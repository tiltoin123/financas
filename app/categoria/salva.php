<?php

require_once "../../core/bootstrap.php";

use Models\Categoria;
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
    // 0 = categoria nova; qualquer outro valor precisa ser um inteiro positivo
    $id = filter_var($dados['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    if ($id === false) {
        throw new InvalidArgumentException('Categoria inválida.');
    }

    // Se o id não for de uma categoria do usuário logado, o construtor lança exceção
    $categoria = new Categoria($id, $usuario->id);

    // Cada atribuição é validada pela própria classe
    $categoria->nome = $campo('nome');
    $categoria->tipo = $campo('tipo');

    $categoria->salvar();

    Functions::printJson(['success' => true, 'message' => 'Categoria salva.']);
} catch (InvalidArgumentException $e) {
    Functions::printJson(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Functions::printJson(['success' => false, 'message' => 'Erro ao salvar a categoria.']);
}
