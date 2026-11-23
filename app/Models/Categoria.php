<?php

namespace App\Models;

use PDO;

/**
 * Modelo de la tabla categorias.
 * El DELETE es lógico: cambia activo a 0.
 */
class Categoria
{
    public function __construct(private PDO $db)
    {
    }

    public function obtenerTodos(): array
    {
        $sql = "
            SELECT
                c.id_categoria,
                c.nombre,
                c.descripcion,
                c.activo,
                COUNT(p.id_producto) AS total_productos
            FROM categorias c
            LEFT JOIN productos p
                ON p.id_categoria = c.id_categoria
                AND p.activo = 1
            WHERE c.activo = 1
            GROUP BY c.id_categoria, c.nombre, c.descripcion, c.activo
            ORDER BY c.nombre
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): array|false
    {
        $sql = "
            SELECT id_categoria, nombre, descripcion, activo
            FROM categorias
            WHERE id_categoria = :id
              AND activo = 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    /**
     * Verifica si ya existe una categoría con ese nombre
     * (se puede excluir un id, para el caso de actualizar).
     */
    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM categorias
            WHERE nombre = :nombre
              AND id_categoria <> :id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':id'     => $excluirId ?? 0
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function contarProductosActivos(int $id): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM productos
            WHERE id_categoria = :id
              AND activo = 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    public function crear(array $datos): int
    {
        $sql = "
            INSERT INTO categorias (nombre, descripcion)
            VALUES (:nombre, :descripcion)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre'      => $datos['nombre'],
            ':descripcion' => $datos['descripcion'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $sql = "
            UPDATE categorias
            SET
                nombre      = :nombre,
                descripcion = :descripcion
            WHERE id_categoria = :id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre'      => $datos['nombre'],
            ':descripcion' => $datos['descripcion'] ?? null,
            ':id'          => $id
        ]);
    }

    public function eliminar(int $id): void
    {
        $sql = "UPDATE categorias SET activo = 0 WHERE id_categoria = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
    }
}
