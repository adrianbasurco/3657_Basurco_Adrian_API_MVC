<?php

namespace App\Models;

use Core\HttpException;
use PDO;
use Throwable;

/**
 * Modelo de la tabla detalle_pedido.
 *
 * Relaciones:
 *   detalle_pedido.id_pedido   -> pedidos.id_pedido     (ON DELETE CASCADE)
 *   detalle_pedido.id_producto -> productos.id_producto (ON DELETE RESTRICT)
 *   UNIQUE (id_pedido, id_producto): un producto solo una vez por pedido
 *
 * Reglas de negocio:
 *   - El precio_unitario se toma del producto (no lo envía el cliente).
 *   - subtotal = cantidad * precio_unitario.
 *   - Al agregar un producto se descuenta el stock; al quitarlo se devuelve.
 *   - Después de cada cambio se recalcula el total del pedido.
 *   - Solo se modifican pedidos en estado Pendiente o Confirmado.
 *   - Todo se hace dentro de una transacción (si algo falla, ROLLBACK).
 */
class DetallePedido
{
    public const ESTADOS_EDITABLES = ['Pendiente', 'Confirmado'];

    public function __construct(private PDO $db)
    {
    }

    private const SELECT = "
        SELECT
            d.id_detalle,
            d.id_pedido,
            d.id_producto,
            pr.nombre AS producto,
            pr.presentacion,
            d.cantidad,
            d.precio_unitario,
            d.subtotal
        FROM detalle_pedido d
        INNER JOIN productos pr ON pr.id_producto = d.id_producto
    ";

    // =====================================================
    // CONSULTAS
    // =====================================================

