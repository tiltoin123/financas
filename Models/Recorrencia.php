<?php

namespace Models;

use Core\Db;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDOException;

class Recorrencia
{
    public const PERIODICIDADES = ['unica', 'mensal'];

    public function __construct(
        public readonly int $id,
        public readonly int $usuarioId,
        public ?int $categoriaId,
        public string $nome,
        public ?string $descricao,
        public float $valor,
        public private(set) string $dataInicio,
        public private(set) ?string $dataFim,
        public private(set) string $periodicidade,
        public private(set) int $diaDoMes
    ) {}

    // ---------- Regra de negócio ----------

    /**
     * Data em que esta recorrência cai no mês informado ('YYYY-MM'),
     * ou null se ela não está ativa naquele mês.
     * Se o mês não tem o dia (ex: 31 em abril), cai no último dia do mês.
     */
    public function dataNoMes(string $anoMes): ?string
    {
        $primeiro = new DateTimeImmutable($anoMes . '-01');
        $ultimoDia = (int) $primeiro->format('t');
        $dia = min($this->diaDoMes, $ultimoDia);

        $data = $anoMes . '-' . sprintf('%02d', $dia);

        if ($data < $this->dataInicio) {
            return null;
        }
        if ($this->dataFim !== null && $data > $this->dataFim) {
            return null;
        }

        return $data;
    }

    // ---------- Consultas ----------

