<?php

namespace App\Models;

use Core\HttpException;
use PDO;

/**
 * Modelo de la tabla pedidos.
 *
 * Relaciones:
 *   pedidos.id_cliente -> clientes.id_cliente (ON DELETE RESTRICT)
 *   detalle_pedido.id_pedido -> pedidos.id_pedido (ON DELETE CASCADE)
 *
 * El total NO lo envía el cliente: se calcula con la suma de los subtotales.
 */
class Pedido
{
    public const ESTADOS = ['Pendiente', 'Confirmado', 'Preparando', 'Enviado', 'Entregado', 'Cancelado'];

    // Estados finales: el pedido ya no se puede modificar
    public const ESTADOS_FINALES = ['Entregado', 'Cancelado'];

    private DetallePedido $detalle;

    public function __construct(private PDO $db)
    {
        $this->detalle = new DetallePedido($db);
    }

    private const SELECT = "
        SELECT
            pe.id_pedido,
            pe.id_cliente,
            CONCAT(c.nombres, ' ', c.apellidos) AS cliente,
            pe.fecha_pedido,
            pe.direccion_entrega,
            pe.estado,
            pe.total,
            pe.observaciones,
            (SELECT COUNT(*) FROM detalle_pedido d WHERE d.id_pedido = pe.id_pedido) AS total_items
        FROM pedidos pe
        INNER JOIN clientes c ON c.id_cliente = pe.id_cliente
    ";

    /**
     * Lista los pedidos. Se puede filtrar por estado.
     */
    public function obtenerTodos(?string $estado = null): array
    {
        $sql = self::SELECT;
        $params = [];

        if ($estado !== null) {
            $sql .= " WHERE pe.estado = :estado";
            $params[':estado'] = $estado;
        }

        $sql .= " ORDER BY pe.fecha_pedido DESC, pe.id_pedido DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Devuelve el pedido con su lista de productos (detalle).
     */
    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->db->prepare(self::SELECT . " WHERE pe.id_pedido = :id");
        $stmt->execute([':id' => $id]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            $pedido['detalles'] = $this->detalle->obtenerPorPedido($id);
        }

        return $pedido;
    }

    public function obtenerCliente(int $idCliente): array|false
    {
        $stmt = $this->db->prepare("SELECT id_cliente, direccion FROM clientes WHERE id_cliente = :id");
        $stmt->execute([':id' => $idCliente]);

        return $stmt->fetch();
    }

    /**
     * Crea el pedido y, si se envían, sus productos.
     * Todo en una sola transacción: si un producto no tiene stock,
     * no se guarda nada.
     *
     * @param array $detalles [['id_producto' => 1, 'cantidad' => 2], ...]
     */
    public function crear(array $datos, array $detalles = []): int
    {
        return $this->detalle->transaccion(function () use ($datos, $detalles) {

            $stmt = $this->db->prepare("
                INSERT INTO pedidos (id_cliente, direccion_entrega, estado, total, observaciones)
                VALUES (:id_cliente, :direccion, 'Pendiente', 0, :observaciones)
            ");
            $stmt->execute([
                ':id_cliente'    => (int) $datos['id_cliente'],
                ':direccion'     => $datos['direccion_entrega'],
                ':observaciones' => $datos['observaciones'] ?? null,
            ]);

            $idPedido = (int) $this->db->lastInsertId();

            foreach ($detalles as $item) {
                $this->detalle->agregarItem($idPedido, (int) $item['id_producto'], (int) $item['cantidad']);
            }

            $this->detalle->recalcularTotal($idPedido);

            return $idPedido;
        });
    }

    /**
     * Actualiza dirección, estado y observaciones.
     * Si el nuevo estado es Cancelado, devuelve el stock.
     */
    public function actualizar(int $id, array $datos): void
    {
        $this->detalle->transaccion(function () use ($id, $datos) {

            $stmt = $this->db->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $estadoActual = $stmt->fetchColumn();

            if (in_array($estadoActual, self::ESTADOS_FINALES, true)) {
                throw new HttpException(
                    "El pedido {$id} está {$estadoActual} y ya no se puede modificar",
                    409
                );
            }

            if ($datos['estado'] === 'Cancelado') {
                $this->detalle->devolverStockDelPedido($id);
            }

            $stmt = $this->db->prepare("
                UPDATE pedidos
                SET
                    direccion_entrega = :direccion,
                    estado            = :estado,
                    observaciones     = :observaciones
                WHERE id_pedido = :id
            ");
            $stmt->execute([
                ':direccion'     => $datos['direccion_entrega'],
                ':estado'        => $datos['estado'],
                ':observaciones' => $datos['observaciones'] ?? null,
                ':id'            => $id,
            ]);
        });
    }

    /**
     * Elimina el pedido. Sus detalles se borran por ON DELETE CASCADE.
     * Si el pedido no estaba cancelado, primero devuelve el stock.
     */
    public function eliminar(int $id): void
    {
        $this->detalle->transaccion(function () use ($id) {

            $stmt = $this->db->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $estado = $stmt->fetchColumn();

            if (!in_array($estado, ['Pendiente', 'Cancelado'], true)) {
                throw new HttpException(
                    "Solo se pueden eliminar pedidos en estado Pendiente o Cancelado (estado actual: {$estado})",
                    409
                );
            }

            if ($estado === 'Pendiente') {
                $this->detalle->devolverStockDelPedido($id);
            }

            $stmt = $this->db->prepare("DELETE FROM pedidos WHERE id_pedido = :id");
            $stmt->execute([':id' => $id]);
        });
    }
}
