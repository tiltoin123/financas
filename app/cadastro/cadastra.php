<?php

require_once "../../core/bootstrap.php";

use Models\Usuario;
use Functions\Functions;


header('Content-Type: application/json');

try {
    // Lê o JSON enviado pelo fetch
    $json = file_get_contents('php://input');
    $dados = json_decode($json, true) ?? [];

    $nome  = trim($dados['nome'] ?? '');
    $email = trim($dados['email'] ?? '');
    $senha = $dados['senha'] ?? '';

    if ($nome === '' || $email === '' || $senha === '') {
        throw new Exception('Preencha todos os campos!');
    }

    // Se o construtor for (id, nome, email, senha)
    $usuario = new Usuario(0, $nome, $email, $senha);
    $usuario->salvar();

    $ret = [
        'success' => true,
        'message' => 'Usuário cadastrado com sucesso!'
    ];
} catch (Throwable $e) {
    Functions::dd($e);
    error_log($e);
    $ret = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

Functions::printJson($ret);
