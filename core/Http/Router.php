<?php

namespace App\Core\Http;

class Router
{
    public function run(): void
    {
        // 1. Lógica de URL (Igual a sua, que está correta)
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
        $scriptName = $_SERVER['SCRIPT_NAME'];

        $uriParts = explode('/', trim($uri, '/'));
        $scriptParts = explode('/', trim(dirname($scriptName), '/'));

        // Remove a pasta base (financas) da URI
        foreach ($scriptParts as $part) {
            if (!empty($uriParts) && $uriParts[0] === $part) {
                array_shift($uriParts);
            }
        }

        // 2. Define Módulo e Controller
        $module = !empty($uriParts) ? ucfirst($uriParts[0]) : 'Home';
        $controllerName = $module . 'Controller';

        // --- A MUDANÇA ESTÁ AQUI ---

        // Em vez de rezar para o Autoloader achar, montamos o caminho físico
        // Assumindo que a pasta "app" é minúscula e "Modules" maiúscula (padrão Linux/PSR)
        // Se sua pasta raiz for "App" (maiúsculo), troque abaixo.
        $baseFolder = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

        // 2. Monta o caminho físico absoluto usando o DOCUMENT_ROOT
        // DOCUMENT_ROOT costuma ser C:/wamp/Apache24/htdocs
        $rootPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

        // 3. O caminho completo do arquivo agora leva em conta a pasta do projeto
        $controllerFile = $rootPath . $baseFolder . "/app/Modules/{$module}/{$controllerName}.php";

        // Limpeza final de barras
        $controllerFile = str_replace(['//', '\\'], ['/', '/'], $controllerFile);

        if (file_exists($controllerFile)) {
            require_once $controllerFile; // <--- O Pulo do gato: Carrega o arquivo na força bruta

            // Agora a classe existe na memória, podemos instanciar com o namespace completo
            $controllerClass = "App\\Modules\\{$module}\\{$controllerName}";

            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();

                // Lógica de método (mantida)
                $method = isset($uriParts[1]) && !empty($uriParts[1]) ? $uriParts[1] : 'index';

                if (method_exists($controller, $method)) {
                    $params = array_slice($uriParts, 2);
                    call_user_func_array([$controller, $method], $params);
                } else {
                    echo "Método <b>{$method}</b> não encontrado na classe {$controllerClass}.";
                }
            } else {
                echo "Arquivo encontrado, mas a classe <b>{$controllerClass}</b> não foi definida corretamente dentro dele (verifique o namespace).";
            }
        } else {
            http_response_code(404);
            echo "<h1>Erro 404</h1>";
            echo "Arquivo do Controller não encontrado.<br>";
            echo "Caminho tentado: <code>{$controllerFile}</code>";
        }
    }
}
