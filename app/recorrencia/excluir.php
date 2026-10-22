<?php

require_once "../../core/bootstrap.php";

use Models\Recorrencia;
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

$id = is_scalar($dados['id'] ?? null) ? (int) $dados['id'] : 0;

try {
    $recorrencia = Recorrencia::buscarPorId($id, $usuario->id);

    if ($recorrencia === null) {
        Functions::printJson(['success' => false, 'message' => 'Recorrência não encontrada.']);
        exit;
    }

    // apagar() devolve false quando já existem eventos em meses fechados (FK RESTRICT)
    if (!$recorrencia->apagar()) {
        Functions::printJson([
            'success' => false,
            'message' => 'Esta recorrência já gerou eventos em meses fechados e não pode ser apagada.'
        ]);
        exit;
    }

    Functions::printJson(['success' => true, 'message' => 'Recorrência apagada.']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Functions::printJson(['success' => false, 'message' => 'Erro ao apagar a recorrência.']);
}
