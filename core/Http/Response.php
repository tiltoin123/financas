<?php

namespace App\Core\Http;

class Response {
    /**
     * Renderiza uma view (arquivo HTML/PHP)
     * * @param string $view Nome do arquivo dentro de app/Views/ (sem .php)
     * @param array $data Dados que serão passados para a tela
     */
    public static function view(string $view, array $data = []) {
        // 1. Caminho do arquivo da view
        $file = BASE_PATH . 'app' . DS . 'Views' . DS . $view . '.php';

        if (file_exists($file)) {
            // 2. Transforma ['nome' => 'Pedro'] em $nome = 'Pedro'
            extract($data);

            // 3. Inclui o arquivo que agora tem acesso às variáveis acima
            require_once $file;
        } else {
            die("Erro: A view <b>$view</b> não foi encontrada em app/Views/");
        }
    }

    /**
     * Retorna um JSON (útil para APIs no seu TCC)
     */
    public static function json(array $data, int $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}