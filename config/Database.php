<?php

namespace Config;

use PDO;
use PDOException;

/**
 * Conexión a MySQL con PDO.
 * Los datos de conexión se leen del archivo .env (ver .env.example).
 */
class Database
{
    private string $host;
    private string $port;
    private string $database;
    private string $username;
    private string $password;

    public function __construct()
    {
        Env::cargar(__DIR__ . '/../.env');

        $this->host     = Env::get('DB_HOST', 'localhost');
        $this->port     = Env::get('DB_PORT', '3306');
        $this->database = Env::get('DB_NAME', 'marah_db');
        $this->username = Env::get('DB_USER', 'root');
        $this->password = Env::get('DB_PASS', '');
    }

    public function connect(): PDO
    {
        try {

            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";

            $connection = new PDO(
                $dsn,
                $this->username,
                $this->password
            );

            // Lanzar excepciones cuando ocurra un error SQL
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Devolver los resultados como arreglos asociativos
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Usar consultas preparadas reales (protege de inyección SQL)
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            return $connection;

        } catch (PDOException $e) {

            // No se muestra el mensaje original para no exponer datos de conexión
            throw new PDOException('Error al conectar con la base de datos.');
        }
    }
}
