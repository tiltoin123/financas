<?php

namespace App\Core\Http;

use App\Core\Http\Request;

class Router
{
    public function run()
    {
        $uri = Request::uri();

        // 1. Se a URI vier vazia (raiz), define como 'home'
        if (empty($uri)) {
            $uri = 'home';
        }

        // 2. TRATAMENTO DO ERRO: 
        // Pegamos apenas a última parte da URI (caso venha financas/home)
        // E forçamos a primeira letra a ser Maiúscula (home -> Home)
        $parts = explode('/', $uri);
        $controllerBase = ucfirst(end($parts));

        $controllerName = $controllerBase . 'Controller';
        $controllerClass = "\\App\\Controllers\\" . $controllerName;

        // 3. Verifica se o arquivo físico existe antes de instanciar
        $file = __DIR__ . "/../../app/Controllers/{$controllerName}.php";

        if (file_exists($file)) {
            $controller = new $controllerClass();
            $controller->index();
        } else {
            die("404 - O controller {$controllerName} não foi encontrado em app/Controllers.");
        }
    }
}
