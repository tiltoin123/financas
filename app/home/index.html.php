<?php

/** @var Models\Categoria[] $categorias */
/** @var string $mesAtual */

$h = fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel</title>
    <?php /* inclua aqui seus css */ ?>
</head>

<body>
    <main id="painel" data-mes="<?= $h($mesAtual) ?>">
        <h1>Painel</h1>

        <p><a href="../recorrencia/index.php">Recorrências</a></p>

        <section id="navegacao">
            <button type="button" id="mes-anterior">&larr;</button>
            <strong id="mes-titulo"></strong>
            <button type="button" id="mes-proximo">&rarr;</button>
            <button type="button" id="mes-hoje">Mês atual</button>
        </section>

        <section id="numeros">
            <div><span>Saldo atual</span> <strong id="saldo-atual">—</strong></div>
            <div><span>Saldo previsto no fim do mês</span> <strong id="saldo-previsto">—</strong></div>
            <div><span>Receitas do mês</span> <strong id="receitas">—</strong></div>
            <div><span>Despesas do mês</span> <strong id="despesas">—</strong></div>
        </section>

        <section id="fechamento">
            <span id="situacao-mes"></span>
            <button type="button" id="fechar-mes" hidden>Fechar mês</button>
        </section>

        <section>
            <h2>Lançamentos</h2>

            <p id="lista-vazia" hidden>Nenhum lançamento neste mês.</p>

            <table id="tabela" hidden>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Valor</th>
                        <th>Situação</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="lancamentos"></tbody>
            </table>
        </section>
    </main>

    <dialog id="modal-evento">
        <h2>Editar lançamento</h2>

        <p>Este mês já está fechado: ao salvar, os saldos dos meses fechados a partir dele são refeitos.</p>

        <input type="hidden" id="evento-id">

        <fieldset>
            <legend>Tipo</legend>
            <label><input type="radio" name="evento-tipo" value="despesa"> Despesa</label>
            <label><input type="radio" name="evento-tipo" value="receita"> Receita</label>
        </fieldset>

        <label>Nome
            <input type="text" id="evento-nome" maxlength="150" required>
        </label>

        <label>Descrição
            <input type="text" id="evento-descricao" maxlength="255">
        </label>

        <label>Valor (R$)
            <input type="number" id="evento-valor" step="0.01" min="0.01" max="99999999.99" required>
        </label>

        <label>Categoria
            <select id="evento-categoria">
                <option value="">Sem categoria</option>
                <?php foreach (['despesa' => 'Despesas', 'receita' => 'Receitas'] as $tipo => $rotulo): ?>
                    <optgroup label="<?= $rotulo ?>">
                        <?php foreach ($categorias as $c): ?>
                            <?php if ($c->tipo === $tipo): ?>
                                <option value="<?= $c->getId() ?>"><?= $h($c->nome) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Data
            <input type="date" id="evento-data" required>
        </label>
        <small>A data precisa continuar em um mês já fechado.</small>

        <button type="button" id="evento-salvar">Salvar</button>
        <button type="button" id="evento-cancelar">Cancelar</button>
    </dialog>

    <script src="./index.js"></script>
</body>

</html>