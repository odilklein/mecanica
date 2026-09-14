<?php
// =============================================
// CARREGAMENTO DE VARIÁVEIS DE AMBIENTE (.env)
// O PHP puro não lê .env automaticamente; este parser cobre esse caso
// sem depender de extensões externas. Também funciona quando putenv()
// está desabilitado (comum em hospedagem compartilhada).
// =============================================

$GLOBALS['_MECANICA_ENV'] = $GLOBALS['_MECANICA_ENV'] ?? [];

if (!function_exists('carregar_env')) {
    function carregar_env(string $arquivo): void
    {
        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) {
                continue;
            }
            [$chave, $valor] = explode('=', $linha, 2);
            $chave = trim($chave);
            $valor = trim($valor, " \t\r\n\"'");

            $GLOBALS['_MECANICA_ENV'][$chave] = $valor;
            if (!getenv($chave)) {
                if (function_exists('putenv')) {
                    @putenv($chave . '=' . $valor);
                }
                $_ENV[$chave] = $valor;
            }
        }
    }
}

if (!function_exists('env')) {
    // Lê uma variável: getenv() -> $_ENV -> parser do .env -> padrão.
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

if (!function_exists('config_local')) {
    // Carrega config.local.php (fora do Git), se existir.
    function config_local(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $arquivo = __DIR__ . '/config.local.php';
            if (is_file($arquivo)) {
                $retorno = require $arquivo;
                if (is_array($retorno)) {
                    $cache = $retorno;
                }
            }
        }
        return $cache;
    }
}

if (!function_exists('config_valor')) {
    // Precedência: config.local.php -> .env -> padrão.
    function config_valor(string $chave, ?string $padrao = null): ?string
    {
        $local = config_local();
        if (isset($local[$chave]) && $local[$chave] !== '') {
            return (string) $local[$chave];
        }
        return env($chave, $padrao);
    }
}

if (!defined('MECANICA_ENV_CARREGADO')) {
    define('MECANICA_ENV_CARREGADO', true);
    // Procura o .env na raiz do projeto, em níveis acima e no webroot.
    $candidatos_env = [
        __DIR__ . '/../.env',
        __DIR__ . '/../../.env',
        __DIR__ . '/../../../.env',
    ];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $docroot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
        $candidatos_env[] = $docroot . '/.env';
        $candidatos_env[] = dirname($docroot) . '/.env';
    }
    foreach (array_unique($candidatos_env) as $arquivo_env) {
        if (is_file($arquivo_env) && is_readable($arquivo_env)) {
            carregar_env($arquivo_env);
            if (!defined('MECANICA_ENV_ARQUIVO')) {
                define('MECANICA_ENV_ARQUIVO', $arquivo_env);
            }
            break;
        }
    }
    if (!defined('MECANICA_ENV_ARQUIVO')) {
        define('MECANICA_ENV_ARQUIVO', null);
    }
}
