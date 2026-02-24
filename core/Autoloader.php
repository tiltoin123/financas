<?php

namespace App\Core;

class Autoloader
{
    private string $prefix;
    private string $baseDir;

    public function __construct(string $prefix, string $baseDir)
    {
        $this->prefix = $prefix;
        $this->baseDir = $baseDir;
    }

    public static function register(string $prefix, string $baseDir): void
    {
        $loader = new self($prefix, $baseDir);

        spl_autoload_register([$loader, 'load']);
    }

    private function load(string $class): void
    {
        $len = strlen($this->prefix);
        if (strncmp($this->prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);

        $path = str_replace('\\', '/', $relativeClass);

        $folder = (str_starts_with($path, 'Core/')) ? 'core/' : 'app/';

        if ($folder === 'core/') {
            $path = substr($path, 5);
        }

        $file = rtrim($this->baseDir, '/') . '/' . $folder . $path . '.php';

        if (file_exists($file)) {
            require $file;
        } else {
        }
    }
}
