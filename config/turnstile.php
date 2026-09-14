<?php
// =============================================
// CLOUDFLARE TURNSTILE - CONFIGURAÇÃO
// A site key é pública e pode ficar versionada.
// O secret NUNCA fica no código: vem de TURNSTILE_SECRET (.env).
// =============================================

if (!defined('TURNSTILE_SITE_KEY_PADRAO')) {
    define('TURNSTILE_SITE_KEY_PADRAO', '<sua-site-key>');
}

if (!function_exists('turnstile_site_key')) {
    function turnstile_site_key(): string
    {
        $chave = trim((string) getenv('TURNSTILE_SITE_KEY'));
        return $chave !== '' ? $chave : TURNSTILE_SITE_KEY_PADRAO;
    }
}
