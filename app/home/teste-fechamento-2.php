<?php

/**
 * Testes complementares do Fechamento.  Rodar: php teste_fechamento_2.php
 *
 * Pré-requisito: usuário 1 com as 4 recorrências de amostra
 * (Salário, Aluguel, Parcela, Compra).
 *
 * ATENÇÃO: cada bloco começa apagando os eventos e saldos do usuário 1
 * e refechando jun/jul/ago de 2026 do zero. As recorrências de amostra
 * não são alteradas. No fim, eventos e saldos ficam zerados.
 */

require_once "../../core/bootstrap.php";

use Core\Db;
use Models\Evento;
use Models\Fechamento;
use Models\Recorrencia;
use Models\SaldoMensal;

$uid = 1;
$falhas = 0;

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

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

/** Confere que $fn lança exatamente a exceção esperada. */
function lanca(string $nome, callable $fn, string $classeEsperada): void
{
    try {
        $fn();
        conferir($nome, 'não lançou exceção', $classeEsperada);
    } catch (Throwable $e) {
        conferir($nome, get_class($e), $classeEsperada);
    }
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

/** Apaga eventos, saldos e recorrências temporárias do teste. Mantém as de amostra. */
function limpar(int $uid): void
{
    $db = Db::con();

    foreach (['saldos_mensais', 'eventos'] as $tabela) {
        $db->prepare("DELETE FROM {$tabela} WHERE usuario_id = :u")->execute([':u' => $uid]);
    }

    $db->prepare("DELETE FROM recorrencias WHERE usuario_id = :u AND nome = 'TESTE-TEMP'")
        ->execute([':u' => $uid]);
}

/** Estado-base: eventos e saldos zerados, depois jun/jul/ago de 2026 fechados. */
function preparar(int $uid): void
{
    limpar($uid);
    Fechamento::fecharMes($uid, '2026-08');
}

function recorrenciaPorNome(int $uid, string $nome): Recorrencia
{
    foreach (Recorrencia::listarPorUsuario($uid) as $r) {
        if ($r->nome === $nome) {
            return $r;
        }
    }

    echo "[ERRO] recorrência '{$nome}' não encontrada. Rode o SQL de amostra.\n";
    exit(1);
}

function eventoPorNome(int $uid, string $anoMes, string $nome): Evento
{
    foreach (Evento::listarPorMes($uid, $anoMes) as $e) {
        if ($e->nome === $nome) {
            return $e;
        }
    }

    echo "[ERRO] evento '{$nome}' de {$anoMes} não encontrado.\n";
    exit(1);
}

// ---------------------------------------------------------------
// Pré-checagem
// ---------------------------------------------------------------

limpar($uid);

if (count(Recorrencia::listarPorUsuario($uid)) < 4) {
    echo "[ERRO] o usuário {$uid} precisa das 4 recorrências de amostra. Rode o SQL de amostra.\n";
    exit(1);
}

// ---------------------------------------------------------------
echo "== A. Setters, construtor e recorrência em mês fechado ==\n";
preparar($uid);

// Recorrência nova preenchida pelos setters (o fluxo do salva.php)
$nova = function (string $dataInicio) use ($uid): Recorrencia {
    $r = new Recorrencia(0, $uid);
    $r->nome = 'TESTE-TEMP';
    $r->periodicidade = 'mensal';
    $r->dataInicio = $dataInicio;
    $r->definirValor('despesa', 10);
    return $r;
};

// Os setters barram valor inválido na hora da atribuição
$vazia = new Recorrencia(0, $uid);
lanca('nome só com espaços', fn() => $vazia->nome = '   ', 'InvalidArgumentException');
lanca('data inexistente (31/02)', fn() => $vazia->dataInicio = '2026-02-31', 'InvalidArgumentException');
lanca('periodicidade desconhecida', fn() => $vazia->periodicidade = 'semanal', 'InvalidArgumentException');
lanca('valor zero', fn() => $vazia->definirValor('despesa', 0), 'InvalidArgumentException');
lanca('tipo desconhecido', fn() => $vazia->definirValor('outro', 10), 'InvalidArgumentException');
lanca('salvar sem preencher nada', fn() => new Recorrencia(0, $uid)->salvar(), 'InvalidArgumentException');

$despesa = $nova('2026-09-10');
conferir('despesa fica negativa', $despesa->valor, -10.0);

// id que não existe (ou é de outro usuário) não vira edição
lanca('construir com id inexistente', fn() => new Recorrencia(999999, $uid), 'InvalidArgumentException');
lanca('construir com id negativo', fn() => new Recorrencia(-1, $uid), 'InvalidArgumentException');

// Regras de mês fechado
lanca('criar com início em jun (fechado)', fn() => $nova('2026-06-15')->salvar(), 'DomainException');
lanca('criar com início em ago (último fechado)', fn() => $nova('2026-08-20')->salvar(), 'DomainException');
lanca('criar com início fora do hardcap', fn() => $nova('2020-01-01')->salvar(), 'DomainException');

// 'Compra' é única e terminou em ago (fechado): transformá-la em mensal sem fim estenderia o fim
$compra = recorrenciaPorNome($uid, 'Compra');
$compra->periodicidade = 'mensal';
$compra->dataFim = null;
lanca('estender recorrência que terminou em mês fechado', fn() => $compra->salvar(), 'DomainException');
conferir(
    'Compra continua única no banco',
    recorrenciaPorNome($uid, 'Compra')->periodicidade,
    'unica'
);

// Editar sem mexer em datas é permitido
$aluguel = recorrenciaPorNome($uid, 'Aluguel');
conferir('editar sem mudar datas é permitido', $aluguel->salvar(), true);

// Início em mês aberto funciona
$setembro = $nova('2026-09-10');
conferir('criar com início em set (aberto) funciona', $setembro->salvar(), true);
conferir('salvar preenche o id da nova', $setembro->id !== null, true);
conferir('apagar recorrência sem eventos', $setembro->apagar(), true);

// ---------------------------------------------------------------
echo "\n== B. Mover evento entre meses ==\n";
preparar($uid);

$s = saldosFinais($uid);
conferir('base: saldo jul', $s['2026-07'] ?? null, 3350.0);
conferir('base: saldo ago', $s['2026-08'] ?? null, 4820.0);

// Os setters do Evento barram dado inválido
$ev = eventoPorNome($uid, '2026-08', 'Compra');
lanca('nome do evento só com espaços', fn() => $ev->nome = '   ', 'InvalidArgumentException');
lanca('data do evento inexistente (31/02)', fn() => $ev->data = '2026-02-31', 'InvalidArgumentException');

// Move a Compra (-80) de ago para jul: o próprio Evento refaz os saldos desde o menor dos dois meses
$ev->data = '2026-07-15';
conferir('mover evento para jul', $ev->salvar(), true);

$s = saldosFinais($uid);
conferir('jul correto após mover', $s['2026-07'] ?? null, 3270.0);
conferir('ago correto após mover', $s['2026-08'] ?? null, 4820.0);
conferir(
    'saldo inicial de ago = saldo final de jul',
    SaldoMensal::buscarPorMes($uid, '2026-08')?->saldoInicial,
    3270.0
);

// Evento é fato de mês fechado: não pode ir para mês aberto
$ev = eventoPorNome($uid, '2026-07', 'Compra');
$ev->data = '2026-09-15';
lanca('mover evento para mês aberto', fn() => $ev->salvar(), 'DomainException');

// ---------------------------------------------------------------
echo "\n== C. Fechar mês com mês posterior já fechado (estado forçado) ==\n";
limpar($uid);

// Estado impossível pelo sistema: ago fechado com saldo velho, jun e jul abertos
Fechamento::materializar($uid, '2026-08');
SaldoMensal::gravar($uid, '2026-08', 999.0, 999.0, true);

conferir(
    'fecharMes(jul) fecha jun e jul',
    Fechamento::fecharMes($uid, '2026-07'),
    ['2026-06', '2026-07']
);

$s = saldosFinais($uid);
conferir('jul fechado', $s['2026-07'] ?? null, 3350.0);
conferir('ago refeito em cascata (não ficou com 999)', $s['2026-08'] ?? null, 4820.0);
conferir('ago continua fechado', SaldoMensal::buscarPorMes($uid, '2026-08')?->fechado, true);
conferir(
    'saldo inicial de ago = saldo final de jul',
    SaldoMensal::buscarPorMes($uid, '2026-08')?->saldoInicial,
    3350.0
);

// ---------------------------------------------------------------
echo "\n== D. Apagar evento de mês fechado ==\n";
preparar($uid);

$parcelaJul = eventoPorNome($uid, '2026-07', 'Parcela');
$parcelaJul->apagar();
conferir('eventos logo após apagar', totalEventos($uid), 8);

Fechamento::recalcularDesde($uid, '2026-07');
$s = saldosFinais($uid);
conferir('jul sem a parcela', $s['2026-07'] ?? null, 3600.0);
conferir('ago propagou', $s['2026-08'] ?? null, 5070.0);
conferir('recalcular não recria o evento apagado', totalEventos($uid), 8);

// Aviso para quando existir "reabrir mês": materializar não sabe que o evento foi apagado de propósito
conferir(
    'materializar(jul) recriaria o evento apagado',
    Fechamento::materializar($uid, '2026-07'),
    1
);

// ---------------------------------------------------------------
limpar($uid);

echo $falhas === 0
    ? "\nTudo certo.\n"
    : "\n{$falhas} verificação(ões) falharam.\n";

exit($falhas === 0 ? 0 : 1);
