<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';

$usuario_id = (int) $_SESSION['usuario_id'];
$filtro = $_GET['filtro'] ?? 'todas';
$tipo = limpar($_GET['tipo'] ?? '');
$busca = limpar($_GET['busca'] ?? '');
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pagina = 25;
$offset = ($pagina - 1) * $por_pagina;

$condicoes = ['n.usuario_id = :uid'];
$params = [':uid' => $usuario_id];
if ($filtro === 'nao_lidas') $condicoes[] = 'n.lida_em IS NULL';
if ($tipo !== '') { $condicoes[] = 'n.tipo = :tipo'; $params[':tipo'] = $tipo; }
if ($busca !== '') { $condicoes[] = 'n.mensagem LIKE :busca'; $params[':busca'] = '%' . $busca . '%'; }
$where = implode(' AND ', $condicoes);

$st = $conn->prepare("SELECT COUNT(*) FROM notificacoes n WHERE $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
$total_paginas = max(1, (int) ceil($total / $por_pagina));
if ($pagina > $total_paginas) $pagina = $total_paginas;

$st = $conn->prepare("SELECT n.id, n.os_id, n.tipo, n.mensagem, n.lida_em, n.criada_em, os.numero AS os_numero FROM notificacoes n LEFT JOIN ordens_servico os ON os.id = n.os_id WHERE $where ORDER BY n.criada_em DESC LIMIT :limite OFFSET :offset");
$st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->execute();
$notificacoes = $st->fetchAll();

$st = $conn->prepare('SELECT tipo, COUNT(*) AS total FROM notificacoes WHERE usuario_id = :uid GROUP BY tipo ORDER BY total DESC');
$st->execute([':uid' => $usuario_id]);
$tipos = $st->fetchAll();
$rotulos_tipo = ['os_criada' => 'OS criada', 'os_atribuida' => 'OS atribuída', 'os_status' => 'Status da OS', 'diagnostico' => 'Diagnóstico', 'tarefa' => 'Tarefa', 'peca_pedido' => 'Pedido de peça', 'peca_disponivel' => 'Peça disponível', 'entrega' => 'Entrega', 'orcamento' => 'Orçamento'];
$filtros_pag = array_filter(['filtro' => $filtro, 'tipo' => $tipo, 'busca' => $busca], fn($v) => $v !== '');

$titulo_pagina = 'Notificações';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header"><div><h1><i data-lucide="bell" class="text-primary me-2"></i>Notificações</h1><span class="page-subtitle">Avisos internos do sistema</span></div></div>
<div class="card"><div class="card-body p-0">
<div class="filter-bar mb-0 rounded-bottom-0">
    <div class="filter-search">
        <i data-lucide="search" class="search-icon"></i>
        <input type="text" class="form-control" id="buscaNotificacoes" placeholder="Buscar por mensagem..." value="<?php echo esc($busca); ?>">
    </div>
    <div class="filter-group">
        <label for="filtroEstado">Estado</label>
        <select class="form-select" id="filtroEstado">
            <option value="todas" <?php echo $filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
            <option value="nao_lidas" <?php echo $filtro === 'nao_lidas' ? 'selected' : ''; ?>>Não lidas</option>
        </select>
    </div>
    <div class="filter-group">
        <label for="filtroTipo">Tipo</label>
        <select class="form-select" id="filtroTipo">
            <option value="">Todos os tipos</option>
            <?php foreach ($tipos as $t): ?>
            <option value="<?php echo esc($t['tipo']); ?>" <?php echo $tipo === $t['tipo'] ? 'selected' : ''; ?>><?php echo esc($rotulos_tipo[$t['tipo']] ?? $t['tipo']); ?> (<?php echo (int) $t['total']; ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="window.location.href='listar.php'"><i data-lucide="filter-x"></i> Limpar</button>
    <form method="POST" action="marcar_todas.php" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><button class="btn btn-outline-primary" type="submit"><i data-lucide="check-check"></i> Marcar todas como lidas</button></form>
</div>
<div class="px-3 pt-3 filter-results-info"><span>Exibindo <strong><?php echo count($notificacoes); ?></strong> de <?php echo $total; ?> notificações</span></div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0 responsive-card"><thead><tr><th>Mensagem</th><th>OS</th><th>Data</th><th></th></tr></thead><tbody>
<?php foreach ($notificacoes as $notif): ?><tr class="<?php echo $notif['lida_em'] === null ? 'notificacao-nao-lida' : ''; ?>"><td data-label="Mensagem"><div class="d-flex align-items-start gap-2"><span class="mt-1" style="width:16px;"><?php if ($notif['lida_em'] === null): ?><span class="notification-dot d-inline-block"></span><?php endif; ?></span><div><strong><?php echo esc($rotulos_tipo[$notif['tipo']] ?? $notif['tipo']); ?></strong><br><span class="small"><?php echo esc($notif['mensagem']); ?></span></div></div></td><td data-label="OS"><?php if ($notif['os_id']): ?><a href="../ordens_servico/detalhes.php?id=<?php echo (int) $notif['os_id']; ?>">OS #<?php echo str_pad((int) $notif['os_numero'], 4, '0', STR_PAD_LEFT); ?></a><?php else: ?>-<?php endif; ?></td><td data-label="Data"><?php echo date('d/m/Y H:i', strtotime($notif['criada_em'])); ?></td><td class="text-end"><?php if ($notif['os_id'] && $notif['lida_em'] === null): ?><a class="btn btn-sm btn-outline-primary" href="../ordens_servico/detalhes.php?id=<?php echo (int) $notif['os_id']; ?>&marcar_notificacao=<?php echo (int) $notif['id']; ?>">Abrir</a><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$notificacoes): ?><tr class="row-empty"><td colspan="4"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="bell-off"></i></div><h4>Nenhuma notificação</h4><p>Não há notificações para exibir com os filtros atuais.</p></div></td></tr><?php endif; ?>
</tbody></table></div>
<?php echo paginacao_links($pagina, $total_paginas, $filtros_pag); ?>
</div></div>
<script>
(function () {
    function recarregar() {
        var busca = document.getElementById('buscaNotificacoes').value;
        var estado = document.getElementById('filtroEstado').value;
        var tipo = document.getElementById('filtroTipo').value;
        window.location.href = 'listar.php?filtro=' + encodeURIComponent(estado) + '&tipo=' + encodeURIComponent(tipo) + '&busca=' + encodeURIComponent(busca);
    }
    var busca = document.getElementById('buscaNotificacoes');
    var debounce = null;
    busca.addEventListener('keydown', function (e) { if (e.key === 'Enter') recarregar(); });
    busca.addEventListener('input', function () { clearTimeout(debounce); debounce = setTimeout(recarregar, 600); });
    document.getElementById('filtroEstado').addEventListener('change', recarregar);
    document.getElementById('filtroTipo').addEventListener('change', recarregar);
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>