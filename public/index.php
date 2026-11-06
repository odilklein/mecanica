<?php
// =============================================
// FRONT CONTROLLER — ponto único de entrada da
// aplicação (toda requisição passa por aqui).
// =============================================

declare(strict_types=1);

use Kernel\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';

// Carrega as variáveis de ambiente (.env) se existir. Não falha sem ele.
$basePath = dirname(__DIR__);
if (is_file($basePath . '/.env')) {
    Dotenv\Dotenv::createImmutable($basePath)->safeLoad();
}

// Inicializa e despacha a aplicação.
$kernel = new Kernel($basePath);
$kernel->boot();
$kernel->run();
