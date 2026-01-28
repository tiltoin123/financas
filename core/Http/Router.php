<?php

namespace App\Core\Http;

// Importamos o Request que acabámos de criar

class Router {
    public function run(): void
    {
        // 1. Pega a URL atual (ex: 'contato' ou 'usuario/perfil')
        $uri = Request::uri();

        // 2. Se a URL estiver vazia (raiz do site), define como 'home'
        if ($uri === '' || $uri === 'index.php') {
            $uri = 'home';
        }

        // 3. Transforma a URI no nome do Controller (ex: 'contato' -> 'ContatoController')
        // ucfirst coloca a primeira letra em maiúscula (regra da PSR-4 que vimos!)
        $controllerName = ucfirst($uri) . 'Controller';

        // 4. Define o caminho completo do ficheiro na pasta app/Controllers
        $file = BASE_PATH . 'app' . DS . 'Controllers' . DS . $controllerName . '.php';

        // 5. Verifica se o ficheiro existe fisicamente
        if (file_exists($file)) {
            // Se existir, instanciamos a classe usando o Namespace
            $className = "\\App\\Controllers\\" . $controllerName;

            // O Autoloader vai carregar o ficheiro automaticamente aqui!
            $controller = new $className();

            // Executamos o método padrão (index)
            if (method_exists($controller, 'index')) {
                $controller->index();
            } else {
                http_response_code(500);
                echo "Erro: O método index não existe no $controllerName.";
            }
        } else {
            // Se não achar o ficheiro, manda um 404
            http_response_code(404);
            echo "<h1>404 - Página não encontrada</h1>";
            echo "O controller <b>$controllerName</b> não foi encontrado em app/Controllers.";
        }
    }
}