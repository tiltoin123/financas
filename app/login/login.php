<?php

use Services\Aut;

require_once "../../core/bootstrap.php";

try {
    $ret['success'] = false;

    if (!Aut::check()) {
        $json = file_get_contents('php://input');
        $dados = json_decode($json, true) ?? [];

        $email = trim($dados['email'] ?? '');
        $senha = $dados['senha'] ?? '';

        if ($email === '' || $senha === '') {
            throw new Exception('Preencha todos os campos!');
        }

        $ret['message'] = 'Usuário ou senha incorretos.';

        $logado = Aut::login($email, $senha);

        if ($logado) {
            $ret['success'] = true;
            $ret['message'] = 'Login realizado com sucesso!';
        }
    } else {
        $ret['success'] = true;
        $ret['message'] = 'Usuário já está logado.';
    }
} catch (Throwable $e) {
    error_log($e);
    $ret = ['success' => false, 'erro' => true, 'mensagem' => $e->getMessage()];
}

Functions::printJson($ret);
