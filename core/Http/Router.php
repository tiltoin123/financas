<?php

namespace App\Core\Http;

use App\Core\Http\Request;

class Router
{
    public function run()
    {
        $uri = Request::uri();

        if (empty($uri)) {
            $uri = 'home';
        }

        $parts = explode('/', $uri);
        $controllerBase = ucfirst(end($parts));

        $controllerName = $controllerBase . 'Controller';
        $controllerClass = "\\App\\Controllers\\" . $controllerName;

        $file = __DIR__ . "/../../app/Controllers/{$controllerName}.php";

        if (file_exists($file)) {
            $controller = new $controllerClass();
            $controller->index();
        } else {
            die("404 - O controller {$controllerName} não foi encontrado em app/Controllers.");
        }
    }
}
