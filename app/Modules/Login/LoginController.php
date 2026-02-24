<?php

namespace App\Controllers;

use App\Core\Http\Response;
use App\Core\Http\Session;
use App\Models\Usuario;

class LoginController
{

    /**
     * Mostra o formulário de login (GET)
     */
    public function index()
    {
        // Se o cara já estiver logado, manda direto pro dashboard
        if (Session::has('usuario_id')) {
            return Response::redirect('/dashboard');
        }

        return Response::view('login');
    }

    /**
     * Processa a tentativa de login (POST)
     */
    public function login()
    {
        // 1. Pega os dados do formulário
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';

        // 2. Validação simples de campos vazios
        if (empty($email) || empty($senha)) {
            return Response::view('login', [
                'erro' => 'Preencha todos os campos.'
            ]);
        }

        // 3. Verifica no Model se as credenciais batem
        $usuario = Usuario::autenticar($email, $senha);

        if ($usuario) {
            // LOGIN SUCESSO: Salva na sessão usando seu wrapper
            Session::set('usuario_id', $usuario->id);
            Session::set('usuario_nome', $usuario->nome);
            Session::set('usuario_email', $usuario->email);

            // Regenera ID para evitar sequestro de sessão
            Session::flash();

            return Response::redirect('/dashboard');
        }

        // LOGIN FALHOU: Volta com mensagem de erro
        return Response::view('login', [
            'erro' => 'E-mail ou senha incorretos.',
            'email_digitado' => $email // Para o usuário não ter que digitar o e-mail de novo
        ]);
    }

    /**
     * Finaliza a sessão (Logout)
     */
    public function logout()
    {
        Session::destroy();
        return Response::redirect('/login');
    }
}
