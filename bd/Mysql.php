<?php

namespace db;

use Exception;
use mysqli;

require_once '../utils/env.php';
load_env(__DIR__ . '/../');

class Mysql
{
    const ERR_DUPLICIDADE       = 1062;
    const ERR_CHAVE_ESTRANGEIRA = 1451;

    private static ?mysqli $conn = null;
    private static string $HOST;
    private static string $PORT;
    private static string $BASE;
    private static string $USERNAME;
    private static string $PASSWORD;

    private static function loadEnv(): void
    {
        self::$HOST     = $_ENV['DB_HOST'];
        self::$PORT     = $_ENV['DB_PORT'];
        self::$BASE     = $_ENV['DB_DATABASE'];
        self::$USERNAME = $_ENV['DB_USERNAME'];
        self::$PASSWORD = $_ENV['DB_PASSWORD'];
    }

    /**
     * @throws Exception
     */
    public static function connection(): mysqli
    {
        if (self::$conn !== null) {
            return self::$conn;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::loadEnv();

        try {
            $mysqli = mysqli_init();
            $mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);

            $mysqli->real_connect(
                self::$HOST,
                self::$USERNAME,
                self::$PASSWORD,
                self::$BASE,
                self::$PORT
            );

            $mysqli->set_charset("utf8mb4");

            self::$conn = $mysqli;
            return self::$conn;
        } catch (\mysqli_sql_exception $e) {
            throw new Exception(
                "Erro ao conectar no banco: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
