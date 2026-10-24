<?php

/**
 * Teste da Categoria.  Rodar: php teste_categoria.php
 * Usa o usuário 1 e remove tudo que cria (nomes que começam com TESTE-CAT).
 */

require_once "../../core/bootstrap.php";

use Core\Db;
use Models\Categoria;
use Models\Recorrencia;

$uid = 1;
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

function limpar(int $uid): void
{
    $db = Db::con();

    $db->prepare("DELETE FROM recorrencias WHERE usuario_id = :u AND nome LIKE 'TESTE-CAT%'")->execute([':u' => $uid]);
    $db->prepare("DELETE FROM categorias WHERE usuario_id = :u AND nome LIKE 'TESTE-CAT%'")->execute([':u' => $uid]);
}

limpar($uid);

echo "== Setters e validação ==\n";

$vazia = new Categoria(0, $uid);
lanca('nome só com espaços', fn() => $vazia->nome = '   ', 'InvalidArgumentException');
lanca('nome com mais de 100 caracteres', fn() => $vazia->nome = str_repeat('a', 101), 'InvalidArgumentException');
lanca('tipo desconhecido', fn() => $vazia->tipo = 'outro', 'InvalidArgumentException');
lanca('salvar sem preencher nada', fn() => (new Categoria(0, $uid))->salvar(), 'InvalidArgumentException');
lanca('construir com id inexistente', fn() => new Categoria(999999, $uid), 'InvalidArgumentException');

echo "\n== Criar, editar e listar ==\n";

$c = new Categoria(0, $uid);
$c->nome = 'TESTE-CAT';
$c->tipo = 'despesa';
conferir('criar', $c->salvar(), true);
conferir('salvar preenche o id', $c->id !== null, true);

$c->nome = 'TESTE-CAT 2';
$c->tipo = 'receita';
conferir('editar', $c->salvar(), true);

$recarregada = new Categoria($c->id, $uid);
conferir('nome gravado', $recarregada->nome, 'TESTE-CAT 2');
conferir('tipo gravado', $recarregada->tipo, 'receita');

$achadas = array_filter(Categoria::listarPorUsuario($uid), fn(Categoria $x) => $x->id === $c->id);
conferir('aparece na listagem', count($achadas), 1);

echo "\n== Apagar: quem usava fica sem categoria ==\n";

$r = new Recorrencia(0, $uid);
$r->nome = 'TESTE-CAT-REC';
$r->periodicidade = 'mensal';
$r->dataInicio = '2026-11-05';
$r->categoriaId = $c->id;
$r->definirValor('despesa', 10);
conferir('recorrência com a categoria', $r->salvar(), true);
conferir('categoria vinculada', Recorrencia::buscarPorId($r->id, $uid)->categoriaId, $c->id);

conferir('apagar a categoria', $c->apagar(), true);
conferir('categoria sumiu', Categoria::buscarPorId($c->id, $uid), null);
conferir('recorrência ficou sem categoria (SET NULL)', Recorrencia::buscarPorId($r->id, $uid)->categoriaId, null);
conferir('apagar de novo não quebra', $c->apagar(), true);

limpar($uid);

echo $falhas === 0
    ? "\nTudo certo.\n"
    : "\n{$falhas} verificação(ões) falharam.\n";

exit($falhas === 0 ? 0 : 1);
