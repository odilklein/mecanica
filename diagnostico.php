<?php
// =============================================
// DIAGNÓSTICO TEMPORÁRIO - APAGUE APÓS O USO
// Acesse: /diagnostico.php?token=diag-9c4f1b7a
// Não exibe senhas.
// =============================================

const DIAG_TOKEN = 'diag-9c4f1b7a';

if (($_GET['token'] ?? '') !== DIAG_TOKEN) {
    http_response_code(404);
    exit('404');
}

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/config/env.php';

echo "== Ambiente ==\n";
echo "PHP: " . PHP_VERSION . " (" . PHP_SAPI . ")\n";
echo "Dir do arquivo: " . __DIR__ . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? '(vazio)') . "\n";
echo "open_basedir: " . (ini_get('open_basedir') ?: '(sem restricao)') . "\n";
echo "putenv: " . (function_exists('putenv') ? 'existe' : 'AUSENTE (bloqueado)') . "\n";
echo "pdo_mysql: " . (extension_loaded('pdo_mysql') ? 'ok' : 'AUSENTE') . "\n\n";

echo "== Busca pelo .env ==\n";
$cands = [
    __DIR__ . '/.env',
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    __DIR__ . '/../../../.env',
];
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $d = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $cands[] = $d . '/.env';
    $cands[] = dirname($d) . '/.env';
}
foreach (array_unique($cands) as $c) {
    $existe = is_file($c);
    echo ($existe ? '[OK]  ' : '[nao] ') . $c
        . ($existe ? (is_readable($c) ? ' (legivel)' : ' (SEM leitura)') : '')
        . "\n";
}
echo "Carregado: " . var_export(defined('MECANICA_ENV_ARQUIVO') ? MECANICA_ENV_ARQUIVO : null, true) . "\n";
echo "Chaves lidas do .env: " . count($GLOBALS['_MECANICA_ENV'] ?? []) . "\n";
echo "config.local.php: " . (is_file(__DIR__ . '/config/config.local.php') ? 'EXISTE (tem prioridade)' : 'nao existe') . "\n\n";

echo "== Valores resolvidos (sem senha) ==\n";
$host = config_valor('DB_HOST', 'localhost');
$port = config_valor('DB_PORT', '3306');
$user = config_valor('DB_USER', 'root');
$pass = config_valor('DB_PASS', '');
$name = config_valor('DB_NAME', 'mecanica');
echo "DB_HOST=$host\nDB_PORT=$port\nDB_USER=$user\nDB_NAME=$name\n";
echo "DB_PASS: " . ($pass === '' ? '(vazio)' : '(definido)') . "\n\n";

echo "== Tentativa de conexao ==\n";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $c = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    echo "SUCESSO: conectou em $host/$name\n";
} catch (Throwable $e) {
    echo "FALHA: " . $e->getMessage() . "\n";
}

echo "\n== Turnstile ==\n";
echo "site key: " . (trim((string) config_valor('TURNSTILE_SITE_KEY')) !== '' ? 'definida' : 'AUSENTE') . "\n";
echo "secret: " . (trim((string) config_valor('TURNSTILE_SECRET')) !== '' ? 'definido' : 'AUSENTE') . "\n";
echo "\nAPAGUE este arquivo (diagnostico.php) apos o uso.\n";
