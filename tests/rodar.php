<?php

declare(strict_types=1);

// php tests/rodar.php — roda sem banco e sem Composer.
require_once __DIR__ . '/../Models/Recorrencia.php';
require_once __DIR__ . '/../core/Csrf.php';

use Models\Recorrencia;
use Core\Csrf;

$ok = 0;
$falhas = 0;
function verificar(string $cenario, mixed $esperado, mixed $recebido): void {
    global $ok, $falhas;
    if ($esperado === $recebido) {
        $ok++;
        echo "OK: {$cenario}\n";
    } else {
        $falhas++;
        echo "FALHOU: {$cenario}; esperado=" . var_export($esperado, true)
             . "; recebido=" . var_export($recebido, true) . "\n";
    }
}
function rejeita(string $cenario, callable $fn): void {
    global $ok, $falhas;
    try {
        $fn();
        $falhas++;
        echo "FALHOU: {$cenario}; deveria rejeitar\n";
    } catch (InvalidArgumentException $e) {
        $ok++;
        echo "OK: {$cenario}\n";
    }
}

$r = new Recorrencia(0, 1);
$r->dataInicio = '2024-01-31';
$r->periodicidade = 'mensal';
verificar('fevereiro bissexto dia 31', '2024-02-29', $r->dataNoMes('2024-02'));
verificar('abril dia 31', '2024-04-30', $r->dataNoMes('2024-04'));
verificar('maio dia 31', '2024-05-31', $r->dataNoMes('2024-05'));
verificar('fevereiro não bissexto dia 31', '2025-02-28', $r->dataNoMes('2025-02'));
$r->definirValor('receita', '10.456');
verificar('arredondamento para duas casas', 10.46, $r->valor);
$r->definirValor('despesa', '99.99');
verificar('despesa negativa', -99.99, $r->valor);
rejeita('valor zero', fn() => $r->definirValor('receita', '0'));
rejeita('valor fora do teto', fn() => $r->definirValor('receita', '99999999.995'));
rejeita('tipo inválido', fn() => $r->definirValor('transferencia', '10'));
rejeita('valor não numérico', fn() => $r->definirValor('receita', 'abc'));

$_SESSION = [];
$token = Csrf::token();
verificar('token CSRF hex 64', true, (bool)preg_match('/^[0-9a-f]{64}$/', $token));
verificar('token CSRF válido', true, Csrf::validar($token));
verificar('token CSRF ausente rejeitado', false, Csrf::validar(null));
verificar('token CSRF inválido rejeitado', false, Csrf::validar(str_repeat('0', 64)));

printf("Resultado: %d OK; %d falhas\n", $ok, $falhas);
exit($falhas ? 1 : 0);
