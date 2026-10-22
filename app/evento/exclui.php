<?php

require_once "../../core/bootstrap.php";

use Models\Evento;
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

try {
    $id = filter_var($dados['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($id === false) {
        throw new InvalidArgumentException('Lançamento inválido.');
    }

    // Se não for de um evento do usuário logado, o construtor lança exceção
    $evento = new Evento($id, $usuario->id);

    // Hardcap e recálculo em cascata ficam dentro de apagar()
    if (!$evento->apagar()) {
        Functions::printJson(['success' => false, 'message' => 'Não foi possível apagar o lançamento.']);
        exit;
    }

    Functions::printJson(['success' => true, 'message' => 'Lançamento apagado.']);
} catch (InvalidArgumentException | DomainException $e) {
    Functions::printJson(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Functions::printJson(['success' => false, 'message' => 'Erro ao apagar o lançamento.']);
}
