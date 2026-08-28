<?php

namespace Models;

use Core\Db;
use Functions;

class Usuario
{

    public ?int $id;
    private string $nome;
    private string $email;
    private ?string $senha;

    public function __construct(
        ?int $id,
        string $nome,
        string $email,
        string $senha,
        bool $senhaHash = false
    ) {
        $this->id = $id;
        $this->setNome($nome);
        $this->setEmail($email);
        $this->setSenha($senha, $senhaHash);
    }

    public function setNome(string $nome): void
    {
        // remove espaços nas pontas
        $nome = trim($nome);

        // valida (letras + acentos + espaço)
        if (!preg_match('/^[a-zA-ZÀ-ÿ\s]+$/u', $nome)) {
            throw new \InvalidArgumentException("Nome inválido");
        }

        $this->nome = $nome;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setEmail(string $email): void
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email inválido");
        }

        // opcional: normalizar
        $this->email = strtolower($email);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    private function validarSenha(string $senha): void
    {
        if (!preg_match(
            '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/',
            $senha
        )) {
            throw new \InvalidArgumentException(
                "Senha inválida. Deve ter no mínimo 8 caracteres, incluindo maiúscula, minúscula, número e caractere especial."
            );
        }
    }

    public function getSenha()
    {
        return $this->senha;
    }

    public function setSenha(?string $senha, bool $jaHasheada = false): void
    {
        if (!$senha) {
            $this->senha = null;
            return;
        }

        if ($jaHasheada) {
            $this->senha = $senha;
            return;
        }

        $this->validarSenha($senha);
        $this->senha = password_hash($senha, PASSWORD_DEFAULT);
    }

    public static function buscarPorEmail(string $email): ?self
    {
        $db = Db::con();

        $sql = "SELECT id, nome, email, senha 
                FROM usuarios 
                WHERE email = :email 
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':email' => strtolower(trim($email))
        ]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return new self(
            (int) $data['id'],
            $data['nome'],
            $data['email'],
            $data['senha'],
            true
        );
    }

    public static function buscarPorId(int $id): ?self
    {
        $db = Db::con();

        $sql = "SELECT id, nome, email, senha 
                FROM usuarios 
                WHERE id = :id 
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id' => $id
        ]);

        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return new self(
            (int) $data['id'],
            $data['nome'],
            $data['email'],
            $data['senha'],
            true
        );
    }

    public function verificarSenha(string $senha): bool
    {

        return password_verify($senha, $this->senha);
    }

    private function inserir(): bool
    {
        $db = Db::con();

        $sql = "INSERT INTO usuarios (nome, email, senha)
                VALUES (:nome, :email, :senha)";

        $stmt = $db->prepare($sql);

        $ok = $stmt->execute([
            ':nome'  => $this->nome,
            ':email' => $this->email,
            ':senha' => $this->senha
        ]);

        if ($ok) {
            $this->id = (int) $db->lastInsertId();
        }

        return $ok;
    }

    private function atualizar(): bool
    {
        $db = Db::con();

        $sql = "UPDATE usuarios
            SET nome = :nome,
                email = :email,
                senha = :senha
            WHERE id = :id";

        $stmt = $db->prepare($sql);

        return $stmt->execute([
            ':nome'  => $this->nome,
            ':email' => $this->email,
            ':senha' => $this->senha,
            ':id'    => $this->id
        ]);
    }

    public function salvar(): bool
    {
        if ($this->id === null) {
            return $this->inserir();
        }

        return $this->atualizar();
    }
}
