<?php

namespace App\Core\Db;

use PDO;
use PDOException;

class Db
{
    private static ?PDO $instance = null;

    public static function con(): PDO
    {
        if (self::$instance === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $db   = $_ENV['DB_NAME'] ?? 'financas';
                $user = $_ENV['DB_USER'] ?? 'root';
                $pass = $_ENV['DB_PASSWORD'] ?? '';
                $port = $_ENV['DB_PORT'] ?? '3306';

                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE      => PDO::FETCH_OBJ,
                ];

                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                die("Erro de Conexão com o Banco: " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
