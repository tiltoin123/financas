<?php

namespace Core;

class Config
{
    protected static array $data = [];

    public static function loadDotEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;

            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);

                $name = trim($name);
                $value = trim(trim($value), '"\'');

                self::$data[$name] = $value;

                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        return self::$data[$key] ?? $default;
    }
}
