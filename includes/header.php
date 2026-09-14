<?php
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/../config/empresa.php';

$url_css = $caminho_base . '/css/style.css';
$url_js = $caminho_base . '/js/main.js';
$url_index = $caminho_base . '/index.php';
$url_logout = $caminho_base . '/pages/auth/logout.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#b8270f">
    <title><?php echo EMPRESA_NOME; ?> | <?php echo $titulo_pagina ?? 'Sistema de Gestão'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $url_css; ?>" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>body{opacity:0;transition:opacity .25s ease}</style>
</head>
<body class="loading">
    <script>
        window.CAMINHO_BASE = <?php echo json_encode($caminho_base); ?>;
        window.CSRF_TOKEN = <?php echo json_encode(csrf_token() ?? ''); ?>;
    </script>
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="topbar">
            <button class="topbar-burger" id="mobileMenuBtn" aria-label="Abrir menu">
                <i data-lucide="menu"></i>
            </button>
            <div class="topbar-breadcrumb">
                <a href="<?php echo $url_index; ?>"><i data-lucide="home"></i></a>
                <span class="separator">/</span>
                <span class="current"><?php echo $titulo_pagina ?? 'Dashboard'; ?></span>
            </div>
            <div class="topbar-flex-spacer"></div>
            <div class="topbar-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" placeholder="Buscar...">
            </div>
            <div class="topbar-notifications">
                <button class="topbar-btn" id="notifBell" aria-label="Notificações" aria-expanded="false">
                    <i data-lucide="bell"></i>
                    <span class="notification-dot" id="notifDot" hidden></span>
                    <span class="notification-badge" id="notifCount" hidden>0</span>
                </button>
                <div class="notifications-panel" id="notifPanel" hidden>
                    <div class="notifications-header">
                        <span><i data-lucide="bell"></i> Notificações</span>
                        <button type="button" id="notifMarkAll">Marcar todas como lidas</button>
                    </div>
                    <div class="notifications-list" id="notifList"></div>
                </div>
            </div>
            <div class="topbar-user">
                <div class="topbar-user-avatar">
                    <?php echo strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1)); ?>
                </div>
                <span class="topbar-user-name"><?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
            </div>
        </header>