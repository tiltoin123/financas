<?php

namespace Models;

use Core\Db;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

class Evento
{
    public const TIPOS = ['receita', 'despesa'];

    // ---------- Propriedades: cada atribuição é validada ----------

    /** null até o evento ser salvo. Só a própria classe escreve. */
    public private(set) ?int $id = null;

    public readonly int $usuarioId;

    /** Evento nunca troca de recorrência: só é definido em deRecorrencia() ou ao carregar do banco. */
    public private(set) int $recorrenciaId = 0;

    /** Existência e dono da categoria são conferidos em salvar() (dependem do banco). */
    public ?int $categoriaId = null;

    public string $nome = '' {
        set {
            $limpo = trim($value);

            if ($limpo === '' || mb_strlen($limpo) > 150) {
                throw new InvalidArgumentException('Informe um nome de até 150 caracteres.');
            }

            $this->nome = $limpo;
        }
    }

    public ?string $descricao = null {
        set {
            $limpa = $value === null ? null : trim($value);
            $limpa = $limpa === '' ? null : $limpa;

            if ($limpa !== null && mb_strlen($limpa) > 255) {
                throw new InvalidArgumentException('A descrição pode ter no máximo 255 caracteres.');
            }

            $this->descricao = $limpa;
        }
    }

    /** Com sinal (despesa negativa). Só definirValor() escreve aqui. */
    public private(set) float $valor = 0.0;

    public string $data = '' {
        set {
            self::validarData($value);
            $this->data = $value;
        }
    }

    // ---------- Construção ----------

    /**
     * $id = 0: evento novo (sem id até ser salvo; só nasce por deRecorrencia()).
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
            throw new InvalidArgumentException('Evento não encontrado.');
        }

        $this->id = $id;
        $this->preencher($linha);
    }

    /** Evento nascido de uma recorrência no fechamento do mês (data já calculada por dataNoMes). */
    public static function deRecorrencia(Recorrencia $recorrencia, string $data): self
    {
        $e = new self(0, $recorrencia->usuarioId);

        $e->recorrenciaId = (int) $recorrencia->id;
        $e->categoriaId = $recorrencia->categoriaId;
        $e->nome = $recorrencia->nome;
        $e->descricao = $recorrencia->descricao;
        $e->valor = $recorrencia->valor;
        $e->data = $data;

        return $e;
    }

