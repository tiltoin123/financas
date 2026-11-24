<?php

declare(strict_types=1);

use Core\Autoloader;
use Core\Config;

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

define(
    'RAIZ',
    rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/'
);

require_once RAIZ . 'core/Config.php';
require_once RAIZ . 'core/Functions.php';

Config::loadDotEnv(RAIZ . '.env');

define('APP', Config::get('APP_NAME', 'app'));
define('APP_ENV', Config::get('APP_ENV', 'production'));
define('DEV', APP_ENV !== 'production');

require_once RAIZ . 'core/Autoloader.php';

Autoloader::register('App\\', RAIZ);
Autoloader::register('Models\\', RAIZ . 'Models/');
Autoloader::register('Services\\', RAIZ . 'Services/');
Autoloader::register('Core\\', RAIZ . 'core/');

define('SITE', Config::get('APP_URL', ''));

// A configuração do cookie deve ocorrer antes de session_start().
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name(APP);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// CSRF para toda requisição POST iniciada no navegador.
// A página recebe um token no HTML e os fetches enviam X-CSRF-Token.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!\Core\Csrf::validar($enviado)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Requisição não autorizada. Recarregue a página.']);
        exit;
    }
}

if (DEV) {
    header('X-App-Env: ' . APP_ENV);
}
