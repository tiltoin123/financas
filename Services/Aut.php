<?php

namespace Services;

use Models\Usuario;

class Auth
{
    public static function login(string $email, string $senha): bool
    {
        $usuario = Usuario::buscarPorEmail($email);

        if (!$usuario) {
            return false;
        }

        if (!$usuario->verificarSenha($senha)) {
            return false;
        }

        $_SESSION['user_id'] = $usuario->id;

        return true;
    }

    public static function user(): ?Usuario
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return Usuario::buscarPorId($_SESSION['user_id']);
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
    }
}
