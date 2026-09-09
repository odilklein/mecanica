<?php
// Detectar página atual para marcar item ativo
$uri_atual = $_SERVER['REQUEST_URI'] ?? '';
$arquivo_atual = basename($_SERVER['PHP_SELF'] ?? '');

function sidebarAtiva($modulo) {
    global $uri_atual, $arquivo_atual;
    if ($modulo === 'dashboard') return ($arquivo_atual === 'index.php' || $arquivo_atual === '');
    if ($modulo === 'orcamentos' && strpos($uri_atual, 'relatorio') !== false) return false;
    return strpos($uri_atual, $modulo) !== false;
}

// Configurar URLs relativas ao contexto
if (!isset($caminho_base)) {
    $caminho_base = '../../';
}

$url_index = $caminho_base . 'index.php';
$url_clientes = $caminho_base . 'pages/clientes/listar.php';
$url_veiculos = $caminho_base . 'pages/veiculos/listar.php';
$url_pecas = $caminho_base . 'pages/pecas/listar.php';
$url_servicos = $caminho_base . 'pages/servicos/listar.php';
$url_agendamentos = $caminho_base . 'pages/agendamentos/listar.php';
$url_orcamentos = $caminho_base . 'pages/orcamentos/listar.php';
$url_relatorio = $caminho_base . 'pages/orcamentos/relatorio.php';
$url_logout = $caminho_base . 'pages/auth/logout.php';
?>

<aside class="sidebar" id="sidebar">
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Minimizar sidebar">
        <i class="fas fa-chevron-left"></i>
    </button>

    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-wrench"></i>
        </div>
        <span class="brand-text">AutoMecânica</span>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section">
            <div class="nav-section-title">Principal</div>
            <a href="<?php echo $url_index; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('dashboard') ? 'active' : ''; ?>" title="Dashboard">
                <i class="fas fa-th-large"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="<?php echo $url_clientes; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('clientes') ? 'active' : ''; ?>" title="Clientes">
                <i class="fas fa-users"></i>
                <span class="nav-text">Clientes</span>
            </a>
            <a href="<?php echo $url_veiculos; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('veiculos') ? 'active' : ''; ?>" title="Veículos">
                <i class="fas fa-car"></i>
                <span class="nav-text">Veículos</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="nav-section-title">Operações</div>
            <a href="<?php echo $url_pecas; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('pecas') ? 'active' : ''; ?>" title="Estoque">
                <i class="fas fa-boxes-stacked"></i>
                <span class="nav-text">Estoque</span>
            </a>
            <a href="<?php echo $url_servicos; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('servicos') ? 'active' : ''; ?>" title="Serviços">
                <i class="fas fa-wrench"></i>
                <span class="nav-text">Serviços</span>
            </a>
            <a href="<?php echo $url_agendamentos; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('agendamentos') ? 'active' : ''; ?>" title="Agendamentos">
                <i class="fas fa-calendar-check"></i>
                <span class="nav-text">Agendamentos</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="nav-section-title">Financeiro</div>
            <a href="<?php echo $url_orcamentos; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('orcamentos') ? 'active' : ''; ?>" title="Orçamentos">
                <i class="fas fa-file-invoice-dollar"></i>
                <span class="nav-text">Orçamentos</span>
            </a>
            <a href="<?php echo $url_relatorio; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('relatorio') ? 'active' : ''; ?>" title="Relatórios">
                <i class="fas fa-chart-line"></i>
                <span class="nav-text">Relatórios</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user" title="<?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?>">
            <div class="sidebar-user-avatar">
                <?php echo strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="sidebar-user-info">
                <span class="user-name"><?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
                <span class="user-email"><?php echo $_SESSION['usuario_email'] ?? ''; ?></span>
            </div>
        </div>
        <div class="sidebar-actions">
            <button class="sidebar-action-btn" id="themeToggle" title="Alternar tema">
                <i class="fas fa-moon"></i>
                <span class="nav-text">Tema</span>
            </button>
            <a href="<?php echo $url_logout; ?>" class="sidebar-action-btn" title="Sair">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Sair</span>
            </a>
        </div>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>