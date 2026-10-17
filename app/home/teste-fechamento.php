<?php

/**
 * Teste manual do Fechamento.  Rodar: php teste_fechamento.php
 *
 * Pré-requisitos:
 *  - usuário id 1 existente;
 *  - as 4 recorrências de amostra inseridas (Salário, Aluguel, Parcela, Compra);
 *  - nenhum evento nem saldo prévio. Para zerar e repetir o teste:
 *
 *      DELETE FROM saldos_mensais WHERE usuario_id = 1;
 *      DELETE FROM eventos        WHERE usuario_id = 1;
 *
 *    (as recorrências podem ficar)
 *
 * Usa data fixa (12/10/2026) para o saldo "atual", então o resultado
 * não depende do dia em que você rodar.
 */

require_once "../../core/bootstrap.php";

use Core\Db;
use Models\Evento;
use Models\Fechamento;
use Models\Recorrencia;
use Models\Saldo;
use Models\SaldoMensal;
use Services\Aut;

$uid = $uid = Aut::exigir();;
$falhas = 0;

function conferir(string $nome, mixed $obtido, mixed $esperado): void
{
    global $falhas;

    $ok = (is_float($esperado) || is_int($esperado))
        ? abs((float) $obtido - (float) $esperado) < 0.005
        : $obtido === $esperado;

    if (!$ok) {
        $falhas++;
    }

    printf(
        "[%s] %s | obtido: %s | esperado: %s\n",
        $ok ? 'OK' : 'ERRO',
        $nome,
        json_encode($obtido),
        json_encode($esperado)
    );
}

function saldosFinais(int $uid): array
{
    $r = [];
    foreach (SaldoMensal::listarPorUsuario($uid) as $s) {
        $r[$s->anoMes] = $s->saldoFinal;
    }
    return $r;
}

function totalEventos(int $uid): int
{
    $stmt = Db::con()->prepare('SELECT COUNT(*) FROM eventos WHERE usuario_id = :u');
    $stmt->execute([':u' => $uid]);
    return (int) $stmt->fetchColumn();
}

// ---------------------------------------------------------------
echo "== 1. Fechamento em cadeia ==\n";

conferir('meses pendentes até ago', Fechamento::mesesParaFechar($uid, '2026-08'), ['2026-06', '2026-07', '2026-08']);
conferir('fecharMes(ago) fechou', Fechamento::fecharMes($uid, '2026-08'), ['2026-06', '2026-07', '2026-08']);

$s = saldosFinais($uid);
conferir('saldo final jun', $s['2026-06'] ?? null, 1800.0);
conferir('saldo final jul', $s['2026-07'] ?? null, 3350.0);
conferir('saldo final ago', $s['2026-08'] ?? null, 4820.0);
conferir('eventos materializados (2 + 3 + 4)', totalEventos($uid), 9);

// ---------------------------------------------------------------
echo "\n== 2. Saldo atual e previsto ==\n";

conferir('saldo até 12/10/2026', Saldo::ate($uid, '2026-10-12'), 8170.0);
conferir('previsto fim de dez/2026', Saldo::previstoNoFimDe($uid, '2026-12'), 11020.0);

// ---------------------------------------------------------------
echo "\n== 3. Idempotência ==\n";

conferir('fecharMes de novo (nada pendente)', Fechamento::fecharMes($uid, '2026-08'), []);
conferir('materializar de novo cria 0', Fechamento::materializar($uid, '2026-08'), 0);
conferir('eventos continuam 9', totalEventos($uid), 9);

// ---------------------------------------------------------------
echo "\n== 4. Regra do dia 31 ==\n";

$parcela = null;
foreach (Recorrencia::listarPorUsuario($uid) as $r) {
    if ($r->nome === 'Parcela') {
        $parcela = $r;
    }
}

if ($parcela === null) {
    echo "[ERRO] recorrência 'Parcela' não encontrada\n";
    exit(1);
}

conferir('parcela em setembro cai no dia 30', $parcela->dataNoMes('2026-09'), '2026-09-30');
conferir('parcela em dezembro cai no dia 31', $parcela->dataNoMes('2026-12'), '2026-12-31');
conferir('parcela após data_fim não existe', $parcela->dataNoMes('2027-01'), null);

// ---------------------------------------------------------------
echo "\n== 5. Cascata ao editar mês fechado ==\n";

$aluguel = null;
foreach (Evento::listarPorMes($uid, '2026-06') as $e) {
    if ($e->nome === 'Aluguel') {
        $aluguel = $e;
    }
}

if ($aluguel === null) {
    echo "[ERRO] evento 'Aluguel' de junho não encontrado\n";
    exit(1);
}

$aluguel->atualizar($aluguel->categoriaId, $aluguel->nome, $aluguel->descricao, -1300.0, $aluguel->data);
Fechamento::recalcularDesde($uid, '2026-06');

$s = saldosFinais($uid);
conferir('saldo final jun após editar', $s['2026-06'] ?? null, 1700.0);
conferir('saldo final jul propagou', $s['2026-07'] ?? null, 3250.0);
conferir('saldo final ago propagou', $s['2026-08'] ?? null, 4720.0);
conferir('saldo até 12/10 propagou', Saldo::ate($uid, '2026-10-12'), 8070.0);

// ---------------------------------------------------------------
echo "\n== 6. Hardcap ==\n";

try {
    Fechamento::fecharMes($uid, '2030-01');
    conferir('fechar 2030-01 deveria ser bloqueado', 'não lançou exceção', 'DomainException');
} catch (DomainException $e) {
    conferir('fechar 2030-01 bloqueado', 'DomainException', 'DomainException');
}

// ---------------------------------------------------------------
echo $falhas === 0
    ? "\nTudo certo.\n"
    : "\n{$falhas} verificação(ões) falharam.\n";

exit($falhas === 0 ? 0 : 1);
