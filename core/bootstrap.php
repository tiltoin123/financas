<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

define(
    'RAIZ',
    rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/'
);

require RAIZ . 'core/Config.php';
\Core\Config::loadDotEnv(RAIZ . '.env');

define('APP', \Core\Config::get('APP_NAME', 'app'));
define('APP_ENV', \Core\Config::get('APP_ENV', 'production'));
define('DEV', APP_ENV !== 'prod');

require RAIZ . 'core/Autoloader.php';
\Core\Autoloader::register('App\\', RAIZ);

define('SITE', \Core\Config::get('APP_URL', ''));

session_name(APP);
session_start();

if (DEV) {
    header('X-App-Env: ' . APP_ENV);
}
