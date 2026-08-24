<?php

require_once "../../core/bootstrap.php";

use Services\Aut;

if (!Aut::check()) {
    header('Location: ../login/');
    exit;
}

require "index.html.php";
