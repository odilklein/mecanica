<?php
// =============================================
// SESSION — wrapper sobre a sessão PHP nativa
// (cookie httponly/samesite + helpers).
// =============================================

declare(strict_types=1);

namespace Kernel\Http;

class Session
{
    /**
     * Inicia a sessão (se ainda não iniciada) com cookies seguros.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') === '443');

            session_set_cookie_params([
                'httponly' => true,
                'secure' => $https,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): bool
    {
        return session_regenerate_id(true);
    }

    /**
     * Token CSRF da sessão (gerado uma única vez).
     */
    public function csrfToken(): string
    {
        if (!$this->has('_csrf_token')) {
            $this->set('_csrf_token', bin2hex(random_bytes(32)));
        }
        return (string) $this->get('_csrf_token');
    }

    public function verifyCsrf(?string $token): bool
    {
        return $token !== null && hash_equals((string) $this->get('_csrf_token', ''), $token);
    }

    /**
     * Mensagem flash (lida uma única vez, no próximo request).
     */
    public function flash(string $key, mixed $value): void
    {
        $this->set('_flash_' . $key, $value);
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->get('_flash_' . $key, $default);
        $this->remove('_flash_' . $key);
        return $value;
    }
}
