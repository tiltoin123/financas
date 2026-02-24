<?php

namespace App\Core\Http;

class Response
{
    /**
     * * @param string $view
     * @param array $data
     */
    public static function view(string $view, array $data = []): void
    {
        extract($data);

        $viewFile = __DIR__ . "/../../app/Views/{$view}.php";

        if (!file_exists($viewFile)) {
            die("Erro: O arquivo da página não foi encontrado em: {$viewFile}");
        }

        ob_start();
        require_once $viewFile;
        $content = ob_get_clean();
        $layoutFile = __DIR__ . "/../../../app/Views/partials/layout.php";

        if (file_exists($layoutFile)) {
            require_once $layoutFile;
        } else {
            echo $content;
        }
    }

    public static function json(array $data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    public static function redirect(string $url): void
    {
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $baseDir = str_replace('/public/index.php', '', $scriptName);

        $url = '/' . ltrim($url, '/');

        header("Location: " . $baseDir . $url);
        exit;
    }
}
