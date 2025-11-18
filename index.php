<?php

use db\Mysql;

$con = Mysql::connection();

if ($con) {
    echo "<p>Conexão MySQL OK</p>" . $con;
} else {
    echo "<p>Falha na conexão.</p>";
    exit;
}

header('Location: app/index.php');
exit;
