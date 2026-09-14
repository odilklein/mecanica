<?php
// =============================================
// CLOUDFLARE TURNSTILE - CONFIGURAÇÃO
// A site key é pública, mas NÃO fica versionada: vem de TURNSTILE_SITE_KEY (.env).
// O secret NUNCA fica no código: vem de TURNSTILE_SECRET (.env).
// =============================================

require_once __DIR__ . '/env.php';

if (!function_exists('turnstile_site_key')) {
    function turnstile_site_key(): string
    {
        return trim((string) config_valor('TURNSTILE_SITE_KEY'));
    }
}

if (!function_exists('turnstile_configurado')) {
    function turnstile_configurado(): bool
    {
        return turnstile_site_key() !== '';
    }
}
