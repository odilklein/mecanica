<?php
// =============================================
// VARIÁVEIS DE AMBIENTE
// Precedência: variáveis do processo (ex.: Docker) -> arquivo .env da raiz.
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

    $arquivo_env = __DIR__ . '/../.env';
    if (is_file($arquivo_env) && is_readable($arquivo_env)) {
        foreach (file($arquivo_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) {
                continue;
            }
            [$chave, $valor] = explode('=', $linha, 2);
            $GLOBALS['_MECANICA_ENV'][trim($chave)] = trim(trim($valor), " \t\r\n\"'");
        }
        define('MECANICA_ENV_ARQUIVO', $arquivo_env);
    } else {
        define('MECANICA_ENV_ARQUIVO', null);
    }
}
