<?php

require_once "../../core/bootstrap.php";

use Models\Categoria;
use Services\Aut;

$usuario = Aut::user();
if ($usuario === null) {
    header('Location: ../login/');
    exit;
}

$categorias = Categoria::listarPorUsuario($usuario->id);

require "index.html.php";
