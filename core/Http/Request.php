<?php

namespace App\Core\Http;

class Request
{
    public static function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $scriptName = $_SERVER['SCRIPT_NAME'];

        $basePath = str_replace('index.php', '', $scriptName);
        $uri = str_replace($basePath, '', $uri);

        $uri = str_replace('index.php', '', $uri);

        return trim(parse_url($uri, PHP_URL_PATH), '/');
    }

    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }
}
