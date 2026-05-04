<?php

namespace App\Models;

use App\Core\Db;

class Usuario
{

    public ?int $id;
    private string $nome;
    private string $email;
    private string $senha;

    public function __construct(?int $id, string $nome, string $email, string $senha)
    {
        $this->id = $id;
        $this->setNome($nome);
        $this->setEmail($email);
        $this->senha = $senha;
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
}
