<?php

namespace db;

use Exception;
use mysqli;

require_once '../env.php';

class Mysql
{

    const ERR_DUPLICIDADE       = 1062;
    const ERR_CHAVE_ESTRANGEIRA = 1451;

    const HOST = $_ENV['DB_HOST'];
    const PORT = $_ENV['DB_PORT'];
    const BASE   = $_ENV['DB_DATABASE'];
    const USERNAME = $_ENV['DB_USERNAME'];
    const PASSWORD = $_ENV['DB_PASSWORD'];


    public static function connection($dataSource = null): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $conn = new mysqli(
                self::HOST,
                self::USERNAME,
                self::PASSWORD,
                self::BASE,
                self::PORT
            );

            $conn->set_charset("utf8mb4");

            return $conn;
        } catch (\mysqli_sql_exception $e) {
            throw new Exception("Erro ao conectar no banco: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
