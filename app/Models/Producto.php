<?php

namespace App\Models;

use PDO;

/**
 * Modelo de la tabla productos.
 * Relación: productos.id_categoria -> categorias.id_categoria
 * El DELETE es lógico: cambia activo a 0.
 */
class Producto
{
    public function __construct(private PDO $db)
    {
    }

    private const SELECT = "
        SELECT
            p.id_producto,
            p.id_categoria,
            c.nombre AS categoria,
            p.nombre,
            p.descripcion,
            p.presentacion,
            p.precio,
            p.stock,
            p.activo,
            p.fecha_registro
        FROM productos p
        INNER JOIN categorias c
            ON p.id_categoria = c.id_categoria
    ";

    /**
     * Lista los productos activos. Se puede filtrar por categoría.
     */
    public function obtenerTodos(?int $idCategoria = null): array
    {
        $sql = self::SELECT . " WHERE p.activo = 1";
        $params = [];

        if ($idCategoria !== null) {
            $sql .= " AND p.id_categoria = :id_categoria";
            $params[':id_categoria'] = $idCategoria;
        }

        $sql .= " ORDER BY p.nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): array|false
    {
        $sql = self::SELECT . " WHERE p.id_producto = :id AND p.activo = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    public function categoriaActivaExiste(int $idCategoria): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM categorias WHERE id_categoria = :id AND activo = 1"
        );
        $stmt->execute([':id' => $idCategoria]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Cuenta en cuántos pedidos aún abiertos aparece el producto.
     */
    public function contarPedidosAbiertos(int $id): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM detalle_pedido d
            INNER JOIN pedidos pe ON pe.id_pedido = d.id_pedido
            WHERE d.id_producto = :id
              AND pe.estado IN ('Pendiente', 'Confirmado', 'Preparando', 'Enviado')
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    public function crear(array $datos): int
    {
        $sql = "
            INSERT INTO productos
                (id_categoria, nombre, descripcion, presentacion, precio, stock)
            VALUES
                (:id_categoria, :nombre, :descripcion, :presentacion, :precio, :stock)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_categoria' => (int) $datos['id_categoria'],
            ':nombre'       => $datos['nombre'],
            ':descripcion'  => $datos['descripcion'] ?? null,
            ':presentacion' => $datos['presentacion'],
            ':precio'       => $datos['precio'],
            ':stock'        => (int) ($datos['stock'] ?? 0),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $sql = "
            UPDATE productos
            SET
                id_categoria = :id_categoria,
                nombre       = :nombre,
                descripcion  = :descripcion,
                presentacion = :presentacion,
                precio       = :precio,
                stock        = :stock
            WHERE id_producto = :id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_categoria' => (int) $datos['id_categoria'],
            ':nombre'       => $datos['nombre'],
            ':descripcion'  => $datos['descripcion'] ?? null,
            ':presentacion' => $datos['presentacion'],
            ':precio'       => $datos['precio'],
            ':stock'        => (int) ($datos['stock'] ?? 0),
            ':id'           => $id,
        ]);
    }

    public function eliminar(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE productos SET activo = 0 WHERE id_producto = :id");
        $stmt->execute([':id' => $id]);
    }
}
