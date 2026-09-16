<?php
// Detectar página atual para marcar item ativo
$uri_atual = $_SERVER['REQUEST_URI'] ?? '';
$arquivo_atual = basename($_SERVER['PHP_SELF'] ?? '');

if (!function_exists('sidebarAtiva')) {
    function sidebarAtiva($modulo)
    {
        global $uri_atual, $arquivo_atual;
        if ($modulo === 'dashboard') {
            return $arquivo_atual === 'index.php' || $arquivo_atual === '';
        }
        if ($modulo === 'orcamentos' && strpos($uri_atual, 'relatorio') !== false) {
            return false;
        }
        if ($modulo === 'relatorios') {
            return strpos($uri_atual, 'relatorio') !== false;
        }
        return strpos($uri_atual, $modulo) !== false;
    }
}

if (!isset($caminho_base)) {
    require_once __DIR__ . '/base_url.php';
}
require_once __DIR__ . '/../config/empresa.php';

$url_perfil = $caminho_base . '/pages/auth/perfil.php';
$url_logout = $caminho_base . '/pages/auth/logout.php';

// Navegação: null em "papeis" significa visível para todos os perfis.
$navegacao = [
    [
        'secao' => 'Principal',
        'itens' => [
            ['modulo' => 'dashboard', 'url' => $caminho_base . '/index.php', 'icone' => 'layout-grid', 'texto' => 'Dashboard', 'papeis' => null],
            ['modulo' => 'notificacoes', 'url' => $caminho_base . '/pages/notificacoes/listar.php', 'icone' => 'bell', 'texto' => 'Notificações', 'papeis' => null],
            ['modulo' => 'clientes', 'url' => $caminho_base . '/pages/clientes/listar.php', 'icone' => 'users', 'texto' => 'Clientes', 'papeis' => ['admin', 'secretaria', 'mecanico_chefe']],
            ['modulo' => 'veiculos', 'url' => $caminho_base . '/pages/veiculos/listar.php', 'icone' => 'car-front', 'texto' => 'Veículos', 'papeis' => ['admin', 'secretaria', 'mecanico_chefe']],
            ['modulo' => 'funcionarios', 'url' => $caminho_base . '/pages/funcionarios/listar.php', 'icone' => 'users-round', 'texto' => 'Funcionários', 'papeis' => ['admin', 'secretaria', 'mecanico_chefe']],
        ],
    ],
    [
        'secao' => 'Operações',
        'itens' => [
            ['modulo' => 'ordens_servico', 'url' => $caminho_base . '/pages/ordens_servico/listar.php', 'icone' => 'clipboard-list', 'texto' => 'Ordens de serviço', 'papeis' => null],
            ['modulo' => 'pedidos_pecas', 'url' => $caminho_base . '/pages/pedidos_pecas/listar.php', 'icone' => 'truck', 'texto' => 'Pedidos de peças', 'papeis' => null],
        ],
    ],
    [
        'secao' => 'Documentos',
        'itens' => [
            ['modulo' => 'orcamentos', 'url' => $caminho_base . '/pages/orcamentos/listar.php', 'icone' => 'file-text', 'texto' => 'Orçamentos', 'papeis' => ['admin', 'secretaria', 'mecanico_chefe']],
            ['modulo' => 'relatorios', 'url' => $caminho_base . '/pages/orcamentos/relatorio.php', 'icone' => 'chart-line', 'texto' => 'Relatórios', 'papeis' => ['admin', 'secretaria', 'mecanico_chefe']],
        ],
    ],
    [
        'secao' => 'Sistema',
        'itens' => [
            ['modulo' => 'auditoria', 'url' => $caminho_base . '/pages/auditoria/listar.php', 'icone' => 'history', 'texto' => 'Auditoria', 'papeis' => ['admin']],
        ],
    ],
];

$rotulos_papel = ['admin' => 'Administrador', 'secretaria' => 'Secretaria', 'mecanico_chefe' => 'Mecânico-chefe', 'mecanico' => 'Mecânico', 'funcionario' => 'Funcionário'];
$papel_atual = $rotulos_papel[$_SESSION['usuario_tipo']] ?? ($_SESSION['usuario_tipo'] ?? 'Usuário');
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>

<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<aside class="sidebar" id="sidebar" aria-label="Navegação principal">
    <div class="sidebar-top">
        <a class="sidebar-brand" href="<?php echo $caminho_base; ?>/index.php">
            <span class="brand-icon" aria-hidden="true"><i data-lucide="wrench"></i></span>
            <span class="brand-text"><?php echo esc(EMPRESA_NOME); ?></span>
        </a>
        <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" type="button"
                aria-controls="sidebar" aria-expanded="true" title="Minimizar menu">
            <i data-lucide="chevron-left" aria-hidden="true"></i>
            <span class="sr-only">Minimizar menu</span>
        </button>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navegacao as $grupo): ?>
            <?php
            $visiveis = array_filter($grupo['itens'], function ($item) {
                return $item['papeis'] === null || tem_papel($item['papeis']);
            });
            if (!$visiveis) {
                continue;
            }
            ?>
            <div class="sidebar-section">
                <p class="sidebar-section-title"><?php echo esc($grupo['secao']); ?></p>
                <ul class="sidebar-list" role="list">
                    <?php foreach ($visiveis as $item): ?>
                        <?php $ativo = sidebarAtiva($item['modulo']); ?>
                        <li>
                            <a href="<?php echo $item['url']; ?>"
                               class="sidebar-nav-item<?php echo $ativo ? ' active' : ''; ?>"
                               <?php echo $ativo ? 'aria-current="page"' : ''; ?>
                               data-tooltip="<?php echo esc($item['texto']); ?>">
                                <i data-lucide="<?php echo esc($item['icone']); ?>" aria-hidden="true"></i>
                                <span class="nav-text"><?php echo esc($item['texto']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <button type="button" class="sidebar-user" id="sidebarUser"
                aria-expanded="false" aria-controls="sidebarUserMenu" data-tooltip="<?php echo esc($nome_usuario); ?>">
            <span class="sidebar-user-avatar" aria-hidden="true"><?php echo esc(strtoupper(substr($nome_usuario, 0, 1))); ?></span>
            <span class="sidebar-user-info">
                <span class="user-name"><?php echo esc($nome_usuario); ?></span>
                <span class="user-role"><?php echo esc($papel_atual); ?></span>
            </span>
            <i data-lucide="chevrons-up-down" class="sidebar-user-chevron" aria-hidden="true"></i>
        </button>

        <div class="sidebar-user-menu" id="sidebarUserMenu" hidden>
            <a href="<?php echo $url_perfil; ?>" class="sidebar-user-menu-item">
                <i data-lucide="user-cog" aria-hidden="true"></i>
                <span>Meus dados</span>
            </a>
            <button type="button" class="sidebar-user-menu-item" id="themeToggle">
                <i data-lucide="moon" aria-hidden="true"></i>
                <span>Tema</span>
            </button>
            <form method="POST" action="<?php echo $url_logout; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                <button type="submit" class="sidebar-user-menu-item danger">
                    <i data-lucide="log-out" aria-hidden="true"></i>
                    <span>Sair</span>
                </button>
            </form>
        </div>
    </div>
</aside>

<div class="sidebar-tooltip" id="sidebarTooltip" role="tooltip" hidden></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
