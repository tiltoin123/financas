<?php

use Services\Aut;

require_once "../../core/bootstrap.php";

try {
    $ret['success'] = false;
    if (!Aut::check()) {

        if (!$_POST['email'] || !$_POST['senha']) {
            throw new Exception('Preencha todos os campos!');
        }

        $ret['message'] = 'Usuário ou senha incorretos.';

        $logado = Aut::login($_POST['email'], $_POST['senha']);

        if ($logado) {
            $ret['success'] = true;
        }
    } else {
        $ret['message'] = 'Usuário já está logado.';
    }

} catch (Throwable $e) {
    error_log($e);
    $ret = ['success' => false, 'erro' => true, 'mensagem' => $e->getMessage()];
}
Functions::printJson($ret);