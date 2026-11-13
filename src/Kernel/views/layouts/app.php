<?php
$ticketsOpenPref = (string) ($_COOKIE['mecanica_tickets_open'] ?? '1');
// Páginas que ocupam a tela inteira (ex.: assistente) podem esconder a
// navbar. O rodapé foi removido do sistema.
$hideChrome = !empty($hideChrome);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="<?= $hideChrome ? 'layout-menu-fixed layout-compact layout-chrome-hidden' : 'layout-navbar-fixed layout-menu-fixed layout-compact' ?>" dir="ltr" data-skin="default" data-bs-theme="light" data-assets-path="/assets/" data-template="vertical-menu-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="<?= e(setting('theme_color', '#b8270f')) ?>">
    <script>
        // Evita o "flash" da seção de Tickets aberta antes do JS aplicar a preferência.
        (function () {
            try {
                var saved = localStorage.getItem('mecanica-tickets-open');
                var hasCookie = document.cookie.indexOf('mecanica_tickets_open=') !== -1;
                if (!hasCookie && saved === '0') {
                    document.documentElement.classList.add('tickets-collapsed');
                }
            } catch (e) {}
        })();
    </script>
    <script>
        // Aplica o tema salvo antes do CSS para evitar flash claro/escuro.
        (function () {
            try {
                var root = document.documentElement;
                var template = root.getAttribute('data-template') || 'vertical-menu-template';
                var pref = localStorage.getItem('mecanica-theme');
                if (!pref) {
                    pref = localStorage.getItem('templateCustomizer-' + template + '--Theme') || 'system';
                    localStorage.setItem('mecanica-theme', pref);
                }
                var resolved = pref === 'system'
                    ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : pref;
                root.setAttribute('data-bs-theme', resolved);
                root.setAttribute('data-theme-pref', pref);
                var meta = document.querySelector('meta[name="theme-color"]');
                if (meta) {
                    meta.dataset.lightColor = meta.dataset.lightColor || meta.getAttribute('content') || '#b8270f';
                    meta.setAttribute('content', resolved === 'dark' ? '#1f1f1f' : meta.dataset.lightColor);
                }
            } catch (e) {}
        })();
    </script>
    <title><?= e(setting('seo_title', 'Mecânica')) ?><?= !empty($title) ? ' | ' . e($title) : '' ?></title>
    <meta name="description" content="<?= e(setting('seo_description', '')) ?>">
    <meta name="keywords" content="<?= e(setting('seo_keywords', '')) ?>">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon/favicon-32x32.png">
    <link rel="shortcut icon" href="/assets/img/favicon/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/vendor/fonts/iconify-icons.css">
    <link rel="stylesheet" href="/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="/assets/vendor/css/core.css">
    <link rel="stylesheet" href="/assets/css/demo.css">
    <link rel="stylesheet" href="<?= e(asset('/assets/vendor/libs/select2/select2.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/css/mecanica.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/css/ai-prefill.css')) ?>">

    <?= theme_css_inline(setting('theme_color', '#b8270f')) ?>

    <script src="/assets/vendor/js/helpers.js"></script>
    <script src="<?= e(asset('/assets/js/config.js')) ?>"></script>

    <?php foreach (($pageStyles ?? []) as $style): ?>
    <link rel="stylesheet" href="<?= e(asset($style)) ?>">
    <?php endforeach; ?>