    public static function buscarPorId(int $id, int $usuarioId): ?self
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, categoria_id, nome, descricao, valor,
                       data_inicio, data_fim, periodicidade, dia_do_mes
                FROM recorrencias
                WHERE id = :id AND usuario_id = :usuario_id
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':usuario_id' => $usuarioId
        ]);
        $data = $stmt->fetch();

        return $data ? self::deLinha($data) : null;
    }

    /** @return self[] */
    public static function listarPorUsuario(int $usuarioId): array
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, categoria_id, nome, descricao, valor,
                       data_inicio, data_fim, periodicidade, dia_do_mes
                FROM recorrencias
                WHERE usuario_id = :usuario_id
                ORDER BY data_inicio ASC, id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario_id' => $usuarioId]);

        $lista = [];
        foreach ($stmt->fetchAll() as $data) {
            $lista[] = self::deLinha($data);
        }

        return $lista;
    }

    // ---------- Persistência ----------

    public static function criar(
        int $usuarioId,
        ?int $categoriaId,
        string $nome,
        ?string $descricao,
        float $valor,
        string $dataInicio,
        ?string $dataFim,
        string $periodicidade
    ): self {
        [$dataFim, $diaDoMes] = self::normalizar($periodicidade, $dataInicio, $dataFim);
        self::validarCategoria($categoriaId, $usuarioId);
        self::validarContraFechamento($usuarioId, $dataInicio, $dataFim, null);

        $db = Db::con();

        $sql = "INSERT INTO recorrencias
                    (usuario_id, categoria_id, nome, descricao, valor,
                     data_inicio, data_fim, periodicidade, dia_do_mes)
                VALUES
                    (:usuario_id, :categoria_id, :nome, :descricao, :valor,
                     :data_inicio, :data_fim, :periodicidade, :dia_do_mes)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':categoria_id' => $categoriaId,
            ':nome' => trim($nome),
            ':descricao' => $descricao,
            ':valor' => $valor,
            ':data_inicio' => $dataInicio,
            ':data_fim' => $dataFim,
            ':periodicidade' => $periodicidade,
            ':dia_do_mes' => $diaDoMes
        ]);

        return new self(
            (int) $db->lastInsertId(),
            $usuarioId,
            $categoriaId,
            trim($nome),
            $descricao,
            $valor,
            $dataInicio,
            $dataFim,
            $periodicidade,
            $diaDoMes
        );
    }

    public function atualizar(
        ?int $categoriaId,
        string $nome,
        ?string $descricao,
        float $valor,
        string $dataInicio,
        ?string $dataFim,
        string $periodicidade
    ): bool {
        [$dataFim, $diaDoMes] = self::normalizar($periodicidade, $dataInicio, $dataFim);
        self::validarCategoria($categoriaId, $this->usuarioId);
        self::validarContraFechamento($this->usuarioId, $dataInicio, $dataFim, $this);

        $db = Db::con();

        $sql = "UPDATE recorrencias
                SET categoria_id = :categoria_id, nome = :nome, descricao = :descricao,
                    valor = :valor, data_inicio = :data_inicio, data_fim = :data_fim,
                    periodicidade = :periodicidade, dia_do_mes = :dia_do_mes
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);
        $sucesso = $stmt->execute([
            ':categoria_id' => $categoriaId,
            ':nome' => trim($nome),
            ':descricao' => $descricao,
            ':valor' => $valor,
            ':data_inicio' => $dataInicio,
            ':data_fim' => $dataFim,
            ':periodicidade' => $periodicidade,
            ':dia_do_mes' => $diaDoMes,
            ':id' => $this->id,
            ':usuario_id' => $this->usuarioId
        ]);

        if ($sucesso) {
            $this->categoriaId = $categoriaId;
            $this->nome = trim($nome);
            $this->descricao = $descricao;
            $this->valor = $valor;
            $this->dataInicio = $dataInicio;
            $this->dataFim = $dataFim;
            $this->periodicidade = $periodicidade;
            $this->diaDoMes = $diaDoMes;
        }

        return $sucesso;
    }

    /** Retorna false se já existem eventos materializados (FK RESTRICT). */
    public function apagar(): bool
    {
        $db = Db::con();

        $sql = "DELETE FROM recorrencias
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);

        try {
            return $stmt->execute([
                ':id' => $this->id,
                ':usuario_id' => $this->usuarioId
            ]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                return false;
            }
            throw $e;
        }
    }

    // ---------- Internos ----------

    private static function deLinha(array $d): self
    {
        return new self(
            (int) $d['id'],
            (int) $d['usuario_id'],
            $d['categoria_id'] !== null ? (int) $d['categoria_id'] : null,
            $d['nome'],
            $d['descricao'],
            (float) $d['valor'],
            $d['data_inicio'],
            $d['data_fim'],
            $d['periodicidade'],
            (int) $d['dia_do_mes']
        );
    }

    private static function validarData(string $data, string $mensagem): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$d || $d->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException($mensagem);
        }

        return $d;
    }

    /** Valida e deriva data_fim e dia_do_mes conforme as regras do modelo. */
    private static function normalizar(string $periodicidade, string $dataInicio, ?string $dataFim): array
    {
        if (!in_array($periodicidade, self::PERIODICIDADES, true)) {
            throw new InvalidArgumentException('Periodicidade inválida.');
        }

        $inicio = self::validarData($dataInicio, 'Data de início inválida.');

        if ($periodicidade === 'unica') {
            $dataFim = $dataInicio;
        } elseif ($dataFim !== null) {
            self::validarData($dataFim, 'Data final inválida.');

            if ($dataFim < $dataInicio) {
                throw new InvalidArgumentException('A data final não pode ser anterior à inicial.');
            }
        }

        return [$dataFim, (int) $inicio->format('j')];
    }

    /** Impede usar categoria de outro usuário. */
    private static function validarCategoria(?int $categoriaId, int $usuarioId): void
    {
        if ($categoriaId !== null && Categoria::buscarPorId($categoriaId, $usuarioId) === null) {
            throw new InvalidArgumentException('Categoria inválida.');
        }
    }

    /**
     * Regra "mês fechado é fato": a recorrência não pode passar a cobrir
     * um mês que já foi fechado, porque ninguém materializaria o evento.
     * Também respeita o hardcap na data de início.
     * $atual é null na criação.
     */
    private static function validarContraFechamento(
        int $usuarioId,
        string $dataInicio,
        ?string $dataFim,
        ?self $atual
    ): void {
        $inicioMudou = $atual === null || $dataInicio !== $atual->dataInicio;
        $mesInicio = substr($dataInicio, 0, 7);

        if ($inicioMudou && !Hardcap::podeEditar($mesInicio)) {
            throw new DomainException('A data de início está fora da janela de edição.');
        }

        $ultimo = SaldoMensal::ultimoFechado($usuarioId)?->anoMes;
        if ($ultimo === null) {
            return;
        }

        // Início novo ou alterado caindo em mês já fechado
        if ($inicioMudou && $mesInicio <= $ultimo) {
            throw new DomainException('O mês de início já está fechado.');
        }

        // Fim estendido para frente, alcançando meses que já fecharam sem essa recorrência
        if ($atual !== null && $atual->dataFim !== null) {
            $estendeu = $dataFim === null || $dataFim > $atual->dataFim;

            if ($estendeu && substr($atual->dataFim, 0, 7) <= $ultimo) {
                throw new DomainException('Não é possível estender a recorrência para meses já fechados.');
            }
        }
    }
}
