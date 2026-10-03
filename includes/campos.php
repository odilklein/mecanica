<?php
// =============================================
// CAMPOS DE FORMULÁRIO — PADRÃO "BASIC WITH ICONS"
// -------------------------------------------------
// Reproduz o layout do template de referência:
//   <label> + <div class="input-group input-group-merge">
//       <span class="input-group-text"><i class="ti ..."></i></span>
//       <input class="form-control">
//   </div> + <small class="form-text">
// Uso:
//   campo_icone('nome', 'Nome completo', 'user', 'text', ['required'])
//   campo_icone('cep', 'CEP', 'map-pin', 'text', [], '00000-000')
// =============================================

if (!function_exists('campo_icone')) {
    /**
     * Renderiza um campo de formulário no padrão "Basic with Icons".
     *
     * @param string $name      nome/atributo name e id do campo
     * @param string $rotulo    texto do label
     * @param string $icone     nome do ícone Tabler (sem o prefixo)
     * @param string $tipo      type do input (text, number, ...)
     * @param array  $atributos atributos extras (required, maxlength, value, ...)
     * @param string $placeholder
     * @param string $texto_ajuda texto de ajuda abaixo (form-text)
     */
    function campo_icone(
        string $name,
        string $rotulo,
        string $icone,
        string $tipo = 'text',
        array $atributos = [],
        string $placeholder = '',
        string $texto_ajuda = ''
    ): string {
        $id = esc($name);
        $attrs = '';
        foreach ($atributos as $chave => $valor) {
            if ($valor === true) {
                $attrs .= ' ' . esc($chave);
            } elseif ($valor === false || $valor === null) {
                continue;
            } else {
                $attrs .= ' ' . esc($chave) . '="' . esc((string) $valor) . '"';
            }
        }

        $html  = '<div class="mb-3">';
        $html .= '<label class="form-label" for="' . $id . '">' . esc($rotulo) . '</label>';
        $html .= '<div class="input-group input-group-merge">';
        $html .= '<span class="input-group-text"><i class="icon-base ti tabler-' . esc($icone) . '"></i></span>';
        $html .= '<input type="' . esc($tipo) . '" class="form-control" id="' . $id . '" name="' . $id . '"'
            . ($placeholder !== '' ? ' placeholder="' . esc($placeholder) . '"' : '')
            . $attrs . '>';
        $html .= '</div>';
        if ($texto_ajuda !== '') {
            $html .= '<div class="form-text">' . esc($texto_ajuda) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('campo_select_icone')) {
    /**
     * Mesma ideia do campo_icone(), porém para <select>.
     *
     * @param string $name     nome/id do select
     * @param string $rotulo   texto do label
     * @param string $icone    ícone Tabler
     * @param string $opcoes   HTML já montado das <option>
     * @param array  $atributos
     * @param string $texto_ajuda
     */
    function campo_select_icone(
        string $name,
        string $rotulo,
        string $icone,
        string $opcoes,
        array $atributos = [],
        string $texto_ajuda = ''
    ): string {
        $id = esc($name);
        $attrs = '';
        foreach ($atributos as $chave => $valor) {
            if ($valor === true) {
                $attrs .= ' ' . esc($chave);
            } elseif ($valor === false || $valor === null) {
                continue;
            } else {
                $attrs .= ' ' . esc($chave) . '="' . esc((string) $valor) . '"';
            }
        }

        $html  = '<div class="mb-3">';
        $html .= '<label class="form-label" for="' . $id . '">' . esc($rotulo) . '</label>';
        $html .= '<div class="input-group input-group-merge">';
        $html .= '<span class="input-group-text"><i class="icon-base ti tabler-' . esc($icone) . '"></i></span>';
        $html .= '<select class="form-select" id="' . $id . '" name="' . $id . '"' . $attrs . '>' . $opcoes . '</select>';
        $html .= '</div>';
        if ($texto_ajuda !== '') {
            $html .= '<div class="form-text">' . esc($texto_ajuda) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('campo_textarea_icone')) {
    /** Campo de múltiplas linhas no padrão "Basic with Icons". */
    function campo_textarea_icone(
        string $name,
        string $rotulo,
        string $icone,
        array $atributos = [],
        string $placeholder = '',
        string $texto_ajuda = ''
    ): string {
        $id = esc($name);
        $attrs = '';
        foreach ($atributos as $chave => $valor) {
            if ($valor === true) {
                $attrs .= ' ' . esc($chave);
            } elseif ($valor === false || $valor === null) {
                continue;
            } else {
                $attrs .= ' ' . esc($chave) . '="' . esc((string) $valor) . '"';
            }
        }

        $html  = '<div class="mb-3">';
        $html .= '<label class="form-label" for="' . $id . '">' . esc($rotulo) . '</label>';
        $html .= '<div class="input-group input-group-merge">';
        $html .= '<span class="input-group-text align-self-start"><i class="icon-base ti tabler-' . esc($icone) . '"></i></span>';
        $html .= '<textarea class="form-control" id="' . $id . '" name="' . $id . '" rows="3"'
            . ($placeholder !== '' ? ' placeholder="' . esc($placeholder) . '"' : '')
            . $attrs . '></textarea>';
        $html .= '</div>';
        if ($texto_ajuda !== '') {
            $html .= '<div class="form-text">' . esc($texto_ajuda) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('campo_check_icone')) {
    /** Checkbox/switch com ícone, no padrão da referência. */
    function campo_check_icone(string $name, string $rotulo, string $icone, bool $marcado = true, string $id = ''): string
    {
        $input_id = $id !== '' ? $id : $name;
        $checked = $marcado ? ' checked' : '';
        return '<div class="form-check form-switch mb-3">'
            . '<input class="form-check-input" type="checkbox" role="switch" id="' . esc($input_id) . '" name="' . esc($name) . '" value="1"' . $checked . '>'
            . '<label class="form-check-label" for="' . esc($input_id) . '">'
            . '<i class="icon-base ti tabler-' . esc($icone) . ' me-1"></i>' . esc($rotulo) . '</label>'
            . '</div>';
    }
}