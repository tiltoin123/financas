<?php

declare(strict_types=1);

use Core\Autoloader;
use Core\Config;

error_reporting(E_ALL);
ini_set('display_errors', '0');

define(
    'RAIZ',
    rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/'
);

require_once RAIZ . 'core/Config.php';
require_once RAIZ . 'core/Functions.php';
Config::loadDotEnv(RAIZ . '.env');

define('APP', Config::get('APP_NAME', 'app'));
define('APP_ENV', Config::get('APP_ENV', 'production'));
const DEV = APP_ENV !== 'prod';
require_once RAIZ . 'core/Autoloader.php';
Autoloader::register('App\\', RAIZ);

define('SITE', Config::get('APP_URL', ''));

session_name(APP);
session_start();

if (DEV) {
    header('X-App-Env: ' . APP_ENV);
}
