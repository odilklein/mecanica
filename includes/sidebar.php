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
    require_once __DIR__ . '/base_url.php';
}
require_once __DIR__ . '/../config/empresa.php';

$url_index = $caminho_base . '/index.php';
$url_clientes = $caminho_base . '/pages/clientes/listar.php';
$url_veiculos = $caminho_base . '/pages/veiculos/listar.php';
$url_pecas = $caminho_base . '/pages/pecas/listar.php';
$url_servicos = $caminho_base . '/pages/servicos/listar.php';
$url_agendamentos = $caminho_base . '/pages/agendamentos/listar.php';
$url_orcamentos = $caminho_base . '/pages/orcamentos/listar.php';
$url_ordens_servico = $caminho_base . '/pages/ordens_servico/listar.php';
$url_relatorio = $caminho_base . '/pages/orcamentos/relatorio.php';
$url_logout = $caminho_base . '/pages/auth/logout.php';
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i data-lucide="wrench"></i>
            </div>
            <span class="brand-text">Auto Mecânica Leme</span>
        </div>
        <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Minimizar sidebar">
            <i data-lucide="chevron-left"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Principal</div>
            <a href="<?php echo $url_index; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('dashboard') ? 'active' : ''; ?>" title="Dashboard">
                <i data-lucide="layout-grid"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a href="<?php echo $url_clientes; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('clientes') ? 'active' : ''; ?>" title="Clientes">
                <i data-lucide="users"></i>
                <span class="nav-text">Clientes</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo $url_veiculos; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('veiculos') ? 'active' : ''; ?>" title="Veículos">
                <i data-lucide="car"></i>
                <span class="nav-text">Veículos</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Operações</div>
            <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico'])): ?><a href="<?php echo $url_ordens_servico; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('ordens_servico') ? 'active' : ''; ?>" title="Ordens de serviço">
                <i data-lucide="clipboard-list"></i>
                <span class="nav-text">Ordens de serviço</span>
            </a><?php endif; ?>
            <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a href="<?php echo $url_pecas; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('pecas') ? 'active' : ''; ?>" title="Estoque">
                <i data-lucide="boxes"></i>
                <span class="nav-text">Estoque</span>
            </a>
            <?php endif; ?>
            <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a href="<?php echo $url_servicos; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('servicos') ? 'active' : ''; ?>" title="Serviços">
                <i data-lucide="hammer"></i>
                <span class="nav-text">Serviços</span>
            </a>
            <?php endif; ?>
            <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a href="<?php echo $url_agendamentos; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('agendamentos') ? 'active' : ''; ?>" title="Agendamentos">
                <i data-lucide="calendar-check"></i>
                <span class="nav-text">Agendamentos</span>
            </a>
            <?php endif; ?>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Financeiro</div>
            <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a href="<?php echo $url_orcamentos; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('orcamentos') ? 'active' : ''; ?>" title="Orçamentos">
                <i data-lucide="receipt"></i>
                <span class="nav-text">Orçamentos</span>
            </a>
            <?php endif; ?>
            <?php if (tem_papel(['admin', 'secretaria'])): ?><a href="<?php echo $url_relatorio; ?>" class="sidebar-nav-item <?php echo sidebarAtiva('relatorio') ? 'active' : ''; ?>" title="Relatórios">
                <i data-lucide="chart-line"></i>
                <span class="nav-text">Relatórios</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-empresa">
            <span class="empresa-nome"><?php echo EMPRESA_NOME; ?></span>
            <span class="empresa-linha"><i data-lucide="map-pin"></i> <?php echo EMPRESA_ENDERECO; ?></span>
            <span class="empresa-linha"><i data-lucide="phone"></i> <?php echo EMPRESA_TELEFONE; ?></span>
            <span class="empresa-linha"><i data-lucide="mail"></i> <?php echo EMPRESA_EMAIL; ?></span>
        </div>
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
                <i data-lucide="moon"></i>
                <span class="nav-text">Tema</span>
            </button>
            <form method="POST" action="<?php echo $url_logout; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                <button type="submit" class="sidebar-action-btn" title="Sair">
                    <i data-lucide="log-out"></i>
                    <span class="nav-text">Sair</span>
                </button>
            </form>
        </div>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>