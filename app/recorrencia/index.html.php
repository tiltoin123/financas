<?php

/** @var Models\Recorrencia[] $recorrencias */
/** @var Models\Categoria[] $categorias */
/** @var array<int, string> $nomesCategoria */

$h = fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$dataBr = fn(?string $d): string => $d ? date('d/m/Y', strtotime($d)) : '—';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recorrências</title>
    <link rel="stylesheet" href="./index.css">
</head>

<body>
    <h1>Recorrências</h1>

    <p><a href="../home/index.php">Voltar</a></p>

    <button type="button" id="nova">Nova recorrência</button>

    <?php if (!$recorrencias): ?>
        <p>Nenhuma recorrência cadastrada ainda.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Valor (R$)</th>
                    <th>Repetição</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Categoria</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recorrencias as $r): ?>
                    <tr>
                        <td><?= $h($r->nome) ?></td>
                        <td><?= $r->valor < 0 ? 'Despesa' : 'Receita' ?></td>
                        <td><?= number_format(abs($r->valor), 2, ',', '.') ?></td>
                        <td><?= $r->periodicidade === 'mensal' ? 'Mensal (dia ' . $r->diaDoMes . ')' : 'Única' ?></td>
                        <td><?= $dataBr($r->dataInicio) ?></td>
                        <td><?= $r->periodicidade === 'mensal' ? ($r->dataFim ? $dataBr($r->dataFim) : 'Sem fim') : '—' ?></td>
                        <td><?= $h($r->categoriaId !== null ? ($nomesCategoria[$r->categoriaId] ?? 'Sem categoria') : 'Sem categoria') ?></td>
                        <td>
                            <button type="button" class="editar"
                                data-id="<?= $r->id ?>"
                                data-tipo="<?= $r->valor < 0 ? 'despesa' : 'receita' ?>"
                                data-nome="<?= $h($r->nome) ?>"
                                data-descricao="<?= $h($r->descricao) ?>"
                                data-valor="<?= number_format(abs($r->valor), 2, '.', '') ?>"
                                data-categoria-id="<?= $r->categoriaId ?? '' ?>"
                                data-periodicidade="<?= $h($r->periodicidade) ?>"
                                data-data-inicio="<?= $h($r->dataInicio) ?>"
                                data-data-fim="<?= $r->periodicidade === 'mensal' ? $h($r->dataFim) : '' ?>">
                                Editar
                            </button>
                            <button type="button" class="excluir" data-id="<?= $r->id ?>">Apagar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <dialog id="modal" data-hoje="<?= date('Y-m-d') ?>">
        <h2 id="modal-titulo">Nova recorrência</h2>

        <p id="aviso-edicao" hidden>
            Mudanças em valor, nome e categoria valem só para os meses ainda não fechados.
            Meses já fechados não são alterados.
        </p>

        <input type="hidden" id="id">

        <fieldset>
            <legend>Tipo</legend>
            <label><input type="radio" name="tipo" value="despesa"> Despesa</label>
            <label><input type="radio" name="tipo" value="receita"> Receita</label>
        </fieldset>

        <label>Nome
            <input type="text" id="nome" maxlength="150" required>
        </label>

        <label>Descrição
            <input type="text" id="descricao" maxlength="255">
        </label>

        <label>Valor (R$)
            <input type="number" id="valor" step="0.01" min="0.01" max="99999999.99" required>
        </label>

        <label>Categoria
            <select id="categoria_id">
                <option value="">Sem categoria</option>
                <?php foreach (['despesa' => 'Despesas', 'receita' => 'Receitas'] as $tipo => $rotulo): ?>
                    <optgroup label="<?= $rotulo ?>">
                        <?php foreach ($categorias as $c): ?>
                            <?php if ($c->tipo === $tipo): ?>
                                <option value="<?= $c->id ?>"><?= $h($c->nome) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Repetição
            <select id="periodicidade">
                <option value="unica">Única (acontece uma vez)</option>
                <option value="mensal">Mensal</option>
            </select>
        </label>

        <label>Data (ou início, se for mensal)
            <input type="date" id="data_inicio" required>
        </label>
        <small>Na repetição mensal, o dia do mês é o dia desta data. Se o mês não tiver esse dia (ex.: 31), cai no último dia do mês.</small>

        <label id="linha-data-fim">Data final (vazio = sem fim)
            <input type="date" id="data_fim">
        </label>

        <button type="button" id="salvar">Salvar</button>
        <button type="button" id="cancelar">Cancelar</button>
    </dialog>

    <script src="./index.js"></script>
</body>

</html>