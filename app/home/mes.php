<?php

require_once "../../core/bootstrap.php";

use Models\Extrato;
use Models\Saldo;
use Services\Aut;

$usuario = Aut::user();
if ($usuario === null) {
    Functions::printJson(['success' => false, 'message' => 'Sessão expirada. Recarregue a página.']);
    exit;
}

$mes = is_string($_GET['mes'] ?? null) ? $_GET['mes'] : date('Y-m');

try {
    Functions::printJson([
        'success' => true,
        'saldoAtual' => Saldo::atual($usuario->id),
        'dados' => Extrato::doMes($usuario->id, $mes)
    ]);
} catch (InvalidArgumentException | DomainException $e) {
    Functions::printJson(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Functions::printJson(['success' => false, 'message' => 'Erro ao carregar o mês: ' . $e->getMessage()]);
}
