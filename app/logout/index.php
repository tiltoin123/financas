<?php

require_once "../../core/bootstrap.php";

use Services\Aut;

// Realiza o logout (limpa a sessão)
Aut::logout();

// Carrega a view
require "index.html.php";
