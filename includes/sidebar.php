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
    $caminho_base = $GLOBALS['caminho_base'] ?? '';
}
require_once __DIR__ . '/../config/empresa.php';
require_once __DIR__ . '/configuracoes.php';

$url_perfil = $caminho_base . '/pages/auth/perfil.php';

// Navegação: null em "papeis" significa visível para todos os perfis.
// "icone" usa nomes de ícones Tabler (classe tabler-*).
$navegacao = [
    [
        'secao' => 'Principal',
        'itens' => [
            ['modulo' => 'dashboard', 'url' => $caminho_base . '/index.php', 'icone' => 'layout-grid', 'texto' => 'Dashboard', 'papeis' => null],
            ['modulo' => 'notificacoes', 'url' => $caminho_base . '/pages/notificacoes/listar.php', 'icone' => 'bell', 'texto' => 'Notificações', 'papeis' => null, 'habilitado' => 'modulo_notificacoes'],
            ['modulo' => 'clientes', 'url' => $caminho_base . '/pages/clientes/listar.php', 'icone' => 'users', 'texto' => 'Clientes', 'papeis' => ['admin', 'secretaria', 'mecanico_chefe']],
            ['modulo' => 'veiculos', 'url' => $caminho_base . '/pages/veiculos/listar.php', 'icone' => 'car', 'texto' => 'Veículos', 'papeis' => ['admin', 'secretaria', 'mecanico_chefe']],
            ['modulo' => 'funcionarios', 'url' => $caminho_base . '/pages/funcionarios/listar.php', 'icone' => 'users-group', 'texto' => 'Funcionários', 'papeis' => ['admin', 'secretaria', 'mecanico_chefe']],
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
            ['modulo' => 'configuracoes', 'url' => $caminho_base . '/pages/configuracoes/listar.php', 'icone' => 'settings', 'texto' => 'Configurações', 'papeis' => ['admin']],
            ['modulo' => 'auditoria', 'url' => $caminho_base . '/pages/auditoria/listar.php', 'icone' => 'history', 'texto' => 'Auditoria', 'papeis' => ['admin']],
        ],
    ],
];

$rotulos_papel = ['admin' => 'Administrador', 'secretaria' => 'Secretaria', 'mecanico_chefe' => 'Mecânico-chefe', 'mecanico' => 'Mecânico', 'funcionario' => 'Funcionário'];
$papel_atual = $rotulos_papel[$_SESSION['usuario_tipo']] ?? ($_SESSION['usuario_tipo'] ?? 'Usuário');
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>

<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <div class="app-brand demo">
        <a href="<?php echo $caminho_base; ?>/index.php" class="app-brand-link">
            <span class="app-brand-logo demo">
                <span class="brand-icon"><i class="icon-base ti tabler-tool"></i></span>
            </span>
            <span class="app-brand-text demo menu-text fw-bold"><?php echo esc(EMPRESA_NOME); ?></span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
            <i class="icon-base ti tabler-x d-block d-xl-none"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <?php foreach ($navegacao as $grupo): ?>
            <?php
            $visiveis = array_filter($grupo['itens'], function ($item) use ($conn) {
                if ($item['papeis'] !== null && !tem_papel($item['papeis'])) {
                    return false;
                }
                if (isset($item['habilitado']) && !config_flag($conn, $item['habilitado'])) {
                    return false;
                }
                return true;
            });
            if (!$visiveis) {
                continue;
            }
            ?>
            <li class="menu-header small">
                <span class="menu-header-text"><?php echo esc($grupo['secao']); ?></span>
            </li>
            <?php foreach ($visiveis as $item): ?>
                <?php $ativo = sidebarAtiva($item['modulo']); ?>
                <li class="menu-item<?php echo $ativo ? ' active' : ''; ?>">
                    <a href="<?php echo esc($item['url']); ?>" class="menu-link" <?php echo $ativo ? 'aria-current="page"' : ''; ?>>
                        <i class="menu-icon icon-base ti tabler-<?php echo esc($item['icone']); ?>"></i>
                        <div><?php echo esc($item['texto']); ?></div>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</aside>
