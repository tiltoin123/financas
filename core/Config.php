<?php

namespace App\Core;

class Config {
    protected static array $data = [];

    /**
     * O nome que você escolheu: carrega o arquivo .env para a memória
     */
    public static function loadDotEnv(string $path): void {
        if (!file_exists($path)) {
            // No TCC, você pode até lançar uma Exception aqui se for obrigatório
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

                // Alimenta as globais do PHP para compatibilidade
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    /**
     * Para você pegar os valores de forma limpa
     */
    public static function get(string $key, $default = null) {
        return self::$data[$key] ?? $default;
    }
}