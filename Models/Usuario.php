<?php

namespace App\Models;

use App\Core\Db;

class Usuario
{

    public static function salvar(string $nome, string $email, string $senha): bool
    {
        $db = Db::con();

        $senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

        $sql = "INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            ':nome'  => $nome,
            ':email' => $email,
            ':senha' => $senhaHash
        ]);
    }

    public static function autenticar(string $email, string $senha)
    {
        $db = Db::con();

        $sql = "SELECT * FROM usuarios WHERE email = :email LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario->senha)) {
            return $usuario;
        }

        return false;
    }
}
