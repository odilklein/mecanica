<?php
// =============================================
// COMPONENTES COMPARTILHADOS DE CRUD
// -------------------------------------------------
// Helpers de markup para as listagens modernas
// (toolbar, seleção em massa, paginação, estados
// vazios e cartões KPI). O comportamento é dirigido
// por js/crud.js, que consome window.CrudConfig.
// =============================================

if (!function_exists('crud_kpi')) {
    // Cartão de indicador no topo da listagem.
    // $tom: primary | success | danger | warning | info
    function crud_kpi(string $id, string $rotulo, string $icone, string $tom = 'primary', string $valor_inicial = '—'): string
    {
        return '<div class="col-6 col-lg-3">'
            . '<div class="card crud-kpi">'
            . '<div class="card-body">'
            . '<div class="crud-kpi-icon bg-label-' . esc($tom) . '"><i class="icon-base ti tabler-' . esc($icone) . '"></i></div>'
            . '<div class="crud-kpi-info">'
            . '<span class="crud-kpi-label">' . esc($rotulo) . '</span>'
            . '<span class="crud-kpi-value" id="' . esc($id) . '">' . esc($valor_inicial) . '</span>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}

if (!function_exists('crud_toolbar')) {
    // Abre a barra de busca + filtros. Feche com </div></div> ou use crud_toolbar_fim().
    function crud_toolbar(string $placeholder, string $filtros_html = ''): string
    {
        return '<div class="table-panel">'
            . '<div class="crud-toolbar">'
            . '<div class="crud-search">'
            . '<i class="icon-base ti tabler-search"></i>'
            . '<input type="search" class="form-control" data-crud="busca" placeholder="' . esc($placeholder) . '" autocomplete="off">'
            . '</div>'
            . $filtros_html
            . '</div>'
            . '</div>';
    }
}

if (!function_exists('crud_filtro_select')) {
    // <select> de filtro que o JS observa via data-crud-filtro.
    // $opcoes: [valor => rótulo]
    function crud_filtro_select(string $nome, array $opcoes, string $todos = 'Todos'): string
    {
        $html = '<select class="form-select" data-crud-filtro="' . esc($nome) . '" aria-label="' . esc($todos) . '">';
        $html .= '<option value="">' . esc($todos) . '</option>';
        foreach ($opcoes as $valor => $rotulo) {
            $html .= '<option value="' . esc((string) $valor) . '">' . esc($rotulo) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}

if (!function_exists('crud_bulk_bar')) {
    // Barra de ações em massa (oculta até haver seleção).
    // $botoes: lista de ['id'=>, 'rotulo'=>, 'icone'=>, 'tom'=> (opcional)]
    function crud_bulk_bar(array $botoes): string
    {
        $html = '<div class="bulk-actions mt-3" data-crud="bulk-bar" hidden>';
        $html .= '<span class="bulk-count"><strong data-crud="bulk-count">0</strong> selecionado(s)</span>';
        foreach ($botoes as $b) {
            $tom = $b['tom'] ?? 'outline-secondary';
            $html .= '<button type="button" class="btn btn-' . esc($tom) . ' action-button" data-crud-acao="' . esc($b['id']) . '">'
                . '<i class="icon-base ti tabler-' . esc($b['icone']) . '"></i> ' . esc($b['rotulo']) . '</button>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('crud_tabela_abrir')) {
    // Abre a tabela. A <thead> usa os rótulos; o <tbody> é preenchido pelo JS.
    // $selecionavel adiciona a coluna de checkbox.
    function crud_tabela_abrir(array $colunas, bool $selecionavel = true, string $id = 'crudTbody'): string
    {
        $html = '<div class="table-responsive"><table class="table table-hover align-middle responsive-card" data-crud="tabela">';
        $html .= '<thead><tr>';
        if ($selecionavel) {
            $html .= '<th class="check-cell"><input class="form-check-input" type="checkbox" data-crud="check-todos" aria-label="Selecionar todos"></th>';
        }
        foreach ($colunas as $coluna) {
            $classe = $coluna['classe'] ?? '';
            $html .= '<th' . ($classe ? ' class="' . esc($classe) . '"' : '') . '>' . esc($coluna['rotulo']) . '</th>';
        }
        $html .= '</tr></thead>';
        $html .= '<tbody id="' . esc($id) . '"></tbody>';
        $html .= '</table></div>';
        return $html;
    }
}

if (!function_exists('crud_rodape')) {
    // Rodapé da tabela: informação à esquerda e paginação à direita.
    function crud_rodape(): string
    {
        return '<div class="table-panel">'
            . '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">'
            . '<span class="text-body-secondary" data-crud="info">Carregando...</span>'
            . '<div data-crud="paginacao"></div>'
            . '</div>'
            . '</div>';
    }
}

if (!function_exists('crud_empty_state')) {
    // Estado vazio genérico (usado como template ou server-side).
    function crud_empty_state(string $icone, string $titulo, string $texto, string $botao_html = ''): string
    {
        return '<div class="empty-state">'
            . '<div class="empty-state-icon"><i class="icon-base ti tabler-' . esc($icone) . '"></i></div>'
            . '<h4>' . esc($titulo) . '</h4>'
            . '<p>' . esc($texto) . '</p>'
            . $botao_html
            . '</div>';
    }
}

if (!function_exists('crud_toast')) {
    // Container do toast de feedback (mesmo visual para todos os módulos).
    function crud_toast(string $id = 'crudToast'): string
    {
        return '<div id="' . esc($id) . '" class="crud-toast" role="status" aria-live="polite"></div>';
    }
}

if (!function_exists('crud_confirm_modal')) {
    // Modal padrão de confirmação (exclusões, ações em massa).
    function crud_confirm_modal(string $id = 'crudConfirmModal'): string
    {
        return '<div class="modal fade" id="' . esc($id) . '" tabindex="-1" aria-labelledby="' . esc($id) . 'Titulo" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered modal-sm">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title" id="' . esc($id) . 'Titulo" data-crud="confirm-titulo">Confirmar</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>'
            . '</div>'
            . '<div class="modal-body"><p class="mb-0" data-crud="confirm-mensagem"></p></div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>'
            . '<button type="button" class="btn btn-danger" data-crud="confirm-ok">Confirmar</button>'
            . '</div>'
            . '</div></div></div>';
    }
}

if (!function_exists('crud_form_abrir')) {
    // Abre o modal de formulário com cabeçalho padronizado.
    function crud_form_abrir(string $id, string $entidade_singular): string
    {
        $html = '<div class="modal fade" id="' . esc($id) . '" tabindex="-1" data-crud="modal" aria-hidden="true">';
        $html .= '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">';
        $html .= '<div class="modal-content">';
        $html .= '<form data-crud="form">';
        $html .= '<div class="modal-header">';
        $html .= '<h5 class="modal-title" data-crud="modal-titulo">Novo ' . esc($entidade_singular) . '</h5>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>';
        $html .= '</div>';
        $html .= '<div class="modal-body">';
        $html .= '<div class="alert alert-danger" data-crud="modal-erro" hidden></div>';
        return $html;
    }
}

if (!function_exists('crud_form_fechar')) {
    // Fecha o modal de formulário com rodapé padronizado.
    function crud_form_fechar(string $rotulo_botao = 'Salvar'): string
    {
        return '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>'
            . '<button type="submit" class="btn btn-primary btn-icon"><i class="icon-base ti tabler-device-floppy me-1"></i> ' . esc($rotulo_botao) . '</button>'
            . '</div>'
            . '</form>'
            . '</div></div></div>';
    }
}

if (!function_exists('crud_config')) {
    // Serializa a configuração consumida por js/crud.js.
    function crud_config(array $config): string
    {
        return '<script>window.CrudConfig = Object.assign(window.CrudConfig || {}, ' . json_encode($config, JSON_UNESCAPED_UNICODE) . ');</script>';
    }
}
