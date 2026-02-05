<?php

const DS = DIRECTORY_SEPARATOR;
define('BASE_PATH', dirname(__DIR__) . DS);
const APP_PATH = BASE_PATH . 'app' . DS;
const CORE_PATH = BASE_PATH . 'core' . DS;

App\Core\Config::loadDotEnv(BASE_PATH . '.env');

if (App\Core\Config::get('APP_DEBUG') === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set(App\Core\Config::get('APP_TIMEZONE', 'America/Sao_Paulo'));

if (file_exists(CORE_PATH . 'Functions.php')) {
    require_once CORE_PATH . 'Functions.php';
}
