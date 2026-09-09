<?php
// =========================================================
// BASE URL DA RAIZ DO PROJETO (onde está o index.php)
// Exemplo de retorno: '' (raiz do site) ou '/mecanica' (subpasta)
//
// Estratégia: usa a URL (SCRIPT_NAME) para saber onde a página
// está e sobe o número de níveis (calculado pela hierarquia de
// arquivos, comparando de forma case-insensitive) até a raiz.
// Não usa realpath() e funciona em Windows/XAMPP/LAMP e subpastas.
// =========================================================

if (!isset($GLOBALS['caminho_base'])) {

    // ---------- 1) Profundidade da página até a raiz do projeto ----------
    $script_file = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $pagina_dir = str_replace('\\', '/', dirname($script_file));
    $raiz_fs = str_replace('\\', '/', dirname(__DIR__)); // pai da pasta includes/

    if ($pagina_dir === '' || $pagina_dir === '.' || $pagina_dir === '/') {
        $pagina_dir = $raiz_fs;
    }

    $dirs_pagina = array_values(array_filter(explode('/', $pagina_dir), 'strlen'));
    $dirs_raiz = array_values(array_filter(explode('/', $raiz_fs), 'strlen'));

    $comum = 0;
    $max = min(count($dirs_pagina), count($dirs_raiz));
    while ($comum < $max && strtolower($dirs_pagina[$comum]) === strtolower($dirs_raiz[$comum])) {
        $comum++;
    }
    $profundidade = max(0, count($dirs_pagina) - $comum);

    // ---------- 2) Diretório da página na URL ----------
    $url_pagina_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $url_pagina_dir = rtrim($url_pagina_dir, '/');
    if ($url_pagina_dir === '.' || $url_pagina_dir === '/') $url_pagina_dir = '';

    $segmentos = ($url_pagina_dir === '') ? [] : explode('/', trim($url_pagina_dir, '/'));

    // Subir níveis: remover segmentos de "pages/modulo" até chegar à raiz do projeto
    for ($i = 0; $i < $profundidade && count($segmentos) > 0; $i++) {
        array_pop($segmentos);
    }

    $GLOBALS['caminho_base'] = count($segmentos) > 0 ? '/' . implode('/', $segmentos) : '';
}