</head>
<body>
    <?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $currentUrl = $currentPath . ($queryString !== '' ? '?' . $queryString : '');
    ?>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu">
                <div class="app-brand demo">
                    <a href="/" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <span class="brand-icon"><i class="icon-base ti tabler-tool"></i></span>
                        </span>
                        <span class="app-brand-text demo menu-text fw-bold ms-3"><?= e(setting('company_name', 'Mecânica') ?: 'Mecânica') ?></span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
                        <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
                        <i class="icon-base ti tabler-x d-block d-xl-none"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <?php
                if (!function_exists('menu_item_active')) {
                    /** Verifica se o item (ou algum subitem) corresponde à URL atual. */
                    function menu_item_active(array $item, string $currentPath, string $currentUrl): bool
                    {
                        $url = $item['url'] ?? '#';
                        if ($url === '/') {
                            $active = $currentPath === '/';
                        } else {
                            $active = $url !== '#' && str_starts_with($currentPath, $url);
                        }

                        foreach (($item['children'] ?? []) as $child) {
                            if (menu_child_active($child, $currentUrl)) {
                                $active = true;
                                break;
                            }
                        }

                        return $active;
                    }

                    function menu_child_active(array $item, string $currentUrl): bool
                    {
                        $url = $item['url'] ?? '#';
                        return $url !== '#' && $url === $currentUrl;
                    }
                }
                ?>
                <ul class="menu-inner py-1">
                    <?php foreach (($menu ?? []) as $section => $items): ?>
                    <li class="menu-header small"><span class="menu-header-text"><?= e($section) ?></span></li>
                    <?php foreach ($items as $item): ?>
                    <?php
                        $url = $item['url'] ?? '#';
                        $children = $item['children'] ?? [];
                        $active = menu_item_active($item, $currentPath, $currentUrl);
                    ?>
                    <?php if ($children): ?>
                    <?php
                    $isOpen = $active || !empty($item['open']);
                    if (($item['menu_key'] ?? '') === 'tickets' && $ticketsOpenPref === '0') {
                        $isOpen = false;
                    }
                    ?>
                    <li class="menu-item<?= $active ? ' active' : '' ?><?= $isOpen ? ' open' : '' ?>"<?= !empty($item['menu_key']) ? ' data-menu-key="' . e($item['menu_key']) . '"' : '' ?>>
                        <a href="<?= e($url !== '#' ? $url : 'javascript:void(0);') ?>" class="menu-link menu-toggle">
                            <i class="menu-icon icon-base ti tabler-<?= e($item['icon'] ?? 'circle') ?>"></i>
                            <div><?= e($item['label']) ?></div>
                        </a>
                        <ul class="menu-sub">
                            <?php foreach ($children as $child): ?>
                            <?php
                                $childActive = menu_child_active($child, $currentUrl);
                                $childClass = trim((string) ($child['class'] ?? ''));
                                $isTicket = $childClass === 'menu-ticket';
                            ?>
                            <li class="menu-item<?= $childClass !== '' ? ' ' . e($childClass) : '' ?><?= $childActive ? ' active' : '' ?>">
                                <a href="<?= e($child['url'] ?? '#') ?>" class="menu-link">
                                    <div class="<?= $isTicket ? 'menu-ticket-title' : '' ?>"><?= e($child['label']) ?></div>
                                    <?php if (!empty($child['meta'])): ?>
                                    <small class="menu-ticket-meta"><?= e($child['meta']) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($child['unread'])): ?>
                                    <span class="menu-ticket-dot" title="<?= (int) $child['unread'] ?> não lida(s)"></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="menu-item<?= $active ? ' active' : '' ?>">
                        <a href="<?= e($url) ?>" class="menu-link">
                            <i class="menu-icon icon-base ti tabler-<?= e($item['icon'] ?? 'circle') ?>"></i>
                            <div><?= e($item['label']) ?></div>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>

                <?php
                // Painel do usuário fixo no rodapé da sidebar.
                $menuUserName  = (string) ($user['name'] ?? 'Usuário');
                $menuUserEmail = (string) ($user['email'] ?? '');
                $menuUserRole  = roleLabel($user['role'] ?? null);
                $menuUserInitial = strtoupper(mb_substr($menuUserName !== '' ? $menuUserName : 'U', 0, 1));
                $menuUserIsAdmin = ($user['role'] ?? null) === 'admin';
                ?>
                <div class="menu-user">
                    <div class="menu-user-card">
                        <a href="/profile" class="menu-user-profile" title="Meus dados" aria-label="Meus dados">
                            <span class="avatar avatar-sm avatar-online">
                                <span class="avatar-initial rounded-circle bg-primary"><?= e($menuUserInitial) ?></span>
                            </span>
                        </a>
                        <div class="menu-user-info">
                            <span class="menu-user-name" title="<?= e($menuUserName) ?>"><?= e($menuUserName) ?></span>
                            <span class="menu-user-email" title="<?= e($menuUserEmail) ?>"><?= e($menuUserEmail) ?></span>
                        </div>
                        <div class="dropdown dropup menu-user-dropdown">
                            <button type="button" class="btn btn-icon btn-text-secondary rounded-pill menu-user-actions"
                                    data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Opções da conta">
                                <i class="icon-base ti tabler-dots-vertical icon-md"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li class="menu-user-dropdown-header">
                                    <span class="menu-user-dropdown-name"><?= e($menuUserName) ?></span>
                                    <small class="menu-user-dropdown-role"><?= e($menuUserRole) ?></small>
                                </li>
                                <li><div class="dropdown-divider"></div></li>
                                <li>
                                    <a class="dropdown-item" href="/profile">
                                        <i class="icon-base ti tabler-user me-2"></i>Meus dados
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/notifications">
                                        <i class="icon-base ti tabler-bell me-2"></i>Notificações
                                    </a>
                                </li>
                                <?php if ($menuUserIsAdmin): ?>
                                <li>
                                    <a class="dropdown-item" href="/settings">
                                        <i class="icon-base ti tabler-settings me-2"></i>Configurações
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li><div class="dropdown-divider"></div></li>
                                <li>
                                    <form method="POST" action="/logout">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="icon-base ti tabler-logout me-2"></i>Sair
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </aside>
            <!-- / Menu -->

            <!-- Layout page -->
            <div class="layout-page">
                <?php if ($hideChrome): ?>
                <button type="button" class="chrome-menu-toggle layout-menu-toggle d-xl-none" aria-label="Abrir menu">
                    <i class="icon-base ti tabler-menu-2"></i>
                </button>
                <?php else: ?>
                <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                            <i class="icon-base ti tabler-menu-2 icon-md"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
                        <!-- Busca global (padrão do sistema) -->
                        <div class="navbar-nav align-items-center navbar-search">
                            <div class="nav-item navbar-search-wrapper px-md-0 px-2 mb-0">
                                <div class="nav-item nav-link search-toggler d-flex align-items-center px-0">
                                    <span class="d-inline-block text-body-secondary fw-normal" id="global-search"></span>
                                </div>
                            </div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
                            <!-- Notificações -->
                            <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-2" id="notifRoot" data-csrf="<?= e($csrf) ?>">
                                <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span class="position-relative">
                                        <i class="icon-base ti tabler-bell icon-22px text-heading"></i>
                                        <span class="badge rounded-pill bg-danger badge-dot badge-notifications border" id="notifBadge" hidden></span>
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end p-0">
                                    <li class="dropdown-menu-header border-bottom">
                                        <div class="dropdown-header d-flex align-items-center py-3">
                                            <h6 class="mb-0 me-auto">Notificações</h6>
                                            <div class="d-flex align-items-center h6 mb-0">
                                                <span class="badge bg-label-primary me-2" id="notifCount">0 novas</span>
                                                <a href="javascript:void(0)" class="dropdown-notifications-all p-2 btn btn-icon" id="notifMarkAll" title="Marcar todas como lidas">
                                                    <i class="icon-base ti tabler-mail-opened text-heading"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="dropdown-notifications-list scrollable-container">
                                        <ul class="list-group list-group-flush" id="notifList">
                                            <li class="list-group-item">
                                                <div class="p-3 text-body-secondary text-center">Carregando...</div>
                                            </li>
                                        </ul>
                                    </li>
                                    <li class="border-top">
                                        <div class="d-grid p-4">
                                            <a class="btn btn-primary btn-sm d-flex" href="/notifications">
                                                <small class="align-middle">Ver todas as notificações</small>
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            </li>

                            <!-- Tema -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill" id="nav-theme" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden" id="nav-theme-text">Tema</span>
                                    <i class="icon-base ti tabler-sun icon-22px theme-icon-active" id="themeIcon"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme">
                                    <li><button type="button" class="dropdown-item" data-bs-theme-value="light"><i class="icon-base ti tabler-sun me-2" data-icon="sun"></i>Claro</button></li>
                                    <li><button type="button" class="dropdown-item" data-bs-theme-value="dark"><i class="icon-base ti tabler-moon-stars me-2" data-icon="moon-stars"></i>Escuro</button></li>
                                    <li><button type="button" class="dropdown-item" data-bs-theme-value="system"><i class="icon-base ti tabler-device-desktop-analytics me-2" data-icon="device-desktop-analytics"></i>Sistema</button></li>
                                </ul>
                            </li>

                            <!-- Usuário -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <span class="avatar-initial rounded-circle bg-primary"><?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></span>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <div class="dropdown-item">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-2">
                                                    <div class="avatar avatar-online">
                                                        <span class="avatar-initial rounded-circle bg-primary"><?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></span>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?= e($user['name'] ?? 'Usuário') ?></h6>
                                                    <small class="text-body-secondary"><?= e(roleLabel($user['role'] ?? null)) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li><div class="dropdown-divider my-1"></div></li>
                                    <li>
                                        <a class="dropdown-item" href="/profile">
                                            <i class="icon-base ti tabler-user me-2"></i>Meus dados
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="/logout">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="icon-base ti tabler-logout me-2"></i>Sair
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <?php endif; ?>

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y" id="conteudo" tabindex="-1">
                        <?php $flashSuccess = flash('success'); $flashError = flash('error'); ?>
                        <?php if ($flashSuccess || $flashError): ?>
                        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
                            <div class="toast align-items-center text-bg-<?= $flashError ? 'danger' : 'success' ?> border-0" role="alert" data-autohide="true">
                                <div class="d-flex">
                                    <div class="toast-body"><?= e($flashError ?: $flashSuccess) ?></div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?= $content ?? '' ?>
                    </div>

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- / Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->

    <script src="/assets/vendor/libs/jquery/jquery.js"></script>
    <script src="/assets/vendor/libs/popper/popper.js"></script>
    <script src="/assets/vendor/js/bootstrap.js"></script>
    <script src="/assets/vendor/libs/node-waves/node-waves.js"></script>
    <script src="/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="/assets/vendor/js/menu.js"></script>
    <script src="/assets/js/main.js"></script>
    <script src="<?= e(asset('/js/masks.js')) ?>"></script>
    <script src="<?= e(asset('/js/safe-url.js')) ?>"></script>
    <script src="<?= e(asset('/js/viacep.js')) ?>"></script>
    <script src="<?= e(asset('/js/notifications.js')) ?>"></script>
    <script src="<?= e(asset('/assets/vendor/libs/select2/select2.js')) ?>"></script>
    <script src="<?= e(asset('/js/select2-init.js')) ?>"></script>
    <script src="<?= e(asset('/js/part-autocomplete.js')) ?>"></script>

    <?php
    // Sugestões iniciais da busca global, conforme o papel do usuário.
    $searchRole = (string) ($user['role'] ?? '');
    $searchCanManage = in_array($searchRole, ['admin', 'secretary', 'lead_mechanic'], true);
    $searchSuggestions = [
        'Acesso rápido' => [
            ['name' => 'Painel', 'icon' => 'tabler-smart-home', 'url' => '/'],
            ['name' => 'Ordens de serviço', 'icon' => 'tabler-clipboard-list', 'url' => '/service-orders'],
            ['name' => 'Pedidos de peças', 'icon' => 'tabler-truck', 'url' => '/part-orders'],
            ['name' => 'Tickets', 'icon' => 'tabler-messages', 'url' => '/tickets'],
        ],
    ];
    if ($searchCanManage) {
        $searchSuggestions['Cadastros'] = [
            ['name' => 'Clientes', 'icon' => 'tabler-users', 'url' => '/clients'],
            ['name' => 'Veículos', 'icon' => 'tabler-car', 'url' => '/vehicles'],
            ['name' => 'Funcionários', 'icon' => 'tabler-users-group', 'url' => '/users'],
            ['name' => 'Peças', 'icon' => 'tabler-package', 'url' => '/parts'],
        ];
        $searchSuggestions['Gestão'] = [
            ['name' => 'Orçamentos', 'icon' => 'tabler-file-text', 'url' => '/budgets'],
            ['name' => 'Relatórios', 'icon' => 'tabler-chart-line', 'url' => '/reports'],
        ];
    }
    if ($searchRole === 'admin') {
        $searchSuggestions['Sistema'] = [
            ['name' => 'Configurações', 'icon' => 'tabler-settings', 'url' => '/settings'],
            ['name' => 'Auditoria', 'icon' => 'tabler-history', 'url' => '/audit'],
        ];
    }
    ?>
    <script>
        window.globalSearchConfig = <?= json_encode([
            'endpoint' => '/api/search',
            'suggestions' => $searchSuggestions,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="/assets/vendor/libs/@algolia/autocomplete-js.js"></script>
    <script src="<?= e(asset('/js/global-search.js')) ?>"></script>
    <script src="<?= e(asset('/js/ai-prefill.js')) ?>"></script>
    <script src="<?= e(asset('/js/os-ai.js')) ?>"></script>

    <?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
    <?php endforeach; ?>

    <script>
        (function () {
            var KEY = 'mecanica-theme';
            var template = document.documentElement.getAttribute('data-template') || 'vertical-menu-template';

            function applyTheme(value, focus) {
                var resolved = value;
                if (value === 'system') {
                    resolved = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                        ? 'dark'
                        : 'light';
                }

                document.documentElement.setAttribute('data-bs-theme', resolved);
                document.documentElement.setAttribute('data-theme-pref', value);

                var meta = document.querySelector('meta[name="theme-color"]');
                if (meta) {
                    meta.dataset.lightColor = meta.dataset.lightColor || meta.getAttribute('content') || '#b8270f';
                    meta.setAttribute('content', resolved === 'dark' ? '#1f1f1f' : meta.dataset.lightColor);
                }

                try {
                    localStorage.setItem(KEY, value);
                    localStorage.setItem('templateCustomizer-' + template + '--Theme', value);
                } catch (e) {}

                if (window.Helpers) {
                    window.Helpers.setTheme(value);
                    if (window.Helpers.showActiveTheme) {
                        window.Helpers.showActiveTheme(value, !!focus);
                    }
                }
            }

            document.querySelectorAll('[data-bs-theme-value]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    applyTheme(btn.getAttribute('data-bs-theme-value'), true);
                });
            });

            document.addEventListener('DOMContentLoaded', function () {
                var pref = document.documentElement.getAttribute('data-theme-pref') || 'system';
                applyTheme(pref, false);

                if (window.matchMedia) {
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                        var current = 'system';
                        try {
                            current = localStorage.getItem(KEY) || 'system';
                        } catch (e) {}
                        if (current === 'system') {
                            applyTheme('system', false);
                        }
                    });
                }
            });
        })();

        document.querySelectorAll('.toast').forEach(function (t) {
            if (window.bootstrap && bootstrap.Toast) {
                new bootstrap.Toast(t, { delay: 5000 }).show();
            }
        });

        // Mantém o topo da sidebar (seção de tickets) visível; o menu
        // continua rolável para alcançar o item ativo.
        window.addEventListener('load', function () {
            var menuInner = document.querySelector('#layout-menu .menu-inner');
            if (menuInner) {
                menuInner.scrollTop = 0;
            }
        });

        // Persiste o estado aberto/fechado da seção de Tickets.
        (function () {
            var KEY = 'mecanica-tickets-open';
            var COOKIE = 'mecanica_tickets_open';
            var item = document.querySelector('#layout-menu [data-menu-key="tickets"]');
            if (!item) {
                return;
            }

            function setCookie(open) {
                document.cookie = COOKIE + '=' + (open ? '1' : '0')
                    + '; path=/; max-age=31536000; SameSite=Lax';
            }

            function persist(open) {
                try {
                    localStorage.setItem(KEY, open ? '1' : '0');
                } catch (e) {
                    // Armazenamento indisponível: mantém o padrão.
                }
                setCookie(open);
            }

            try {
                var hasCookie = document.cookie.indexOf(COOKIE + '=') !== -1;
                if (!hasCookie) {
                    var saved = localStorage.getItem(KEY);
                    if (saved === '0') {
                        item.classList.remove('open');
                    } else if (saved === '1') {
                        item.classList.add('open');
                    }
                    setCookie(saved === '0' ? false : true);
                }

                // Grava imediatamente no clique (antes da animação do menu).
                var toggleLink = item.querySelector('.menu-toggle');
                if (toggleLink) {
                    toggleLink.addEventListener('click', function () {
                        persist(!item.classList.contains('open'));
                    });
                }

                // E confirma o estado final depois da animação (inclusive fechamento por accordion).
                var saveTimer = null;
                var observer = new MutationObserver(function () {
                    window.clearTimeout(saveTimer);
                    saveTimer = window.setTimeout(function () {
                        persist(item.classList.contains('open'));
                    }, 350);
                });
                observer.observe(item, { attributes: true, attributeFilter: ['class'] });
            } catch (e) {
                // Sem armazenamento, mantém o padrão do servidor.
            }

            document.documentElement.classList.remove('tickets-collapsed');
        })();
    </script>
</body>
</html>
