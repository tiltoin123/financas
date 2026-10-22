<?php

namespace Services;

use Functions;
use Models\Usuario;

class Aut
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

        session_regenerate_id(true);

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
        return self::user() !== null;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }
    /** ID do usuário logado, lido da sessão (sem consultar o banco). Null se ninguém logou. */
    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /** Para rotas protegidas: devolve o ID ou manda para o login. */
    public static function exigir(): int
    {
        $id = self::id();

        if ($id === null) {
            header('Location: /login/index.php'); // ajuste para a rota real do seu login
            exit;
        }

        return $id;
    }
}
