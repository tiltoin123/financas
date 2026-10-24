<?php

namespace Models;

use Core\Db;
use InvalidArgumentException;

class Categoria
{
    public const TIPOS = ['receita', 'despesa'];

    /** Conjunto inicial criado no cadastro da conta: [nome, tipo]. */
    public const PADRAO = [
        // Despesas
        ['Moradia', 'despesa'],               // aluguel, condomínio, luz, água, internet
        ['Mercado', 'despesa'],
        ['Restaurantes e delivery', 'despesa'],
        ['Transporte', 'despesa'],            // combustível, app, transporte público
        ['Saúde', 'despesa'],
        ['Educação', 'despesa'],
        ['Lazer', 'despesa'],
        ['Assinaturas', 'despesa'],           // streaming, apps, academia
        ['Compras', 'despesa'],               // roupas, eletrônicos, casa
        ['Dívidas e parcelas', 'despesa'],    // financiamento, empréstimo, cartão parcelado
        ['Impostos e taxas', 'despesa'],
        ['Outras despesas', 'despesa'],

        // Receitas
        ['Salário', 'receita'],
        ['Renda extra', 'receita'],           // freelas, bicos, vendas
        ['Rendimentos', 'receita'],           // investimentos, juros
        ['Outras receitas', 'receita'],
    ];

    // ---------- Propriedades: cada atribuição é validada ----------

    /** null até a categoria ser salva. Só a própria classe escreve. */
    public private(set) ?int $id = null;

    public readonly int $usuarioId;

    public string $nome = '' {
        set {
            $limpo = trim($value);

            if ($limpo === '' || mb_strlen($limpo) > 100) {
                throw new InvalidArgumentException('Informe um nome de até 100 caracteres.');
            }

            $this->nome = $limpo;
        }
    }

    public string $tipo = '' {
        set {
            if (!in_array($value, self::TIPOS, true)) {
                throw new InvalidArgumentException('Escolha receita ou despesa.');
            }

            $this->tipo = $value;
        }
    }

    // ---------- Construção ----------

    /**
     * $id = 0: categoria nova (sem id até ser salva).
     * $id > 0: carrega do banco; precisa ser do usuário, senão lança InvalidArgumentException.
     */
    public function __construct(int $id, int $usuarioId)
    {
        $this->usuarioId = $usuarioId;

        if ($id === 0) {
            return;
        }

        $linha = self::buscarLinha($id, $usuarioId);

        if ($linha === null) {
            throw new InvalidArgumentException('Categoria não encontrada.');
        }

        $this->id = $id;
        $this->preencher($linha);
    }

    // ---------- Consultas ----------

    /** Versão que devolve null em vez de lançar exceção quando não encontra. */
    public static function buscarPorId(int $id, int $usuarioId): ?self
    {
        $linha = self::buscarLinha($id, $usuarioId);

        return $linha ? self::deLinha($linha) : null;
    }

    /** @return self[] */
    public static function listarPorUsuario(int $usuarioId): array
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, nome, tipo
                FROM categorias
                WHERE usuario_id = :usuario_id
                ORDER BY nome ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario_id' => $usuarioId]);

        $lista = [];
        foreach ($stmt->fetchAll() as $linha) {
            $lista[] = self::deLinha($linha);
        }

        return $lista;
    }

    // ---------- Persistência ----------

    /** Cria (sem id) ou edita (com id). Lança InvalidArgumentException se faltar dado. */
    public function salvar(): bool
    {
        $this->validar();

        return $this->id === null ? $this->criar() : $this->editar();
    }

    private function criar(): bool
    {
        $db = Db::con();

        $sql = "INSERT INTO categorias (usuario_id, nome, tipo)
                VALUES (:usuario_id, :nome, :tipo)";
        $stmt = $db->prepare($sql);

        $ok = $stmt->execute([
            ':usuario_id' => $this->usuarioId,
            ':nome' => $this->nome,
            ':tipo' => $this->tipo
        ]);

        if ($ok) {
            $this->id = (int) $db->lastInsertId();
        }

        return $ok;
    }

    private function editar(): bool
    {
        // Pode ter sido apagada em outra aba
        if (self::buscarLinha($this->id, $this->usuarioId) === null) {
            throw new InvalidArgumentException('Categoria não encontrada.');
        }

        $db = Db::con();

        $sql = "UPDATE categorias
                SET nome = :nome, tipo = :tipo
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            ':nome' => $this->nome,
            ':tipo' => $this->tipo,
            ':id' => $this->id,
            ':usuario_id' => $this->usuarioId
        ]);
    }

    /**
     * Apaga a categoria. Recorrências e eventos que a usavam ficam sem categoria:
     * quem faz isso é o banco (ON DELETE SET NULL). Retorna false se não foi salva.
     */
    public function apagar(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $db = Db::con();

        $sql = "DELETE FROM categorias
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            ':id' => $this->id,
            ':usuario_id' => $this->usuarioId
        ]);
    }

    /** Cria o conjunto inicial de categorias para um usuário novo (chamado no cadastro). */
    public static function criarPadrao(int $usuarioId): void
    {
        $db = Db::con();

        $sql = "INSERT INTO categorias (usuario_id, nome, tipo)
                VALUES (:usuario_id, :nome, :tipo)";
        $stmt = $db->prepare($sql);

        foreach (self::PADRAO as [$nome, $tipo]) {
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':nome' => $nome,
                ':tipo' => $tipo
            ]);
        }
    }

    // ---------- Internos ----------

    /** Linha do banco ou null. Sempre filtra por usuário. */
    private static function buscarLinha(int $id, int $usuarioId): ?array
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, nome, tipo
                FROM categorias
                WHERE id = :id AND usuario_id = :usuario_id
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':usuario_id' => $usuarioId
        ]);
        $linha = $stmt->fetch();

        return $linha ?: null;
    }

    /** Monta a instância a partir de uma linha já lida (sem nova consulta). */
    private static function deLinha(array $linha): self
    {
        $c = new self(0, (int) $linha['usuario_id']);
        $c->id = (int) $linha['id'];
        $c->preencher($linha);

        return $c;
    }

    private function preencher(array $linha): void
    {
        $this->nome = $linha['nome'];
        $this->tipo = $linha['tipo'];
    }

    /** Campos que ficaram nos valores iniciais (os setters só rodam quando há atribuição). */
    private function validar(): void
    {
        if ($this->nome === '') {
            throw new InvalidArgumentException('Informe um nome de até 100 caracteres.');
        }
        if ($this->tipo === '') {
            throw new InvalidArgumentException('Escolha receita ou despesa.');
        }
    }
}
