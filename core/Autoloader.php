<?php

namespace Core;

class Autoloader
{
    /**
     * Mapeia prefixo de namespace => diretório base.
     *
     * @var array<string, string>
     */
    private static array $mappings = [];

    /**
     * Registra um prefixo de namespace para um diretório base.
     */
    public static function register(string $prefix, string $baseDir): void
    {
        if (!is_dir($baseDir)) {
            throw new \RuntimeException("BaseDir inválido: {$baseDir}");
        }

        self::$mappings[$prefix] = $baseDir;

        spl_autoload_register([self::class, 'load']);
    }

    /**
     * Carrega a classe se o namespace corresponder a um dos prefixos registrados.
     */
    private static function load(string $class): void
    {
        foreach (self::$mappings as $prefix => $baseDir) {
            if (str_starts_with($class, $prefix)) {
                $relativeClass = substr($class, strlen($prefix));
                $relativePath  = str_replace('\\', '/', $relativeClass) . '.php';

                $file = rtrim($baseDir, '/') . '/' . $relativePath;

                if (file_exists($file)) {
                    require $file;
                }

                break;
            }
        }
    }
}
