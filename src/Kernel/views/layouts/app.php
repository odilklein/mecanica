<!DOCTYPE html>
<html lang="pt-BR" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr" data-skin="default" data-bs-theme="light" data-assets-path="/assets/" data-template="vertical-menu-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#b8270f">
    <title><?= e($title ?? 'Mecânica') ?></title>
    <link rel="icon" href="/assets/img/favicon/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/vendor/fonts/iconify-icons.css">
    <link rel="stylesheet" href="/assets/vendor/css/core.css">
    <link rel="stylesheet" href="/assets/css/demo.css">
    <link rel="stylesheet" href="/css/mecanica.css">

    <script src="/assets/vendor/js/helpers.js"></script>
    <script src="/assets/js/config.js"></script>
</head>
<body>
    <?php $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
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
                        <span class="app-brand-text demo menu-text fw-bold ms-3">Mecânica</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
                        <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
                        <i class="icon-base ti tabler-x d-block d-xl-none"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <?php foreach (($menu ?? []) as $section => $items): ?>
                    <li class="menu-header small"><span class="menu-header-text"><?= e($section) ?></span></li>
                    <?php foreach ($items as $item): ?>
                    <?php
                        $url = $item['url'] ?? '#';
                        $active = $url === '/' ? $currentPath === '/' : ($url !== '#' && str_starts_with($currentPath, $url));
                    ?>
                    <li class="menu-item<?= $active ? ' active' : '' ?>">
                        <a href="<?= e($url) ?>" class="menu-link">
                            <i class="menu-icon icon-base ti tabler-<?= e($item['icon'] ?? 'circle') ?>"></i>
                            <div><?= e($item['label']) ?></div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout page -->
            <div class="layout-page">
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
                                    <i class="icon-base ti tabler-sun icon-22px" id="themeIcon"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme">
                                    <li><button type="button" class="dropdown-item" data-bs-theme-value="light"><i class="icon-base ti tabler-sun me-2"></i>Claro</button></li>
                                    <li><button type="button" class="dropdown-item" data-bs-theme-value="dark"><i class="icon-base ti tabler-moon-stars me-2"></i>Escuro</button></li>
                                    <li><button type="button" class="dropdown-item" data-bs-theme-value="system"><i class="icon-base ti tabler-device-desktop-analytics me-2"></i>Sistema</button></li>
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

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y" id="conteudo" tabindex="-1">
                        <?php if ($success = flash('success')): ?>
                        <div class="alert alert-success alert-dismissible" role="alert">
                            <?= e($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                        <?php endif; ?>
                        <?php if ($error = flash('error')): ?>
                        <div class="alert alert-danger alert-dismissible" role="alert">
                            <?= e($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                        <?php endif; ?>
                        <?= $content ?? '' ?>
                    </div>

                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                                <div class="text-body">&copy; <?= date('Y') ?> Mecânica</div>
                                <div class="text-body-secondary">Oficina em dia</div>
                            </div>
                        </div>
                    </footer>

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
    <script src="/assets/vendor/js/menu.js"></script>
    <script src="/assets/js/main.js"></script>
    <script src="/js/masks.js"></script>
    <script src="/js/viacep.js"></script>

    <script>
        document.querySelectorAll('[data-bs-theme-value]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                window.Helpers.setTheme(btn.getAttribute('data-bs-theme-value'));
            });
        });
    </script>
</body>
</html>
