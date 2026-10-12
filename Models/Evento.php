<?php

namespace Models;

use Core\Db;
use Functions;
use DateTimeImmutable;
use InvalidArgumentException;
use PDOException;

class Evento
{
    public function __construct(
        public readonly int $id,
        public readonly int $usuarioId,
        public readonly int $recorrenciaId,
        public ?int $categoriaId,
        public string $nome,
        public ?string $descricao,
        public float $valor,
        public string $data
    ) {}

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

    public static function buscarPorId(int $id, int $usuarioId): ?self
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
        $data = $stmt->fetch();

        return $data ? self::deLinha($data) : null;
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

    public static function criar(
        int $usuarioId,
        int $recorrenciaId,
        ?int $categoriaId,
        string $nome,
        ?string $descricao,
        float $valor,
        string $data
    ): self {
        self::validarData($data);
        self::validarVinculos($recorrenciaId, $categoriaId, $usuarioId);

        $db = Db::con();

        $sql = "INSERT INTO eventos
                    (usuario_id, categoria_id, recorrencia_id, nome, descricao, valor, data)
                VALUES
                    (:usuario_id, :categoria_id, :recorrencia_id, :nome, :descricao, :valor, :data)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':categoria_id' => $categoriaId,
            ':recorrencia_id' => $recorrenciaId,
            ':nome' => trim($nome),
            ':descricao' => $descricao,
            ':valor' => $valor,
            ':data' => $data
        ]);

        return new self(
            (int) $db->lastInsertId(),
            $usuarioId,
            $recorrenciaId,
            $categoriaId,
            trim($nome),
            $descricao,
            $valor,
            $data
        );
    }

    public function atualizar(
        ?int $categoriaId,
        string $nome,
        ?string $descricao,
        float $valor,
        string $data
    ): bool {
        self::validarData($data);
        self::validarVinculos($this->recorrenciaId, $categoriaId, $this->usuarioId);

        $db = Db::con();

        $sql = "UPDATE eventos
                SET categoria_id = :categoria_id, nome = :nome, descricao = :descricao,
                    valor = :valor, data = :data
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);
        $sucesso = $stmt->execute([
            ':categoria_id' => $categoriaId,
            ':nome' => trim($nome),
            ':descricao' => $descricao,
            ':valor' => $valor,
            ':data' => $data,
            ':id' => $this->id,
            ':usuario_id' => $this->usuarioId
        ]);

        if ($sucesso) {
            $this->categoriaId = $categoriaId;
            $this->nome = trim($nome);
            $this->descricao = $descricao;
            $this->valor = $valor;
            $this->data = $data;
        }

        return $sucesso;
    }

    public function apagar(): bool
    {
        $db = Db::con();

        $sql = "DELETE FROM eventos
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            ':id' => $this->id,
            ':usuario_id' => $this->usuarioId
        ]);
    }

    // ---------- Internos ----------

    private static function deLinha(array $d): self
    {
        return new self(
            (int) $d['id'],
            (int) $d['usuario_id'],
            (int) $d['recorrencia_id'],
            $d['categoria_id'] !== null ? (int) $d['categoria_id'] : null,
            $d['nome'],
            $d['descricao'],
            (float) $d['valor'],
            $d['data']
        );
    }

    private static function validarData(string $data): void
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$d || $d->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException('Data inválida.');
        }
    }

    /** Impede vincular recorrência ou categoria de outro usuário. */
    private static function validarVinculos(int $recorrenciaId, ?int $categoriaId, int $usuarioId): void
    {
        if (Recorrencia::buscarPorId($recorrenciaId, $usuarioId) === null) {
            throw new InvalidArgumentException('Recorrência inválida.');
        }
        if ($categoriaId !== null && Categoria::buscarPorId($categoriaId, $usuarioId) === null) {
            throw new InvalidArgumentException('Categoria inválida.');
        }
    }
}
