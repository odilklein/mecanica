<?php
// =============================================
// PAGINAÇÃO (server-side) para listagens
// Padrão CRUD: filtros via querystring + página + por_pagina
// =============================================

if (!function_exists('pagina_atual')) {
    function pagina_atual(): int
    {
        return max(1, (int) ($_GET['pagina'] ?? 1));
    }
}

if (!function_exists('por_pagina_atual')) {
    // Lê ?por_pagina limitado a uma lista branca; padrão 15.
    function por_pagina_atual(array $opcoes = [10, 15, 25, 50], int $padrao = 15): int
    {
        $valor = (int) ($_GET['por_pagina'] ?? $padrao);
        return in_array($valor, $opcoes, true) ? $valor : $padrao;
    }
}

if (!function_exists('paginacao_calcular')) {
    // Normaliza a página e devolve [pagina, offset, total_paginas].
    function paginacao_calcular(int $total, int $por_pagina, int $pagina): array
    {
        $total_paginas = max(1, (int) ceil($total / $por_pagina));
        $pagina = min(max(1, $pagina), $total_paginas);
        return [$pagina, ($pagina - 1) * $por_pagina, $total_paginas];
    }
}

if (!function_exists('paginacao_links')) {
    // Renderiza paginação com janela de páginas (evita centenas de links).
    // $params são os filtros atuais, preservados nos links.
    function paginacao_links(int $pagina, int $total_paginas, array $params = [], string $destino = 'listar.php'): string
    {
        if ($total_paginas <= 1) {
            return '';
        }

        $link = function (int $p) use ($params, $destino): string {
            $params['pagina'] = $p;
            return $destino . '?' . http_build_query($params);
        };
        $item = function (int $p, string $label, bool $disabled = false, bool $active = false) use ($link): string {
            if ($disabled) {
                return '<li class="page-item disabled"><span class="page-link">' . $label . '</span></li>';
            }
            if ($active) {
                return '<li class="page-item active"><span class="page-link">' . $label . '</span></li>';
            }
            return '<li class="page-item"><a class="page-link" href="' . esc($link($p)) . '">' . $label . '</a></li>';
        };

        $html = '<nav class="pagination-nav" aria-label="Paginação"><ul class="pagination justify-content-center my-3">';
        $html .= $item($pagina - 1, '&laquo;', $pagina <= 1);

        $inicio = max(1, $pagina - 2);
        $fim = min($total_paginas, $pagina + 2);

        if ($inicio > 1) {
            $html .= $item(1, '1');
            if ($inicio > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }
        for ($p = $inicio; $p <= $fim; $p++) {
            $html .= $item($p, (string) $p, false, $p === $pagina);
        }
        if ($fim < $total_paginas) {
            if ($fim < $total_paginas - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $html .= $item($total_paginas, (string) $total_paginas);
        }

        $html .= $item($pagina + 1, '&raquo;', $pagina >= $total_paginas);
        return $html . '</ul></nav>';
    }
}

if (!function_exists('opcoes_por_pagina')) {
    // <option> do seletor "por página".
    function opcoes_por_pagina(int $atual, array $opcoes = [10, 15, 25, 50]): string
    {
        $html = '';
        foreach ($opcoes as $op) {
            $sel = $op === $atual ? ' selected' : '';
            $html .= '<option value="' . $op . '"' . $sel . '>' . $op . '</option>';
        }
        return $html;
    }
}
