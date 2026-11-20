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
            // Rejeita IDs de sessão não inicializados (fixation).
            ini_set('session.use_strict_mode', '1');

            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') === '443');

            // Atrás de proxy TLS confiável, o PHP não vê HTTPS; só aceita o
            // cabeçalho quando TRUST_PROXY=1 (evita spoofing direto).
            if (!$https && env('TRUST_PROXY') === '1'
                && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
                $https = true;
            }

            session_set_cookie_params([
                'httponly' => true,
                'secure' => $https,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        // Token CSRF existe desde a criação da sessão: qualquer POST sem
        // token (inclusive de uma sessão recém-criada) é rejeitado.
        if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || $_SESSION['_csrf_token'] === '') {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
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
     * Destrói a sessão no servidor e invalida o cookie no cliente.
     */
    public function destroy(): void
    {
        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $params = session_get_cookie_params();
        if (!headers_sent()) {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'] ?: '/',
                'domain' => (string) $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Token CSRF da sessão (gerado uma única vez).
     */
    public function csrfToken(): string
    {
        if (!$this->has('_csrf_token') || (string) $this->get('_csrf_token') === '') {
            $this->set('_csrf_token', bin2hex(random_bytes(32)));
        }
        return (string) $this->get('_csrf_token');
    }

    public function verifyCsrf(?string $token): bool
    {
        // Fail-closed: token vazio, sessão sem token ou tamanho inesperado
        // nunca passam (evita hash_equals('', '') === true).
        if ($token === null || $token === '' || !$this->has('_csrf_token')) {
            return false;
        }

        return hash_equals((string) $this->get('_csrf_token'), $token);
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
