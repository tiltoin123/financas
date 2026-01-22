<?php
// core/bootstrap.php

// 1. Definição de constantes globais de caminho
const DS = DIRECTORY_SEPARATOR;
define('BASE_PATH', dirname(__DIR__) . DS);
const APP_PATH = BASE_PATH . 'app' . DS;
const CORE_PATH = BASE_PATH . 'core' . DS;

// 2. Carregar variáveis de ambiente através da Classe Config
// Usando o nome que você escolheu: loadDotEnv
App\Core\Config::loadDotEnv(BASE_PATH . '.env');

// 3. Configurações de Erro baseadas no .env
// Usamos o Config::get para evitar erros caso a chave não exista
if (App\Core\Config::get('APP_DEBUG') === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// 4. Iniciar Sessão com segurança
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 5. Configuração de fuso horário
date_default_timezone_set(App\Core\Config::get('APP_TIMEZONE', 'America/Sao_Paulo'));

// 6. Carregar funções auxiliares (Helpers) se existirem
if (file_exists(CORE_PATH . 'Functions.php')) {
    require_once CORE_PATH . 'Functions.php';
}