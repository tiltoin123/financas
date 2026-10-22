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

// Texto do JSON; qualquer coisa que não seja valor simples vira string vazia
$campo = fn(string $nome): string => is_scalar($dados[$nome] ?? null) ? trim((string) $dados[$nome]) : '';

try {
    // O id precisa ser um inteiro; se não for de um evento do usuário logado, o construtor lança exceção.
    // Evento novo (id 0) não nasce por aqui: salvar() recusa por falta de recorrência de origem.
    $id = filter_var($dados['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    if ($id === false) {
        throw new InvalidArgumentException('Lançamento inválido.');
    }

    $evento = new Evento($id, $usuario->id);

    // Cada atribuição é validada pela própria classe
    $evento->nome        = $campo('nome');
    $evento->descricao   = $campo('descricao');
    $evento->categoriaId = $campo('categoria_id') !== '' ? (int) $campo('categoria_id') : null;
    $evento->data        = $campo('data');
    $evento->definirValor($campo('tipo'), $campo('valor'));

    // Hardcap, data em mês fechado e recálculo em cascata ficam dentro de salvar()
    $evento->salvar();

    Functions::printJson(['success' => true, 'message' => 'Lançamento salvo.']);
} catch (InvalidArgumentException | DomainException $e) {
    Functions::printJson(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Functions::printJson(['success' => false, 'message' => 'Erro ao salvar o lançamento.']);
}
