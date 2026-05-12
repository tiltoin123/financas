<?php

use Services\Aut;

require_once "../../core/bootstrap.php";

try {

    if (!$_POST['email'] || !$_POST['senha']) {
        throw new Exception('Preencha todos os campos!');
    }

    $ret['success'] = false;
    $ret['message'] = 'Usuário ou senha incorretos.';

    $logado = Aut::login($_POST['email'], $_POST['senha']);

    if ($logado) {
        $ret['success'] = true;
    }

} catch (Throwable $e) {
    error_log($e);
    $ret = ['success' => false, 'erro' => true, 'mensagem' => $e->getMessage()];
}
Functions::printJson($ret);