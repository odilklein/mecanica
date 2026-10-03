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

// =============================================
// LAYOUT DE TABELA (estilo Vuexy)
// Rodapé: "Mostrando X a Y de Z" à esquerda e paginação numerada no canto inferior direito.
// O topo (busca + filtros) é renderizado com os helpers de includes/crud.php.
// =============================================

if (!function_exists('dt_rodape')) {
    // Fecha a tabela e renderiza o rodapé (informação à esquerda, paginação à direita).
    function dt_rodape(
        int $pagina,
        int $total,
        int $por_pagina,
        int $total_paginas,
        array $params = [],
        string $destino = 'listar.php',
        string $rotulo = 'registros'
    ): string {
        $inicio = $total === 0 ? 0 : (($pagina - 1) * $por_pagina) + 1;
        $fim = min($pagina * $por_pagina, $total);
        $info = 'Mostrando ' . $inicio . ' a ' . $fim . ' de ' . $total . ' ' . $rotulo;

        $html  = '</div>'; // fecha table-responsive
        $html .= '<div class="table-panel">';
        $html .= '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">';
        $html .= '<span class="text-body-secondary">' . esc($info) . '</span>';
        $html .= dt_paging_nav($pagina, $total_paginas, $params, $destino);
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('dt_paginas_visiveis')) {
    // Janela de páginas com reticências: 1 … 4 5 [6] 7 8 … 20
    // O tamanho da janela é sempre <= $maximo: quando falta espaço
    // para as bordas, remove-se a página central mais distante.
    function dt_paginas_visiveis(int $atual, int $total, int $maximo = 7): array
    {
        if ($total <= 1) {
            return [1];
        }
        if ($total <= $maximo) {
            return range(1, $total);
        }

        $meio = (int) floor(($maximo - 1) / 2);
        $inicio = min(max(2, $atual - $meio), $total - 1);
        $fim = max(min($total - 1, $atual + $meio), $inicio);

        // Monta a janela interna e descarta o item central mais distante
        // enquanto exceder o limite (mantém $atual sempre visível).
        while (true) {
            $internos = range($inicio, $fim);
            $total_itens = count($internos) + 2 // primeira e última
                + ($inicio > 2 ? 1 : 0)
                + ($fim < $total - 1 ? 1 : 0);

            if ($total_itens <= $maximo || count($internos) <= 1) {
                break;
            }
            // Remove a extremidade interna mais distante da página atual.
            if (abs($inicio - $atual) >= abs($fim - $atual)) {
                $inicio++;
            } else {
                $fim--;
            }
        }

        $lista = [1];
        if ($inicio > 2) {
            $lista[] = '…';
        }
        for ($p = $inicio; $p <= $fim; $p++) {
            $lista[] = $p;
        }
        if ($fim < $total - 1) {
            $lista[] = '…';
        }
        $lista[] = $total;
        return $lista;
    }
}

if (!function_exists('dt_paging_nav')) {
    // <ul class="pagination"> com setas (chevrons) e janela de páginas.
    function dt_paging_nav(int $pagina, int $total_paginas, array $params = [], string $destino = 'listar.php'): string
    {
        if ($total_paginas <= 1) {
            return '';
        }
        $link = function (int $p) use ($params, $destino): string {
            $params['pagina'] = $p;
            return $destino . '?' . http_build_query($params);
        };

        $html = '<ul class="pagination mb-0">';

        // Anterior
        if ($pagina <= 1) {
            $html .= '<li class="page-item prev-item disabled"><span class="page-link"><i class="icon-base ti tabler-chevron-left"></i></span></li>';
        } else {
            $html .= '<li class="page-item prev-item"><a class="page-link" href="' . esc($link($pagina - 1)) . '" aria-label="Página anterior"><i class="icon-base ti tabler-chevron-left"></i></a></li>';
        }

        // Páginas numeradas (janela com reticências)
        foreach (dt_paginas_visiveis($pagina, $total_paginas) as $p) {
            if ($p === '…') {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            } elseif ($p === $pagina) {
                $html .= '<li class="page-item active"><span class="page-link">' . $p . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . esc($link((int) $p)) . '">' . $p . '</a></li>';
            }
        }

        // Próxima
        if ($pagina >= $total_paginas) {
            $html .= '<li class="page-item next-item disabled"><span class="page-link"><i class="icon-base ti tabler-chevron-right"></i></span></li>';
        } else {
            $html .= '<li class="page-item next-item"><a class="page-link" href="' . esc($link($pagina + 1)) . '" aria-label="Próxima página"><i class="icon-base ti tabler-chevron-right"></i></a></li>';
        }

        return $html . '</ul>';
    }
}
