<?php
// =============================================
// VARIÁVEIS DE AMBIENTE
// Precedência:
//   1. Variáveis do processo (ex.: Docker)  -> getenv()/$_ENV
//   2. config/config.local.php  (PHP puro, p/ hospedagem sem .env)
//   3. arquivo .env da raiz
//   4. Padrões definidos nos chamadores de env()
// Não depende de putenv() e funciona também em hospedagem compartilhada.
// =============================================

$GLOBALS['_MECANICA_ENV'] = $GLOBALS['_MECANICA_ENV'] ?? [];

if (!function_exists('env')) {
    function env(string $chave, ?string $padrao = null): ?string
    {
        $v = getenv($chave);
        if ($v !== false && $v !== '') {
            return $v;
        }
        if (isset($_ENV[$chave]) && $_ENV[$chave] !== '') {
            return $_ENV[$chave];
        }
        if (isset($GLOBALS['_MECANICA_ENV'][$chave]) && $GLOBALS['_MECANICA_ENV'][$chave] !== '') {
            return $GLOBALS['_MECANICA_ENV'][$chave];
        }
        return $padrao;
    }
}

if (!defined('MECANICA_ENV_CARREGADO')) {
    define('MECANICA_ENV_CARREGADO', true);

    // ---------- 1) Arquivo .env da raiz (prioridade mais baixa) ----------
    $arquivo_env = __DIR__ . '/../.env';
    $env_carregado = is_file($arquivo_env) && is_readable($arquivo_env);
    define('MECANICA_ENV_ARQUIVO', $env_carregado ? $arquivo_env : null);

    if ($env_carregado) {
        foreach (file($arquivo_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) {
                continue;
            }
            [$chave, $valor] = explode('=', $linha, 2);
            $GLOBALS['_MECANICA_ENV'][trim($chave)] = trim(trim($valor), " \t\r\n\"'");
        }
    }

    // ---------- 2) config.local.php (sobrescreve o .env) ----------
    // Ideal para hospedagem que não aceita .env: é um PHP que retorna um array.
    // Localizações aceitas: config/config.local.php ou config.local.php na raiz.
    $local = null;
    if (is_file(__DIR__ . '/config.local.php')) {
        $local = __DIR__ . '/config.local.php';
    } elseif (is_file(__DIR__ . '/../config.local.php')) {
        $local = __DIR__ . '/../config.local.php';
    }
    define('MECANICA_ENV_LOCAL_ARQUIVO', $local);

    if ($local) {
        $config_local = require $local;
        if (is_array($config_local)) {
            foreach ($config_local as $chave => $valor) {
                if ($valor === null || $valor === false) {
                    continue;
                }
                $GLOBALS['_MECANICA_ENV'][$chave] = (string) $valor;
            }
        }
    }
}
