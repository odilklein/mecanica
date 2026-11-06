<?php
// =============================================
// HELPERS GLOBAIS DO KERNEL (carregados via
// autoload "files" no composer.json).
// =============================================

declare(strict_types=1);

if (!function_exists('e')) {
    /** Escapa uma string para saída HTML (contra XSS). */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('clean')) {
    /** Remove espaços do início/fim de uma entrada. */
    function clean(mixed $value): string
    {
        return trim((string) $value);
    }
}

if (!function_exists('env')) {
    /** Lê uma variável de ambiente (getenv/$_ENV), com fallback. */
    function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        return $default;
    }
}
