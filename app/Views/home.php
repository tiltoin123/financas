<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title><?= $titulo ?? 'Home' ?></title>
</head>

<body>
<h1>Página Inicial do Projeto Finanças</h1>
<p>Se você está vendo isso, o Framework está funcionando no Apache!</p>
<?php
$db = \App\Core\Db\Db::con();
$version = $db->query('SELECT VERSION()')->fetchColumn();
?>
<div><?= $version ?></div>
</body>

</html>