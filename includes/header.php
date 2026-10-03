<?php
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/../config/empresa.php';

// `base_url.php` grava em $GLOBALS; expõe como variável local.
$caminho_base = $GLOBALS['caminho_base'] ?? '';

$url_core = $caminho_base . '/assets/vendor/css/core.css';
$url_demo = $caminho_base . '/assets/css/demo.css';
$url_mecanica = $caminho_base . '/css/mecanica.css';
$url_icons = $caminho_base . '/assets/vendor/fonts/iconify-icons.css';
$url_index = $caminho_base . '/index.php';
?>
<!DOCTYPE html>
<html
    lang="pt-BR"
    class="layout-navbar-fixed layout-menu-fixed layout-compact"
    dir="ltr"
    data-skin="default"
    data-bs-theme="light"
    data-assets-path="<?php echo esc($caminho_base); ?>/assets/"
    data-template="vertical-menu-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#b8270f">
    <title><?php echo EMPRESA_NOME; ?> | <?php echo $titulo_pagina ?? 'Sistema de Gestão'; ?></title>

    <link rel="icon" href="data:,">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <!-- Ícones (Tabler) -->
    <link rel="stylesheet" href="<?php echo $url_icons; ?>">

    <!-- Core CSS (Bootstrap 5 + Vuexy) -->
    <link rel="stylesheet" href="<?php echo $url_core; ?>">
    <link rel="stylesheet" href="<?php echo $url_demo; ?>">

    <!-- Camada do sistema (tokens vermelhos + componentes) -->
    <link rel="stylesheet" href="<?php echo $url_mecanica; ?>">

    <?php if (!empty($head_extra)) echo $head_extra; ?>

    <!-- Helpers do layout (necessário antes do menu.js) -->
    <script src="<?php echo $caminho_base; ?>/assets/vendor/js/helpers.js"></script>
</head>
<body>
    <script>
        window.CAMINHO_BASE = <?php echo json_encode($caminho_base); ?>;
        window.CSRF_TOKEN = <?php echo json_encode(csrf_token() ?? ''); ?>;
    </script>

    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php require_once __DIR__ . '/sidebar.php'; ?>

            <!-- Layout page -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                            <i class="icon-base ti tabler-menu-2 icon-md"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
                            <!-- Tema -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill" id="nav-theme" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="icon-base ti tabler-sun icon-22px theme-icon-active" id="themeIcon" data-icon="sun"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme">
                                    <li>
                                        <button type="button" class="dropdown-item align-items-center active" data-bs-theme-value="light" aria-pressed="true">
                                            <span><i class="icon-base ti tabler-sun icon-22px me-3" data-icon="sun"></i>Claro</span>
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="dark" aria-pressed="false">
                                            <span><i class="icon-base ti tabler-moon-stars icon-22px me-3" data-icon="moon-stars"></i>Escuro</span>
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="system" aria-pressed="false">
                                            <span><i class="icon-base ti tabler-device-desktop-analytics icon-22px me-3" data-icon="device-desktop-analytics"></i>Sistema</span>
                                        </button>
                                    </li>
                                </ul>
                            </li>

                            <!-- Notificações -->
                            <li class="nav-item navbar-dropdown dropdown-notifications dropdown me-3 me-xl-2">
                                <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span class="position-relative">
                                        <i class="icon-base ti tabler-bell icon-22px"></i>
                                        <span class="badge rounded-pill bg-danger badge-dot badge-notifications border" id="notifDot" hidden></span>
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end p-0">
                                    <li class="dropdown-menu-header border-bottom">
                                        <div class="dropdown-header d-flex align-items-center py-3">
                                            <h6 class="mb-0 me-auto">Notificações</h6>
                                            <div class="d-flex align-items-center h6 mb-0">
                                                <span class="badge bg-label-primary me-2" id="notifCount" hidden>0</span>
                                                <a href="javascript:void(0)" class="dropdown-notifications-all p-2 btn btn-icon" id="notifMarkAll" data-bs-toggle="tooltip" data-bs-placement="top" title="Marcar todas como lidas">
                                                    <i class="icon-base ti tabler-mail-opened text-heading"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="dropdown-notifications-list scrollable-container">
                                        <ul class="list-unstyled mb-0" id="notifList"></ul>
                                    </li>
                                    <li class="border-top">
                                        <div class="d-grid p-4">
                                            <a class="btn btn-primary btn-sm d-flex" href="<?php echo $caminho_base; ?>/pages/notificacoes/listar.php">
                                                <small class="align-middle">Ver todas as notificações</small>
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            </li>

                            <!-- Usuário -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <span class="avatar-initial rounded-circle bg-primary">
                                            <?php echo esc(strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1))); ?>
                                        </span>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item mt-0" href="<?php echo $caminho_base; ?>/pages/auth/perfil.php">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-2">
                                                    <div class="avatar avatar-online">
                                                        <span class="avatar-initial rounded-circle bg-primary">
                                                            <?php echo esc(strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1))); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo esc($_SESSION['usuario_nome'] ?? 'Usuário'); ?></h6>
                                                    <small class="text-body-secondary"><?php echo esc($papel_atual ?? 'Usuário'); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li><div class="dropdown-divider my-1 mx-n2"></div></li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $caminho_base; ?>/pages/auth/perfil.php">
                                            <i class="icon-base ti tabler-user me-3 icon-md"></i><span class="align-middle">Meus dados</span>
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="<?php echo $caminho_base; ?>/pages/auth/logout.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="icon-base ti tabler-logout me-3 icon-md"></i><span class="align-middle">Sair</span>
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y" id="conteudo" tabindex="-1">