    /**
     * Valor e tipo entram juntos porque o sinal depende do tipo.
     * Receita fica positiva e despesa negativa: o saldo é uma soma de valores.
     */
    public function definirValor(string $tipo, string|int|float $valor): void
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException('Escolha receita ou despesa.');
        }
        if (!is_numeric($valor)) {
            throw new InvalidArgumentException('Informe um valor numérico.');
        }

        $valor = round((float) $valor, 2);

        if ($valor < 0.01 || $valor > 99999999.99) {
            throw new InvalidArgumentException('Informe um valor entre 0,01 e 99.999.999,99.');
        }

        $this->valor = $tipo === 'despesa' ? -$valor : $valor;
    }

    // ---------- Utilitário de período ----------

    /** Primeiro e último dia de um mês 'YYYY-MM' => ['2026-02-01', '2026-02-28'] */
    public static function limitesDoMes(string $anoMes): array
    {
        $primeiro = DateTimeImmutable::createFromFormat('!Y-m-d', $anoMes . '-01');
        if (!$primeiro || $primeiro->format('Y-m') !== $anoMes) {
            throw new InvalidArgumentException('Mês inválido.');
        }

        return [
            $primeiro->format('Y-m-d'),
            $primeiro->modify('last day of this month')->format('Y-m-d')
        ];
    }

    // ---------- Consultas ----------

    /** Versão que devolve null em vez de lançar exceção quando não encontra. */
    public static function buscarPorId(int $id, int $usuarioId): ?self
    {
        $linha = self::buscarLinha($id, $usuarioId);

        return $linha ? self::deLinha($linha) : null;
    }

    /** @return self[] */
    public static function listarPorPeriodo(int $usuarioId, string $inicio, string $fim): array
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, categoria_id, recorrencia_id,
                       nome, descricao, valor, data
                FROM eventos
                WHERE usuario_id = :usuario_id
                  AND data BETWEEN :inicio AND :fim
                ORDER BY data ASC, id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':inicio' => $inicio,
            ':fim' => $fim
        ]);

        $lista = [];
        foreach ($stmt->fetchAll() as $linha) {
            $lista[] = self::deLinha($linha);
        }

        return $lista;
    }

    /** @return self[] */
    public static function listarPorMes(int $usuarioId, string $anoMes): array
    {
        [$inicio, $fim] = self::limitesDoMes($anoMes);
        return self::listarPorPeriodo($usuarioId, $inicio, $fim);
    }

    public static function somarPorPeriodo(int $usuarioId, string $inicio, string $fim): float
    {
        $db = Db::con();

        $sql = "SELECT COALESCE(SUM(valor), 0) AS total
                FROM eventos
                WHERE usuario_id = :usuario_id
                  AND data BETWEEN :inicio AND :fim";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':inicio' => $inicio,
            ':fim' => $fim
        ]);

        return (float) $stmt->fetchColumn();
    }

    public static function somarPorMes(int $usuarioId, string $anoMes): float
    {
        [$inicio, $fim] = self::limitesDoMes($anoMes);
        return self::somarPorPeriodo($usuarioId, $inicio, $fim);
    }

    /** Já existe evento dessa recorrência nesse mês? Evita materializar duas vezes. */
    public static function existeDaRecorrenciaNoMes(int $recorrenciaId, int $usuarioId, string $anoMes): bool
    {
        [$inicio, $fim] = self::limitesDoMes($anoMes);
        $db = Db::con();

        $sql = "SELECT 1
                FROM eventos
                WHERE recorrencia_id = :recorrencia_id
                  AND usuario_id = :usuario_id
                  AND data BETWEEN :inicio AND :fim
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':recorrencia_id' => $recorrenciaId,
            ':usuario_id' => $usuarioId,
            ':inicio' => $inicio,
            ':fim' => $fim
        ]);

        return (bool) $stmt->fetchColumn();
    }

    // ---------- Persistência ----------

    /**
     * Cria (sem id) ou edita (com id). Editar respeita o hardcap, só aceita data em mês
     * fechado e refaz os saldos em cascata. Lança InvalidArgumentException (dado inválido)
     * ou DomainException (regra de negócio).
     */
    public function salvar(): bool
    {
        $this->validar();

        return $this->id === null ? $this->criar() : $this->editar();
    }

    /** Só o fechamento cria eventos (via deRecorrencia); o saldo do mês é calculado por ele. */
    private function criar(): bool
    {
        $db = Db::con();

        $sql = "INSERT INTO eventos
                    (usuario_id, categoria_id, recorrencia_id, nome, descricao, valor, data)
                VALUES
                    (:usuario_id, :categoria_id, :recorrencia_id, :nome, :descricao, :valor, :data)";
        $stmt = $db->prepare($sql);

        $ok = $stmt->execute([
            ':usuario_id' => $this->usuarioId,
            ':categoria_id' => $this->categoriaId,
            ':recorrencia_id' => $this->recorrenciaId,
            ':nome' => $this->nome,
            ':descricao' => $this->descricao,
            ':valor' => $this->valor,
            ':data' => $this->data
        ]);

        if ($ok) {
            $this->id = (int) $db->lastInsertId();
        }

        return $ok;
    }

    private function editar(): bool
    {
        // Data salva no banco: o evento pode estar saindo de um mês para outro
        $atual = self::buscarLinha($this->id, $this->usuarioId);

        if ($atual === null) {
            throw new InvalidArgumentException('Evento não encontrado.');
        }

        $mesAntigo = substr($atual['data'], 0, 7);
        $mesNovo = substr($this->data, 0, 7);

        foreach (array_unique([$mesAntigo, $mesNovo]) as $mes) {
            if (!Hardcap::podeEditar($mes)) {
                throw new DomainException('Esse mês está fora da janela de edição.');
            }
        }

        // Evento é fato de mês fechado: em mês aberto o saldo não o enxergaria
        if (SaldoMensal::buscarPorMes($this->usuarioId, $mesNovo)?->fechado !== true) {
            throw new DomainException('A data do lançamento precisa continuar em um mês já fechado.');
        }

        return Fechamento::emTransacao(function () use ($mesAntigo, $mesNovo) {
            $db = Db::con();

            $sql = "UPDATE eventos
                    SET categoria_id = :categoria_id, nome = :nome, descricao = :descricao,
                        valor = :valor, data = :data
                    WHERE id = :id AND usuario_id = :usuario_id";
            $stmt = $db->prepare($sql);

            $ok = $stmt->execute([
                ':categoria_id' => $this->categoriaId,
                ':nome' => $this->nome,
                ':descricao' => $this->descricao,
                ':valor' => $this->valor,
                ':data' => $this->data,
                ':id' => $this->id,
                ':usuario_id' => $this->usuarioId
            ]);

            // Do menor dos dois meses: o saldo inicial dos seguintes depende dele
            Fechamento::recalcularDesde($this->usuarioId, min($mesAntigo, $mesNovo));

            return $ok;
        });
    }

    /** Respeita o hardcap e refaz os saldos em cascata. Retorna false se não foi salvo. */
    public function apagar(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $atual = self::buscarLinha($this->id, $this->usuarioId);

        if ($atual === null) {
            return false;
        }

        $mes = substr($atual['data'], 0, 7);

        if (!Hardcap::podeEditar($mes)) {
            throw new DomainException('Esse mês está fora da janela de edição.');
        }

        return Fechamento::emTransacao(function () use ($mes) {
            $db = Db::con();

            $sql = "DELETE FROM eventos
                    WHERE id = :id AND usuario_id = :usuario_id";
            $stmt = $db->prepare($sql);

            $ok = $stmt->execute([
                ':id' => $this->id,
                ':usuario_id' => $this->usuarioId
            ]);

            Fechamento::recalcularDesde($this->usuarioId, $mes);

            return $ok;
        });
    }

    // ---------- Internos ----------

    /** Linha do banco ou null. Sempre filtra por usuário. */
    private static function buscarLinha(int $id, int $usuarioId): ?array
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, categoria_id, recorrencia_id,
                       nome, descricao, valor, data
                FROM eventos
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
        $e = new self(0, (int) $linha['usuario_id']);
        $e->id = (int) $linha['id'];
        $e->preencher($linha);

        return $e;
    }

    /** Atribui os campos do banco pelos mesmos setters de qualquer outra atribuição. */
    private function preencher(array $linha): void
    {
        $valor = (float) $linha['valor'];

        $this->recorrenciaId = (int) $linha['recorrencia_id'];
        $this->categoriaId = $linha['categoria_id'] !== null ? (int) $linha['categoria_id'] : null;
        $this->nome = $linha['nome'];
        $this->descricao = $linha['descricao'];
        $this->definirValor($valor < 0 ? 'despesa' : 'receita', abs($valor));
        $this->data = $linha['data'];
    }

    private static function validarData(string $data): void
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$d || $d->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException('Data inválida.');
        }
    }

    /**
     * O que um setter isolado não enxerga: campos obrigatórios que ficaram nos valores
     * iniciais e a categoria (depende do banco). A recorrência de origem não precisa de
     * conferência: recorrenciaId só é definido a partir do banco ou de uma Recorrencia do mesmo usuário.
     */
    private function validar(): void
    {
        if ($this->recorrenciaId <= 0) {
            throw new InvalidArgumentException('Lançamento sem recorrência de origem.');
        }
        if ($this->nome === '') {
            throw new InvalidArgumentException('Informe um nome de até 150 caracteres.');
        }
        if ($this->valor === 0.0) {
            throw new InvalidArgumentException('Informe o valor e se é receita ou despesa.');
        }
        if ($this->data === '') {
            throw new InvalidArgumentException('Data inválida.');
        }

        if ($this->categoriaId !== null && Categoria::buscarPorId($this->categoriaId, $this->usuarioId) === null) {
            throw new InvalidArgumentException('Categoria inválida.');
        }
    }
}
