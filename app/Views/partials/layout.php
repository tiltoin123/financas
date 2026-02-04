<?php
require_once __DIR__ . '/header.php';
?>

    <div class="main-wrapper">
        <aside class="sidebar">
            <nav>
                <a href="/financas/home">Dashboard</a>
                <a href="/financas/transacoes">Transações</a>
            </nav>
        </aside>

        <section class="content">
            <?php echo $content; ?>
        </section>
    </div>

<?php
require_once __DIR__ . '/footer.php';
?>