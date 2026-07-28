<?php
require_once "../../core/bootstrap.php";
use Models\Usuario;

try {
$usuario = new Usuario(0,$_POST['nome'],$_POST['email'],$_POST['senha']);
$usuario->salvar();
}catch (exception $e){
    throw new Exception($e);
}
