<?php
/**
 * SPIKE — Fase 0 da migração para Metronic Tailwind.
 *
 * Página de validação do shell (sidebar/header/footer) + componentes-chave
 * (card, tabela, badge, avatar, modal, dropdown, alert, switch de tema).
 * É um artefato temporário: será removida quando o shell real (includes/)
 * estiver migrado.
 */
?>
<!DOCTYPE html>
<html class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Spike Metronic | Auto Mecânica</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../dist/assets/vendors/keenicons/styles.bundle.css" rel="stylesheet">
    <link href="../dist/assets/css/styles.css" rel="stylesheet">
    <!-- Ícones Tabler (mantidos por decisão de projeto) -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/iconify-icons.css">
</head>
<body class="antialiased flex h-full text-base text-foreground bg-background demo1 kt-sidebar-fixed kt-header-fixed">
<script>
    // Tema antes da renderização (padrão claro; suporta system via matchMedia)
    (function () {
        var defaultThemeMode = 'light';
        var themeMode;
        if (localStorage.getItem('kt-theme')) {
            themeMode = localStorage.getItem('kt-theme');
        } else if (document.documentElement.hasAttribute('data-kt-theme-mode')) {
            themeMode = document.documentElement.getAttribute('data-kt-theme-mode');
        } else {
            themeMode = defaultThemeMode;
        }
        if (themeMode === 'system') {
            themeMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.classList.add(themeMode);
    })();
</script>

<div class="flex grow">
    <!-- ===================== SIDEBAR ===================== -->
    <div class="kt-sidebar bg-background border-e border-e-border fixed top-0 bottom-0 z-20 hidden lg:flex flex-col items-stretch shrink-0 [--kt-drawer-enable:true] lg:[--kt-drawer-enable:false]"
         data-kt-drawer="true" data-kt-drawer-class="kt-drawer kt-drawer-start top-0 bottom-0" id="sidebar">
        <div class="kt-sidebar-header hidden lg:flex items-center relative justify-between px-3 lg:px-6 shrink-0" id="sidebar_header">
            <a href="#" class="flex items-center gap-2">
                <span class="ki-filled ki-tool text-xl text-primary"></span>
                <span class="font-semibold text-mono">Auto Mecânica</span>
            </a>
            <button class="kt-btn kt-btn-outline kt-btn-icon size-[30px] absolute start-full top-2/4 -translate-x-2/4 -translate-y-2/4 rtl:translate-x-2/4"
                    data-kt-toggle="body" data-kt-toggle-class="kt-sidebar-collapse" id="sidebar_toggle">
                <i class="ki-filled ki-black-left-line kt-toggle-active:rotate-180 transition-all duration-300"></i>
            </button>
        </div>
        <div class="kt-sidebar-content flex grow shrink-0 py-5 pe-2" id="sidebar_content">
            <div class="kt-scrollable-y-hover grow shrink-0 flex ps-2 lg:ps-5 pe-1 lg:pe-3"
                 data-kt-scrollable="true" data-kt-scrollable-dependencies="#sidebar_header"
                 data-kt-scrollable-height="auto" data-kt-scrollable-offset="0px" data-kt-scrollable-wrappers="#sidebar_content"
                 id="sidebar_scrollable">
                <div class="kt-menu flex flex-col grow gap-1" data-kt-menu="true" data-kt-menu-accordion-expand-all="false" id="sidebar_menu">

                    <div class="kt-menu-item pt-2.25 pb-px">
                        <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">Principal</span>
                    </div>
                    <div class="kt-menu-item">
                        <a class="kt-menu-link border border-transparent items-center grow gap-[10px] ps-[10px] pe-[10px] py-[6px]"
                           href="#">
                            <span class="kt-menu-icon items-start text-muted-foreground w-[20px]"><i class="icon-base ti tabler-layout-grid text-lg"></i></span>
                            <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">Dashboard</span>
                        </a>
                    </div>
                    <div class="kt-menu-item active">
                        <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#">
                            <span class="kt-menu-icon items-start text-muted-foreground w-[20px]"><i class="icon-base ti tabler-bell text-lg"></i></span>
                            <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">Notificações</span>
                        </a>
                    </div>
                    <div class="kt-menu-item">
                        <a class="kt-menu-link border border-transparent items-center grow gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#">
                            <span class="kt-menu-icon items-start text-muted-foreground w-[20px]"><i class="icon-base ti tabler-users text-lg"></i></span>
                            <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">Clientes</span>
                        </a>
                    </div>
                    <div class="kt-menu-item">
                        <a class="kt-menu-link border border-transparent items-center grow gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#">
                            <span class="kt-menu-icon items-start text-muted-foreground w-[20px]"><i class="icon-base ti tabler-car text-lg"></i></span>
                            <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">Veículos</span>
                        </a>
                    </div>
                    <div class="kt-menu-item pt-2.25 pb-px">
                        <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">Operações</span>
                    </div>
                    <div class="kt-menu-item">
                        <a class="kt-menu-link border border-transparent items-center grow gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#">
                            <span class="kt-menu-icon items-start text-muted-foreground w-[20px]"><i class="icon-base ti tabler-clipboard-list text-lg"></i></span>
                            <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">Ordens de serviço</span>
                        </a>
                    </div>
                    <div class="kt-menu-item">
                        <a class="kt-menu-link border border-transparent items-center grow gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#">
                            <span class="kt-menu-icon items-start text-muted-foreground w-[20px]"><i class="icon-base ti tabler-truck text-lg"></i></span>
                            <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">Pedidos de peças</span>
                        </a>
                    </div>
                    <div class="kt-menu-item pt-2.25 pb-px">
                        <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">Sistema</span>
                    </div>
                    <div class="kt-menu-item">
                        <a class="kt-menu-link border border-transparent items-center grow gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#">
                            <span class="kt-menu-icon items-start text-muted-foreground w-[20px]"><i class="icon-base ti tabler-history text-lg"></i></span>
                            <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">Auditoria</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- ===================== /SIDEBAR ===================== -->

    <div class="kt-wrapper flex grow flex-col">
        <!-- ===================== HEADER ===================== -->
        <header class="kt-header fixed top-0 z-10 start-0 end-0 flex items-stretch shrink-0 bg-background"
                data-kt-sticky="true" data-kt-sticky-class="border-b border-border" data-kt-sticky-name="header" id="header">
            <div class="kt-container-fixed flex justify-between items-stretch lg:gap-4" id="headerContainer">
                <!-- Mobile: logo + toggle do drawer -->
                <div class="flex gap-2.5 lg:hidden items-center -ms-1">
                    <a class="shrink-0 flex items-center gap-2" href="#">
                        <span class="ki-filled ki-tool text-xl text-primary"></span>
                    </a>
                    <button class="kt-btn kt-btn-icon kt-btn-ghost" data-kt-drawer-toggle="#sidebar">
                        <i class="ki-filled ki-menu"></i>
                    </button>
                </div>
                <div class="hidden lg:flex items-center gap-1">
                    <span class="text-sm font-medium text-mono">Spike Metronic</span>
                </div>
                <div class="flex items-center gap-2.5">
                    <!-- Notificações -->
                    <div class="kt-dropdown" data-kt-dropdown="true" data-kt-dropdown-trigger="click" data-kt-dropdown-placement="bottom-end">
                        <button class="kt-btn kt-btn-icon kt-btn-ghost">
                            <i class="icon-base ti tabler-bell text-xl"></i>
                        </button>
                        <div class="kt-dropdown-menu w-[300px] py-5 px-4">
                            <span class="font-semibold text-2sm">Nada por aqui ainda</span>
                        </div>
                    </div>
                    <!-- Usuário -->
                    <div class="kt-dropdown" data-kt-dropdown="true" data-kt-dropdown-trigger="click" data-kt-dropdown-placement="bottom-end">
                        <button class="kt-btn kt-btn-icon kt-btn-ghost">
                            <div class="kt-avatar kt-avatar-sm">
                                <span class="kt-avatar-fallback bg-primary text-primary-foreground">A</span>
                            </div>
                        </button>
                        <div class="kt-dropdown-menu w-[220px] py-2.5 px-3">
                            <span class="font-medium text-2sm block px-2 pb-2">Administrador</span>
                            <a class="kt-dropdown-menu-link" href="#">Meus dados</a>
                            <div class="kt-dropdown-menu-separator"></div>
                            <a class="kt-dropdown-menu-link text-destructive" href="#">Sair</a>
                        </div>
                    </div>
                    <!-- Tema -->
                    <div class="kt-dropdown" data-kt-dropdown="true" data-kt-dropdown-trigger="click" data-kt-dropdown-placement="bottom-end">
                        <button class="kt-btn kt-btn-icon kt-btn-ghost">
                            <i class="icon-base ti tabler-sun text-xl"></i>
                        </button>
                        <div class="kt-dropdown-menu w-[180px] py-2.5 px-3">
                            <button type="button" class="kt-dropdown-menu-link" data-theme-choice="light"><i class="icon-base ti tabler-sun me-2"></i>Claro</button>
                            <button type="button" class="kt-dropdown-menu-link" data-theme-choice="dark"><i class="icon-base ti tabler-moon-stars me-2"></i>Escuro</button>
                            <button type="button" class="kt-dropdown-menu-link" data-theme-choice="system"><i class="icon-base ti tabler-device-desktop-analytics me-2"></i>Sistema</button>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <!-- ===================== /HEADER ===================== -->

        <main class="grow pt-5" id="content" role="content">
            <div class="kt-container-fixed" id="contentContainer">

                <!-- Page header -->
                <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
                    <div class="flex flex-col justify-center gap-2">
                        <h1 class="text-xl font-medium leading-none text-mono">Painel</h1>
                        <div class="flex items-center gap-2 font-medium">
                            <span class="text-sm text-muted-foreground">Acompanhe o trabalho da oficina</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <button class="kt-btn kt-btn-primary" data-kt-modal-toggle="#spike_modal">
                            <i class="ki-filled ki-plus-squared"></i>
                            Novo atendimento
                        </button>
                    </div>
                </div>
                <!-- /Page header -->

                <!-- KPIs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 lg:gap-7.5">
                    <div class="kt-card">
                        <div class="kt-card-body flex items-center gap-3.5">
                            <div class="flex items-center justify-center size-11 shrink-0 rounded-lg bg-accent/60"><i class="icon-base ti tabler-users text-xl text-primary"></i></div>
                            <div class="flex flex-col gap-0.5">
                                <span class="text-2xs font-medium uppercase text-muted-foreground">Clientes</span>
                                <span class="text-lg font-semibold text-mono">128</span>
                            </div>
                        </div>
                    </div>
                    <div class="kt-card">
                        <div class="kt-card-body flex items-center gap-3.5">
                            <div class="flex items-center justify-center size-11 shrink-0 rounded-lg bg-accent/60"><i class="icon-base ti tabler-users-group text-xl text-primary"></i></div>
                            <div class="flex flex-col gap-0.5">
                                <span class="text-2xs font-medium uppercase text-muted-foreground">Funcionários</span>
                                <span class="text-lg font-semibold text-mono">5</span>
                            </div>
                        </div>
                    </div>
                    <div class="kt-card">
                        <div class="kt-card-body flex items-center gap-3.5">
                            <div class="flex items-center justify-center size-11 shrink-0 rounded-lg bg-accent/60"><i class="icon-base ti tabler-clipboard-list text-xl text-primary"></i></div>
                            <div class="flex flex-col gap-0.5">
                                <span class="text-2xs font-medium uppercase text-muted-foreground">OS em andamento</span>
                                <span class="text-lg font-semibold text-mono">12</span>
                            </div>
                        </div>
                    </div>
                    <div class="kt-card">
                        <div class="kt-card-body flex items-center gap-3.5">
                            <div class="flex items-center justify-center size-11 shrink-0 rounded-lg bg-accent/60"><i class="icon-base ti tabler-package text-xl text-primary"></i></div>
                            <div class="flex flex-col gap-0.5">
                                <span class="text-2xs font-medium uppercase text-muted-foreground">Peças pendentes</span>
                                <span class="text-lg font-semibold text-mono">3</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /KPIs -->

                <!-- Alert -->
                <div class="kt-alert kt-alert-outline kt-alert-success mt-7.5" data-kt-alert="true" data-kt-alert-dismiss="true">
                    <i class="kt-alert-icon ki-filled ki-check-circle"></i>
                    <div class="kt-alert-content">
                        <span class="kt-alert-description">Shell Metronic renderizado com sucesso.</span>
                    </div>
                    <button class="kt-alert-close" data-kt-alert-dismiss="true"><i class="ki-filled ki-cross"></i></button>
                </div>

                <!-- Tabela de exemplo -->
                <div class="kt-card mt-7.5">
                    <div class="kt-card-header px-5 py-4">
                        <h3 class="kt-card-title">Ordens de serviço em andamento</h3>
                    </div>
                    <div class="kt-card-body py-5">
                        <div class="scrollable-x-auto">
                            <table class="kt-table w-full">
                                <thead>
                                    <tr class="text-start text-muted-foreground font-medium uppercase text-2xs">
                                        <th class="px-3 py-2">OS</th>
                                        <th class="px-3 py-2">Cliente</th>
                                        <th class="px-3 py-2">Veículo</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2">Prioridade</th>
                                    </tr>
                                </thead>
                                <tbody class="font-medium text-mono">
                                    <tr>
                                        <td class="px-3 py-2.5">#0042</td>
                                        <td class="px-3 py-2.5">Maria Silva</td>
                                        <td class="px-3 py-2.5">ABC-1D23</td>
                                        <td class="px-3 py-2.5"><span class="kt-badge kt-badge-sm kt-badge-outline kt-badge-primary">Em análise</span></td>
                                        <td class="px-3 py-2.5"><span class="kt-badge kt-badge-sm kt-badge-outline kt-badge-destructive">Urgente</span></td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2.5">#0041</td>
                                        <td class="px-3 py-2.5">João Souza</td>
                                        <td class="px-3 py-2.5">DEF-5G67</td>
                                        <td class="px-3 py-2.5"><span class="kt-badge kt-badge-sm kt-badge-outline kt-badge-info">Em execução</span></td>
                                        <td class="px-3 py-2.5"><span class="kt-badge kt-badge-sm kt-badge-outline kt-badge-mono">Normal</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="kt-footer">
            <div class="kt-container-fixed">
                <div class="flex flex-col md:flex-row justify-center md:justify-between items-center gap-3 py-5">
                    <div class="flex order-2 md:order-1 gap-2 font-normal text-sm">
                        <span class="text-secondary-foreground">2026©</span>
                        <a class="text-secondary-foreground hover:text-primary" href="#">Auto Mecânica</a>
                    </div>
                    <nav class="flex order-1 md:order-2 gap-4 font-normal text-sm text-secondary-foreground">
                        <span>Oficina em dia</span>
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</div>

<!-- Modal de exemplo -->
<div class="kt-modal" data-kt-modal="true" id="spike_modal">
    <div class="kt-modal-content max-w-[500px]">
        <div class="kt-modal-header py-4 px-5">
            <h3 class="kt-modal-title">Novo atendimento</h3>
            <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-dim shrink-0" data-kt-modal-dismiss="true">
                <i class="ki-filled ki-cross"></i>
            </button>
        </div>
        <div class="kt-modal-body px-5 pb-5">
            <p class="text-sm text-muted-foreground">Modal Metronic funcionando.</p>
            <label class="kt-input-group mt-4">
                <input class="kt-input" type="text" placeholder="Nome do cliente">
            </label>
        </div>
        <div class="kt-modal-footer px-5 py-4">
            <button class="kt-btn kt-btn-ghost" data-kt-modal-dismiss="true">Cancelar</button>
            <button class="kt-btn kt-btn-primary">Continuar</button>
        </div>
    </div>
</div>

<script src="../dist/assets/js/core.bundle.js"></script>
<script src="../dist/assets/vendors/ktui/ktui.min.js"></script>
<script src="../dist/assets/js/layouts/demo1.js"></script>
<script>
    // Troca de tema light/dark/system (padrão Metronic: classe .dark no <html>)
    document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mode = btn.getAttribute('data-theme-choice');
            localStorage.setItem('kt-theme', mode);
            var resolved = (mode === 'system')
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : mode;
            document.documentElement.classList.toggle('dark', resolved === 'dark');
            document.documentElement.setAttribute('data-kt-theme-mode', mode);
        });
    });
</script>
</body>
</html>
