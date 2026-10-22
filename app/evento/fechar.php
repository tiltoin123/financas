<?php

require_once "../../core/bootstrap.php";

use Models\Fechamento;
use Services\Aut;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Functions::printJson(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$usuario = Aut::user();
if ($usuario === null) {
    Functions::printJson(['success' => false, 'message' => 'Sessão expirada. Recarregue a página.']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    Functions::printJson(['success' => false, 'message' => 'Requisição inválida.']);
    exit;
}

$mes = is_string($dados['mes'] ?? null) ? $dados['mes'] : '';

try {
    // Fecha o mês e todos os anteriores ainda abertos, em cadeia
    $fechados = Fechamento::fecharMes($usuario->id, $mes);

    Functions::printJson(['success' => true, 'message' => 'Meses fechados: ' . implode(', ', $fechados)]);
} catch (InvalidArgumentException | DomainException $e) {
    Functions::printJson(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Functions::printJson(['success' => false, 'message' => 'Erro ao fechar o mês.']);
}
