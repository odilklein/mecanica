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

if (!function_exists('json_response')) {
    /** Cria uma resposta JSON (PSR-7). */
    function json_response(mixed $data, int $status = 200): \Nyholm\Psr7\Response
    {
        return new \Nyholm\Psr7\Response(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode($data, JSON_UNESCAPED_UNICODE)
        );
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

if (!function_exists('pagination_pages')) {
    /** Janela de páginas com reticências: 1 … 4 5 [6] 7 8 … 20 */
    function pagination_pages(int $current, int $total, int $max = 7): array
    {
        if ($total <= 1) {
            return [1];
        }
        if ($total <= $max) {
            return range(1, $total);
        }

        $middle = (int) floor(($max - 1) / 2);
        $start = min(max(2, $current - $middle), $total - 1);
        $end = max(min($total - 1, $current + $middle), $start);

        while (true) {
            $inner = range($start, $end);
            $count = count($inner) + 2 + ($start > 2 ? 1 : 0) + ($end < $total - 1 ? 1 : 0);
            if ($count <= $max || count($inner) <= 1) {
                break;
            }
            if (abs($start - $current) >= abs($end - $current)) {
                $start++;
            } else {
                $end--;
            }
        }

        $list = [1];
        if ($start > 2) {
            $list[] = '…';
        }
        for ($p = $start; $p <= $end; $p++) {
            $list[] = $p;
        }
        if ($end < $total - 1) {
            $list[] = '…';
        }
        $list[] = $total;

        return $list;
    }
}

if (!function_exists('pagination_links')) {
    /** Paginação no padrão Vuexy (chevrons + janela de páginas). */
    function pagination_links(int $page, int $lastPage, string $path, array $params = []): string
    {
        if ($lastPage <= 1) {
            return '';
        }

        $link = static function (int $p) use ($path, $params): string {
            $params['page'] = $p;
            return $path . '?' . http_build_query($params);
        };

        $html = '<ul class="pagination mb-0">';
        $html .= $page <= 1
            ? '<li class="page-item prev-item disabled"><span class="page-link"><i class="icon-base ti tabler-chevron-left"></i></span></li>'
            : '<li class="page-item prev-item"><a class="page-link" href="' . e($link($page - 1)) . '"><i class="icon-base ti tabler-chevron-left"></i></a></li>';

        foreach (pagination_pages($page, $lastPage) as $p) {
            if ($p === '…') {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            } elseif ($p === $page) {
                $html .= '<li class="page-item active"><span class="page-link">' . $p . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . e($link((int) $p)) . '">' . $p . '</a></li>';
            }
        }

        $html .= $page >= $lastPage
            ? '<li class="page-item next-item disabled"><span class="page-link"><i class="icon-base ti tabler-chevron-right"></i></span></li>'
            : '<li class="page-item next-item"><a class="page-link" href="' . e($link($page + 1)) . '"><i class="icon-base ti tabler-chevron-right"></i></a></li>';

        return $html . '</ul>';
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

if (!function_exists('isValidPlate')) {
    /** Valida placa (ABC-1234 ou Mercosul ABC1D23). */
    function isValidPlate(mixed $plate): bool
    {
        $plate = strtoupper(trim((string) $plate));
        return (bool) preg_match('/^(?:[A-Z]{3}-\d{4}|[A-Z]{3}\d[A-Z0-9]\d{2})$/', $plate);
    }
}
