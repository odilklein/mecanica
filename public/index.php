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

// Em produção não exibe erros na resposta (vão para o log). Em dev,
// basta definir APP_DEBUG=true no .env/ambiente.
error_reporting(E_ALL);
$appDebug = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');

// Inicializa e despacha a aplicação.
$kernel = new Kernel($basePath);
$kernel->boot();
$kernel->run();
