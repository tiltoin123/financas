<?php
ob_start();
?>

<h1>Homepage</h1>
<p>Teste do sistema</p>

<?php
$content = ob_get_clean();
?>

<!DOCTYPE html>
<html>

<head>
</head>

<body>

    <header>
        Header
    </header>

    <main>
    </main>

    <footer>
        Footer
    </footer>

</body>

</html>