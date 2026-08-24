<?php

namespace Functions;

class Functions
{


    public static function dd(...$args): void
    {
        foreach ($args as $arg) {
            echo "\n";
            if (is_scalar($arg)) {
                var_dump($arg);
            } else {
                print_r($arg);
            }
            echo "\n";
        }
        die();
    }

    public static function printJson($ret): void
    {
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($ret, JSON_PRETTY_PRINT);
    }
}
