<?php

/**
 * Teste de integração SOMENTE LEITURA de isolamento dos modelos.
 * Execute apenas com DUAS CONTAS DE TESTE da sua aplicação:
 * php tests/testar_isolamento.php ID_CONTA_A ID_CONTA_B
 * Nunca modifica registros e não imprime dados pessoais.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

use Core\Db;
use Models\Categoria;
use Models\Evento;
use Models\Recorrencia;

if (PHP_SAPI !== 'cli') {
    exit("Execute apenas no terminal.\n");
}

if ($argc !== 3 || !ctype_digit($argv[1]) || !ctype_digit($argv[2])) {
    fwrite(STDERR, "Uso: php tests/testar_isolamento.php ID_CONTA_A ID_CONTA_B\n");
    exit(2);
}

$a = (int)$argv[1];
$b = (int)$argv[2];
if ($a <= 0 || $b <= 0 || $a === $b) {
    fwrite(STDERR, "Informe dois IDs diferentes de contas de teste.\n");
    exit(2);
}

$pdo = Db::con();
$verifica = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE id IN (?, ?)');
$verifica->execute([$a, $b]);
if ((int)$verifica->fetchColumn() !== 2) {
    fwrite(STDERR, "Ambas as contas de teste precisam existir.\n");
    exit(2);
}

$aprovados = 0;
$executados = 0;
$ignorados = 0;

function checar(string $nome, bool $condicao): void
{
    global $aprovados, $executados;
    $executados++;
    if ($condicao) {
        $aprovados++;
        echo "OK: {$nome}\n";
    } else {
        echo "FALHOU: {$nome}\n";
    }
}

$tipos = [
    'recorrencias' => [Recorrencia::class, 'buscarPorId'],
    'categorias' => [Categoria::class, 'buscarPorId'],
    'eventos' => [Evento::class, 'buscarPorId'],
];

foreach ($tipos as $tabela => [$classe, $metodo]) {
    $stmt = $pdo->prepare("SELECT id FROM {$tabela} WHERE usuario_id = ? ORDER BY id LIMIT 1");
    foreach ([[$a, $b], [$b, $a]] as [$dono, $outro]) {
        $stmt->execute([$dono]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            $ignorados++;
            echo "IGNORADO: {$tabela} — conta de teste sem registro\n";
            continue;
        }
        $id = (int)$id;
        checar("{$tabela} — dono consegue consultar", $classe::$metodo($id, $dono) !== null);
        checar("{$tabela} — outra conta não consegue consultar", $classe::$metodo($id, $outro) === null);
    }
}

// A listagem de recorrências também precisa ser limitada ao dono.
foreach ([$a, $b] as $dono) {
    $lista = Recorrencia::listarPorUsuario($dono);
    $somenteDoDono = true;
    foreach ($lista as $r) {
        if ($r->usuarioId !== $dono) {
            $somenteDoDono = false;
            break;
        }
    }
    checar('listagem de recorrências restrita à conta de teste', $somenteDoDono);
}

echo "Resultado: {$aprovados}/{$executados} OK; " . ($executados - $aprovados) . " falhas; {$ignorados} cenários ignorados\n";
echo "Escopo: busca por ID e listagem via modelos; sem testes HTTP, escrita ou alteração de dados.\n";
exit($executados === $aprovados && $executados > 2 ? 0 : 1);
