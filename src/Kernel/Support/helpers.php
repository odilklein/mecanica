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

if (!function_exists('redirect')) {
    /** Cria uma resposta de redirecionamento (302 por padrão). */
    function redirect(string $url, int $status = 302): \Nyholm\Psr7\Response
    {
        return new \Nyholm\Psr7\Response($status, ['Location' => $url]);
    }
}

if (!function_exists('roleLabel')) {
    /** Rótulo legível (pt-BR) de um papel de usuário. */
    function roleLabel(?string $role): string
    {
        $map = [
            'admin' => 'Administrador',
            'secretary' => 'Secretaria',
            'lead_mechanic' => 'Mecânico-chefe',
            'mechanic' => 'Mecânico',
        ];
        return $map[$role] ?? 'Usuário';
    }
}

if (!function_exists('flash')) {
    /** Lê (e limpa) uma mensagem flash da sessão. */
    function flash(string $key, mixed $default = null): mixed
    {
        $k = '_flash_' . $key;
        $value = $_SESSION[$k] ?? $default;
        unset($_SESSION[$k]);
        return $value;
    }
}

if (!function_exists('crud_routes')) {
    /** Registra as 6 rotas padrão de um CRUD para um controller. */
    function crud_routes(\Kernel\Http\Router $router, string $path, string $controller): void
    {
        $router->get($path, [$controller, 'index']);
        $router->get($path . '/create', [$controller, 'create']);
        $router->post($path, [$controller, 'store']);
        $router->get($path . '/{id}/edit', [$controller, 'edit']);
        $router->post($path . '/{id}', [$controller, 'update']);
        $router->post($path . '/{id}/delete', [$controller, 'destroy']);
    }
}

if (!function_exists('isValidCpf')) {
    /** Valida CPF (aceita com ou sem máscara). */
    function isValidCpf(mixed $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', (string) $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$t] !== $d) {
                return false;
            }
        }
        return true;
    }
}
