<?php

namespace App\Core\Http;

class Request {
    public static function uri(): string {
        // Pega a URL vindo do navegador (ex: /financas/public/home)
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Pega o caminho onde o index.php está (ex: /financas/public/index.php)
        $scriptName = $_SERVER['SCRIPT_NAME'];

        // Remove o caminho do script da URI para sobrar só o que importa
        // Se a URL for /financas/public/index.php/home, sobra apenas /home
        $basePath = str_replace('index.php', '', $scriptName);
        $uri = str_replace($basePath, '', $uri);

        // Remove o index.php se ele ainda estiver lá e limpa as barras
        $uri = str_replace('index.php', '', $uri);

        return trim(parse_url($uri, PHP_URL_PATH), '/');
    }

    public static function method(): string {
        return $_SERVER['REQUEST_METHOD'];
    }
}