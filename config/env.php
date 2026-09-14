<?php
// =============================================
// CARREGAMENTO DE VARIÁVEIS DE AMBIENTE (.env)
// O PHP puro não lê .env automaticamente; este parser cobre esse caso
// sem depender de extensões externas.
// =============================================

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
            if (!getenv($chave)) {
                if (function_exists('putenv')) {
                    putenv($chave . '=' . $valor);
                }
                $_ENV[$chave] = $valor;
            }
        }
    }
}

if (!defined('MECANICA_ENV_CARREGADO')) {
    define('MECANICA_ENV_CARREGADO', true);
    // Procura o .env na raiz do projeto e, se não achar, um nível acima
    // (permite guardá-lo fora do webroot, ex.: htdocs/../.env na hospedagem).
    $candidatos_env = [
        __DIR__ . '/../.env',
        __DIR__ . '/../../.env',
    ];
    foreach ($candidatos_env as $arquivo_env) {
        if (is_file($arquivo_env)) {
            carregar_env($arquivo_env);
            break;
        }
    }
}
