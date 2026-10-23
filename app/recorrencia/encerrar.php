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

try {
    $id = filter_var($dados['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($id === false) {
        throw new InvalidArgumentException('Recorrência inválida.');
    }

    $mes = is_string($dados['mes'] ?? null) ? $dados['mes'] : '';

    // Se não for de uma recorrência do usuário logado, o construtor lança exceção
    $recorrencia = new Recorrencia($id, $usuario->id);

    // A regra fica na classe: apaga se não sobra mês anterior; senão passa a terminar no mês anterior
    $resultado = $recorrencia->removerAPartirDe($mes);

    Functions::printJson([
        'success' => true,
        'message' => $resultado === 'apagada' ? 'Recorrência apagada.' : 'Recorrência encerrada no mês anterior.',
        'resultado' => $resultado
    ]);
} catch (InvalidArgumentException | DomainException $e) {
    Functions::printJson(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Functions::printJson(['success' => false, 'message' => 'Erro ao remover a recorrência.']);
}