    public function obtenerTodos(): array
    {
        $stmt = $this->db->prepare(self::SELECT . " ORDER BY d.id_pedido, d.id_detalle");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->db->prepare(self::SELECT . " WHERE d.id_detalle = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    public function obtenerPorPedido(int $idPedido): array
    {
        $stmt = $this->db->prepare(self::SELECT . " WHERE d.id_pedido = :id ORDER BY d.id_detalle");
        $stmt->execute([':id' => $idPedido]);

        return $stmt->fetchAll();
    }

    // =====================================================
    // CRUD (con transacción)
    // =====================================================

    public function crear(int $idPedido, int $idProducto, int $cantidad): int
    {
        return $this->transaccion(function () use ($idPedido, $idProducto, $cantidad) {

            $this->bloquearPedidoEditable($idPedido);

            $id = $this->agregarItem($idPedido, $idProducto, $cantidad);

            $this->recalcularTotal($idPedido);

            return $id;
        });
    }

    public function actualizarCantidad(int $id, int $nuevaCantidad): void
    {
        $this->transaccion(function () use ($id, $nuevaCantidad) {

            $detalle = $this->bloquearDetalle($id);

            $this->bloquearPedidoEditable((int) $detalle['id_pedido']);

            $producto = $this->bloquearProducto((int) $detalle['id_producto'], false);

            // Diferencia de unidades: positiva = se piden más, negativa = se devuelven
            $diferencia = $nuevaCantidad - (int) $detalle['cantidad'];

            if ($diferencia > 0 && (int) $producto['stock'] < $diferencia) {
                throw new HttpException(
                    "Stock insuficiente para {$producto['nombre']}: disponible {$producto['stock']}, se necesitan {$diferencia} más",
                    409
                );
            }

            $this->moverStock((int) $detalle['id_producto'], -$diferencia);

            $stmt = $this->db->prepare("
                UPDATE detalle_pedido
                SET cantidad = :cantidad,
                    subtotal = precio_unitario * :cantidad2
                WHERE id_detalle = :id
            ");
            $stmt->execute([
                ':cantidad'  => $nuevaCantidad,
                ':cantidad2' => $nuevaCantidad,
                ':id'        => $id,
            ]);

            $this->recalcularTotal((int) $detalle['id_pedido']);
        });
    }

    public function eliminar(int $id): void
    {
        $this->transaccion(function () use ($id) {

            $detalle = $this->bloquearDetalle($id);

            $this->bloquearPedidoEditable((int) $detalle['id_pedido']);

            // Devolver las unidades al stock
            $this->moverStock((int) $detalle['id_producto'], (int) $detalle['cantidad']);

            $stmt = $this->db->prepare("DELETE FROM detalle_pedido WHERE id_detalle = :id");
            $stmt->execute([':id' => $id]);

            $this->recalcularTotal((int) $detalle['id_pedido']);
        });
    }

    // =====================================================
    // MÉTODOS USADOS TAMBIÉN POR EL MODELO Pedido
    // (deben llamarse dentro de una transacción)
    // =====================================================

    /**
     * Inserta un producto en el pedido y descuenta el stock.
     */
    public function agregarItem(int $idPedido, int $idProducto, int $cantidad): int
    {
        $producto = $this->bloquearProducto($idProducto, true);

        // UNIQUE (id_pedido, id_producto)
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM detalle_pedido WHERE id_pedido = :pedido AND id_producto = :producto"
        );
        $stmt->execute([':pedido' => $idPedido, ':producto' => $idProducto]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new HttpException(
                "El producto {$producto['nombre']} ya está en el pedido {$idPedido}. Actualiza su cantidad en lugar de agregarlo otra vez",
                409
            );
        }

        if ((int) $producto['stock'] < $cantidad) {
            throw new HttpException(
                "Stock insuficiente para {$producto['nombre']}: disponible {$producto['stock']}, solicitado {$cantidad}",
                409
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO detalle_pedido
                (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
            VALUES
                (:pedido, :producto, :cantidad, :precio, :subtotal)
        ");
        $stmt->execute([
            ':pedido'   => $idPedido,
            ':producto' => $idProducto,
            ':cantidad' => $cantidad,
            ':precio'   => $producto['precio'],
            ':subtotal' => round((float) $producto['precio'] * $cantidad, 2),
        ]);

        $id = (int) $this->db->lastInsertId();

        $this->moverStock($idProducto, -$cantidad);

        return $id;
    }

    /**
     * Devuelve al stock todas las unidades de un pedido
     * (al cancelarlo o eliminarlo).
     */
    public function devolverStockDelPedido(int $idPedido): void
    {
        $stmt = $this->db->prepare("
            UPDATE productos
            SET stock = stock + (
                SELECT d.cantidad
                FROM detalle_pedido d
                WHERE d.id_pedido = :id
                  AND d.id_producto = productos.id_producto
            )
            WHERE id_producto IN (
                SELECT id_producto FROM detalle_pedido WHERE id_pedido = :id2
            )
        ");
        $stmt->execute([':id' => $idPedido, ':id2' => $idPedido]);
    }

    public function recalcularTotal(int $idPedido): void
    {
        $stmt = $this->db->prepare("
            UPDATE pedidos
            SET total = (
                SELECT COALESCE(SUM(subtotal), 0)
                FROM detalle_pedido
                WHERE id_pedido = :id
            )
            WHERE id_pedido = :id2
        ");
        $stmt->execute([':id' => $idPedido, ':id2' => $idPedido]);
    }

    /**
     * Ejecuta una función dentro de una transacción.
     * Si ya hay una transacción abierta, se usa la misma.
     */
    public function transaccion(callable $funcion): mixed
    {
        if ($this->db->inTransaction()) {
            return $funcion();
        }

        $this->db->beginTransaction();

        try {
            $resultado = $funcion();
            $this->db->commit();
            return $resultado;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // =====================================================
    // AYUDANTES PRIVADOS
    // FOR UPDATE bloquea la fila hasta terminar la transacción
    // para que dos pedidos no descuenten el mismo stock a la vez.
    // =====================================================

    private function bloquearPedidoEditable(int $idPedido): array
    {
        $stmt = $this->db->prepare("SELECT id_pedido, estado FROM pedidos WHERE id_pedido = :id FOR UPDATE");
        $stmt->execute([':id' => $idPedido]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            throw new HttpException("Pedido con id {$idPedido} no encontrado", 404);
        }

        if (!in_array($pedido['estado'], self::ESTADOS_EDITABLES, true)) {
            throw new HttpException(
                "El pedido {$idPedido} está en estado {$pedido['estado']} y ya no se puede modificar su detalle",
                409
            );
        }

        return $pedido;
    }

    private function bloquearProducto(int $idProducto, bool $debeEstarActivo): array
    {
        $stmt = $this->db->prepare(
            "SELECT id_producto, nombre, precio, stock, activo FROM productos WHERE id_producto = :id FOR UPDATE"
        );
        $stmt->execute([':id' => $idProducto]);
        $producto = $stmt->fetch();

        if (!$producto || ($debeEstarActivo && (int) $producto['activo'] !== 1)) {
            throw new HttpException("El producto {$idProducto} no existe o está inactivo", 400);
        }

        return $producto;
    }

    private function bloquearDetalle(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM detalle_pedido WHERE id_detalle = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $detalle = $stmt->fetch();

        if (!$detalle) {
            throw new HttpException("Detalle con id {$id} no encontrado", 404);
        }

        return $detalle;
    }

    /**
     * Suma (positivo) o resta (negativo) unidades al stock.
     */
    private function moverStock(int $idProducto, int $unidades): void
    {
        if ($unidades === 0) {
            return;
        }

        $stmt = $this->db->prepare("UPDATE productos SET stock = stock + :unidades WHERE id_producto = :id");
        $stmt->execute([':unidades' => $unidades, ':id' => $idProducto]);
    }
}
