<?php

/** @var Models\Categoria[] $categorias */

$h = fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categorias</title>
    <link rel="stylesheet" href="./index.css">
</head>

<body>
    <h1>Categorias</h1>

    <p><a href="../home/index.php">Voltar</a></p>

    <button type="button" id="nova">Nova categoria</button>

    <?php if (!$categorias): ?>
        <p>Nenhuma categoria cadastrada.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $c): ?>
                    <tr>
                        <td><?= $h($c->nome) ?></td>
                        <td><?= $c->tipo === 'receita' ? 'Receita' : 'Despesa' ?></td>
                        <td>
                            <button type="button" class="editar"
                                data-id="<?= $c->id ?>"
                                data-nome="<?= $h($c->nome) ?>"
                                data-tipo="<?= $h($c->tipo) ?>">
                                Editar
                            </button>
                            <button type="button" class="excluir"
                                data-id="<?= $c->id ?>"
                                data-nome="<?= $h($c->nome) ?>">
                                Apagar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <dialog id="modal">
        <h2 id="modal-titulo">Nova categoria</h2>

        <input type="hidden" id="id">

        <fieldset>
            <legend>Tipo</legend>
            <label><input type="radio" name="tipo" value="despesa"> Despesa</label>
            <label><input type="radio" name="tipo" value="receita"> Receita</label>
        </fieldset>

        <label>Nome
            <input type="text" id="nome" maxlength="100" required>
        </label>

        <button type="button" id="salvar">Salvar</button>
        <button type="button" id="cancelar">Cancelar</button>
    </dialog>

    <script src="./index.js"></script>
</body>

</html>