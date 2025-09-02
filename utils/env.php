<?php
/**
 * 
 *
 * @param string
 */
function load_env(string $path) {
    $filePath = rtrim($path, '/') . '/.env';

    if (!file_exists($filePath) || !is_readable($filePath)) {
        throw new \RuntimeException('O arquivo .env não foi encontrado ou não pode ser lido.');
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value, ' "'); 

        if (!array_key_exists($key, $_ENV)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}
?>