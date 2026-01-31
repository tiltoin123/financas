<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../core/Autoloader.php';

App\Core\Autoloader::register('App\\', __DIR__ . '/../');

require_once __DIR__ . '/../core/bootstrap.php';

use App\Core\Http\Router;

$router = new Router();
$router->run();
