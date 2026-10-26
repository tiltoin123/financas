<?php

require_once "../../core/bootstrap.php";

use Models\Categoria;
use Models\Recorrencia;
use Services\Aut;

$usuario = Aut::user();
if ($usuario === null) {
    header('Location: ../login/');
    exit;
}

$recorrencias = Recorrencia::listarPorUsuario($usuario->id);
$categorias   = Categoria::listarPorUsuario($usuario->id);

$nomesCategoria = [];
foreach ($categorias as $c) {
    $nomesCategoria[$c->id] = $c->nome;
}

require "index.html.php";
