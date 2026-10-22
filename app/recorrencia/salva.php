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

// Texto do JSON; qualquer coisa que não seja valor simples vira string vazia
$campo = fn(string $nome): string => is_scalar($dados[$nome] ?? null) ? trim((string) $dados[$nome]) : '';

try {
    // 0 = recorrência nova; qualquer outro valor precisa ser um inteiro positivo
    $id = filter_var($dados['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    if ($id === false) {
        throw new InvalidArgumentException('Recorrência inválida.');
    }

    // 0 devolve uma recorrência nova; um id que não seja do usuário logado lança exceção
    $recorrencia = new Recorrencia($id, $usuario->id);

    // Cada atribuição é validada pela própria classe
    $recorrencia->nome          = $campo('nome');
    $recorrencia->descricao     = $campo('descricao');
    $recorrencia->categoriaId   = $campo('categoria_id') !== '' ? (int) $campo('categoria_id') : null;
    $recorrencia->periodicidade = $campo('periodicidade');
    $recorrencia->dataInicio    = $campo('data_inicio');
    $recorrencia->dataFim       = $campo('data_fim') !== '' ? $campo('data_fim') : null;
    $recorrencia->definirValor($campo('tipo'), $campo('valor'));

    $recorrencia->salvar();

    Functions::printJson(['success' => true, 'message' => 'Recorrência salva.']);
} catch (InvalidArgumentException | DomainException $e) {
    // Dado inválido, id que não é do usuário, mês fechado, fora do hardcap
    Functions::printJson(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Functions::printJson(['success' => false, 'message' => 'Erro ao salvar a recorrência.']);
}
