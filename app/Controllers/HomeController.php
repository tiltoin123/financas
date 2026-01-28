<?php

namespace App\Controllers;

use App\Core\Http\Response; // Importação da classe correta
use App\Core\Config;

class HomeController {
    public function index(): void {
        // Busca um dado do .env via Config
        $titulo = Config::get('APP_NAME', 'Meu Framework');

        // Chama a View através da classe Response
        Response::view('home', [
            'titulo' => $titulo,
            'ano' => date('Y')
        ]);
    }
}