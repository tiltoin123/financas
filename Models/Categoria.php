<?php

namespace Models;

use Core\Db;
use Functions;

class Categoria
{
    private int $id;
    private int $usuarioId;
    private string $nome;
    private string $tipo;

    public function __construct(
        int $id,
        int $usuarioId,
        string $nome,
        string $tipo
    ) {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->nome = $nome;
        $this->tipo = $tipo;
    }

    // ---------- Getters ----------

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    // ---------- Consultas ----------

    public static function buscarPorId(int $id, int $usuarioId): ?self
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
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return new self(
            (int) $data['id'],
            (int) $data['usuario_id'],
            $data['nome'],
            $data['tipo']
        );
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

        $categorias = [];
        foreach ($stmt->fetchAll() as $data) {
            $categorias[] = new self(
                (int) $data['id'],
                (int) $data['usuario_id'],
                $data['nome'],
                $data['tipo']
            );
        }

        return $categorias;
    }

    // ---------- Persistência ----------

    public static function criar(int $usuarioId, string $nome, string $tipo): self
    {
        $db = Db::con();

        $sql = "INSERT INTO categorias (usuario_id, nome, tipo)
                VALUES (:usuario_id, :nome, :tipo)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':nome' => trim($nome),
            ':tipo' => $tipo
        ]);

        $id = (int) $db->lastInsertId();

        return new self($id, $usuarioId, trim($nome), $tipo);
    }

    public function atualizar(string $nome, string $tipo): bool
    {
        $db = Db::con();

        $sql = "UPDATE categorias
                SET nome = :nome, tipo = :tipo
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);
        $sucesso = $stmt->execute([
            ':nome' => trim($nome),
            ':tipo' => $tipo,
            ':id' => $this->id,
            ':usuario_id' => $this->usuarioId
        ]);

        if ($sucesso) {
            $this->nome = trim($nome);
            $this->tipo = $tipo;
        }

        return $sucesso;
    }

    public function apagar(): bool
    {
        $db = Db::con();

        $sql = "DELETE FROM categorias
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            ':id' => $this->id,
            ':usuario_id' => $this->usuarioId
        ]);
    }
}
