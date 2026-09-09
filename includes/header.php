<?php
// Calcular caminho base relativo até a raiz do projeto (index.php),
// comparando a hierarquia de arquivos da página com a raiz do projeto.
// Funciona independente de subpasta na URL (ex.: localhost/mecanica).
$pagina_dir_fs = str_replace('\\', '/', realpath(dirname($_SERVER['SCRIPT_FILENAME'])));
$raiz_fs = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$dirs_pagina = explode('/', trim($pagina_dir_fs, '/'));
$dirs_raiz = explode('/', trim($raiz_fs, '/'));
$comum = 0;
while ($comum < count($dirs_pagina) && $comum < count($dirs_raiz) && $dirs_pagina[$comum] === $dirs_raiz[$comum]) {
    $comum++;
}
$caminho_base = str_repeat('../', max(0, count($dirs_pagina) - $comum));

$url_css = $caminho_base . 'css/style.css';
$url_js = $caminho_base . 'js/main.js';
$url_index = $caminho_base . 'index.php';
$url_logout = $caminho_base . 'pages/auth/logout.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#bd1804">
    <title>AutoMecânica | <?php echo $titulo_pagina ?? 'Sistema de Gestão'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo $url_css; ?>" rel="stylesheet">
</head>
<body>
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="topbar">
            <button class="topbar-burger" id="mobileMenuBtn" aria-label="Abrir menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="topbar-breadcrumb">
                <a href="<?php echo $url_index; ?>"><i class="fas fa-home"></i></a>
                <span class="separator">/</span>
                <span class="current"><?php echo $titulo_pagina ?? 'Dashboard'; ?></span>
            </div>
            <div class="topbar-flex-spacer"></div>
            <div class="topbar-search">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Buscar...">
            </div>
            <button class="topbar-btn" title="Notificações">
                <i class="fas fa-bell"></i>
                <span class="notification-dot"></span>
            </button>
            <div class="topbar-user">
                <div class="topbar-user-avatar">
                    <?php echo strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1)); ?>
                </div>
                <span class="topbar-user-name"><?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
            </div>
        </header>