<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

// ===== RAIZ FÍSICA (sempre pelo PHP) =====
define(
    'RAIZ',
    rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/'
);

// ===== ENV =====
require RAIZ . 'core/Config.php';
\App\Core\Config::loadDotEnv(RAIZ . '.env');

// ===== CONSTANTES =====
define('APP', \App\Core\Config::get('APP_NAME', 'app'));
define('APP_ENV', \App\Core\Config::get('APP_ENV', 'production'));
define('DEV', APP_ENV !== 'prod');

// ===== AUTOLOADER =====
require RAIZ . 'core/Autoloader.php';
\App\Core\Autoloader::register('App\\', RAIZ);

// ===== URL BASE =====
define('SITE', \App\Core\Config::get('APP_URL', ''));

// ===== SISTEMA =====

// ===== SESSÃO =====
session_name(APP);
session_start();

// ===== INFRA =====
// Auth::ini();

// ===== HEADERS DEV =====
if (DEV) {
    header('X-App-Env: ' . APP_ENV);
}
