<?php

class Functions
{


    public static function dd(...$args)
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
}
