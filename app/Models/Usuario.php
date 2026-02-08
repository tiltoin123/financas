<?php

namespace App\Models;

use App\Core\Db;

class Usuario
{

    // Método para salvar (Criar ou Alterar)
    public static function salvar(string $nome, string $email, string $senha): bool
    {
        $db = Db::con();

        // CRIPTOGRAFIA: O custo '12' é um bom equilíbrio entre segurança e performance
        $senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

        $sql = "INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            ':nome'  => $nome,
            ':email' => $email,
            ':senha' => $senhaHash
        ]);
    }

    // VERIFICAR CREDENCIAIS
    public static function autenticar(string $email, string $senha)
    {
        $db = Db::con();

        $sql = "SELECT * FROM usuarios WHERE email = :email LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        // Se o usuário existe, comparamos a senha digitada com o Hash do banco
        if ($usuario && password_verify($senha, $usuario->senha)) {
            return $usuario; // Sucesso
        }

        return false; // Credenciais inválidas
    }
}
