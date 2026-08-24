<?php
require_once "../../core/bootstrap.php";

use Services\Aut;

Aut::logout();
header('Location: ../login/');
exit;
