<?php

namespace App\Controllers;

use App\Core\Http\Response;
use App\Core\Config;

class HomeController
{
    public function index(): void
    {
        $titulo = Config::get('APP_NAME', 'Meu Framework');

        Response::view('home', [
            'titulo' => $titulo,
            'ano' => date('Y')
        ]);
    }
}
