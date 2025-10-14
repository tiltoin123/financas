<?php

namespace db;

use Exception;
use mysqli;

require_once '../utils/env.php';
load_env(__DIR__ . '/../');


class Mysql
{

    const int ERR_DUPLICIDADE       = 1062;
    const int ERR_CHAVE_ESTRANGEIRA = 1451;

    private static string $HOST;
    private static string $PORT;
    private static string $BASE;
    private static string $USERNAME;
    private static string $PASSWORD;

    public static function getValues(): void
    {
        self::$HOST = $_ENV['DB_HOST'];
        self::$PORT = $_ENV['DB_PORT'];
        self::$BASE = $_ENV['DB_DATABASE'];
        self::$USERNAME = $_ENV['DB_USERNAME'];
        self::$PASSWORD = $_ENV['DB_PASSWORD'];
    }


    /**
     * @throws Exception
     */
    public static function connection(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::getValues();
        try {
            $conn = new mysqli(
                self::$HOST,
                self::$USERNAME,
                self::$PASSWORD,
                self::$BASE,
                self::$PORT
            );

            $conn->set_charset("utf8mb4");

            return $conn;
        } catch (\mysqli_sql_exception $e) {
            throw new Exception("Erro ao conectar no banco: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
