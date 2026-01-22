<?php
// public/index.php

// 1. Importa o arquivo da classe manualmente
require_once __DIR__ . '/../core/Autoloader.php';

// 2. Chama o método register para "ligar" o motor
// Passamos o prefixo 'App\\' e o caminho da pasta onde as classes estão
App\Core\Autoloader::register('App\\', __DIR__ . '/../src/');

// 3. Agora o PHP já sabe onde as coisas estão.
// Você já pode chamar o Bootstrap e o Router sem dar erro de "Class not found"
require_once __DIR__ . '/../core/bootstrap.php';