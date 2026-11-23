<?php

namespace App\Models;

use PDO;

/**
 * Modelo de la tabla clientes.
 * telefono y correo son UNIQUE en la base de datos.
 * El DELETE es físico, pero solo si el cliente no tiene pedidos
 * (pedidos.id_cliente tiene ON DELETE RESTRICT).
 */
class Cliente
{
    public function __construct(private PDO $db)
    {
    }

    private const SELECT = "
        SELECT
            c.id_cliente,
            c.nombres,
            c.apellidos,
            c.telefono,
            c.correo,
            c.direccion,
            c.fecha_registro,
            (SELECT COUNT(*) FROM pedidos p WHERE p.id_cliente = c.id_cliente) AS total_pedidos
        FROM clientes c
    ";

    public function obtenerTodos(): array
    {
        $stmt = $this->db->prepare(self::SELECT . " ORDER BY c.apellidos, c.nombres");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->db->prepare(self::SELECT . " WHERE c.id_cliente = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    /**
     * Verifica si un valor de un campo único (telefono o correo)
     * ya está registrado por otro cliente.
     */
    public function existeValor(string $campo, string $valor, ?int $excluirId = null): bool
    {
        // El nombre de la columna no viene del usuario: solo se permiten estos dos
        if (!in_array($campo, ['telefono', 'correo'], true)) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM clientes WHERE {$campo} = :valor AND id_cliente <> :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':valor' => $valor, ':id' => $excluirId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): int
    {
        $sql = "
            INSERT INTO clientes (nombres, apellidos, telefono, correo, direccion)
            VALUES (:nombres, :apellidos, :telefono, :correo, :direccion)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->parametros($datos));

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $sql = "
            UPDATE clientes
            SET
                nombres   = :nombres,
                apellidos = :apellidos,
                telefono  = :telefono,
                correo    = :correo,
                direccion = :direccion
            WHERE id_cliente = :id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->parametros($datos) + [':id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM clientes WHERE id_cliente = :id");
        $stmt->execute([':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':nombres'   => $datos['nombres'],
            ':apellidos' => $datos['apellidos'],
            ':telefono'  => (string) $datos['telefono'],
            // Si el correo viene vacío se guarda NULL (UNIQUE permite varios NULL)
            ':correo'    => !empty($datos['correo']) ? $datos['correo'] : null,
            ':direccion' => !empty($datos['direccion']) ? $datos['direccion'] : null,
        ];
    }
}
