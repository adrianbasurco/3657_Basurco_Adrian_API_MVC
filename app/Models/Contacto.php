<?php

namespace App\Models;

use PDO;

/**
 * Modelo de la tabla contactos
 * (mensajes del formulario de contacto de la web).
 */
class Contacto
{
    public const ESTADOS = ['Nuevo', 'En revision', 'Atendido'];

    public function __construct(private PDO $db)
    {
    }

    private const SELECT = "
        SELECT id_contacto, nombre, correo, telefono, asunto, mensaje, fecha_envio, estado
        FROM contactos
    ";

    public function obtenerTodos(?string $estado = null): array
    {
        $sql = self::SELECT;
        $params = [];

        if ($estado !== null) {
            $sql .= " WHERE estado = :estado";
            $params[':estado'] = $estado;
        }

        $sql .= " ORDER BY fecha_envio DESC, id_contacto DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->db->prepare(self::SELECT . " WHERE id_contacto = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    public function crear(array $datos): int
    {
        $sql = "
            INSERT INTO contactos (nombre, correo, telefono, asunto, mensaje, estado)
            VALUES (:nombre, :correo, :telefono, :asunto, :mensaje, :estado)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->parametros($datos));

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $sql = "
            UPDATE contactos
            SET
                nombre   = :nombre,
                correo   = :correo,
                telefono = :telefono,
                asunto   = :asunto,
                mensaje  = :mensaje,
                estado   = :estado
            WHERE id_contacto = :id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->parametros($datos) + [':id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM contactos WHERE id_contacto = :id");
        $stmt->execute([':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':nombre'   => $datos['nombre'],
            ':correo'   => $datos['correo'],
            ':telefono' => !empty($datos['telefono']) ? (string) $datos['telefono'] : null,
            ':asunto'   => $datos['asunto'],
            ':mensaje'  => $datos['mensaje'],
            ':estado'   => $datos['estado'] ?? 'Nuevo',
        ];
    }
}
