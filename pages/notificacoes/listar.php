<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';
require_once __DIR__ . '/../../includes/crud.php';

$usuario_id = (int) $_SESSION['usuario_id'];
$filtro = $_GET['filtro'] ?? 'todas';
$tipo = limpar($_GET['tipo'] ?? '');
$busca = limpar($_GET['busca'] ?? '');
$pagina = pagina_atual();
$por_pagina = 15;

$condicoes = ['n.usuario_id = :uid'];
$params = [':uid' => $usuario_id];
if ($filtro === 'nao_lidas') $condicoes[] = 'n.lida_em IS NULL';
if ($tipo !== '') { $condicoes[] = 'n.tipo = :tipo'; $params[':tipo'] = $tipo; }
if ($busca !== '') { $condicoes[] = 'n.mensagem LIKE :busca'; $params[':busca'] = '%' . $busca . '%'; }
$where = implode(' AND ', $condicoes);

$st = $conn->prepare("SELECT COUNT(*) FROM notificacoes n WHERE $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$pagina, $offset, $total_paginas] = paginacao_calcular($total, $por_pagina, $pagina);

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
<div class="page-header">
    <div>
        <h1>Notificações</h1>
        <span class="page-subtitle">Avisos das ordens de serviço</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="POST" action="marcar_todas.php" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
            <button class="btn btn-outline-primary" type="submit"><i class="icon-base ti tabler-checks me-1"></i>Marcar todas como lidas</button>
        </form>
    </div>
</div>

<div class="card">
    <form id="filtrosForm" method="get" action="listar.php"><button type="submit" hidden>Filtrar</button></form>
    <?php
    $filtros = '<select class="form-select" name="filtro" form="filtrosForm" onchange="document.getElementById(\'filtrosForm\').submit()">'
        . '<option value="todas"' . ($filtro === 'todas' ? ' selected' : '') . '>Todas</option>'
        . '<option value="nao_lidas"' . ($filtro === 'nao_lidas' ? ' selected' : '') . '>Não lidas</option>'
        . '</select>';
    $filtros .= '<select class="form-select" name="tipo" form="filtrosForm" onchange="document.getElementById(\'filtrosForm\').submit()">'
        . '<option value="">Tipo: todos</option>';
    foreach ($tipos as $t) {
        $filtros .= '<option value="' . esc($t['tipo']) . '"' . ($tipo === $t['tipo'] ? ' selected' : '') . '>' . esc($rotulos_tipo[$t['tipo']] ?? $t['tipo']) . ' (' . (int) $t['total'] . ')</option>';
    }
    $filtros .= '</select>';
    echo '<div class="table-panel"><div class="crud-toolbar">'
        . '<div class="crud-search"><i class="icon-base ti tabler-search"></i>'
        . '<input type="search" name="busca" form="filtrosForm" class="form-control" placeholder="Buscar por mensagem..." value="' . esc($busca) . '" autocomplete="off"></div>'
        . $filtros
        . '</div></div>';
    ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle responsive-card">
            <thead>
                <tr><th>Mensagem</th><th>OS</th><th>Data</th><th class="actions-cell"></th></tr>
            </thead>
            <tbody>
            <?php foreach ($notificacoes as $notif): ?>
                <tr class="<?php echo $notif['lida_em'] === null ? 'notificacao-nao-lida' : ''; ?>">
                    <td data-label="Mensagem">
                        <div class="d-flex align-items-start gap-2">
                            <span class="mt-1" style="width:16px;"><?php if ($notif['lida_em'] === null): ?><span class="notification-dot d-inline-block"></span><?php endif; ?></span>
                            <div>
                                <span class="fw-medium"><?php echo esc($rotulos_tipo[$notif['tipo']] ?? $notif['tipo']); ?></span><br>
                                <span class="small"><?php echo esc($notif['mensagem']); ?></span>
                            </div>
                        </div>
                    </td>
                    <td data-label="OS"><?php if ($notif['os_id']): ?><a href="../ordens_servico/detalhes.php?id=<?php echo (int) $notif['os_id']; ?>">OS #<?php echo str_pad((int) $notif['os_numero'], 4, '0', STR_PAD_LEFT); ?></a><?php else: ?>—<?php endif; ?></td>
                    <td data-label="Data"><?php echo date('d/m/Y H:i', strtotime($notif['criada_em'])); ?></td>
                    <td class="actions-cell"><?php if ($notif['os_id'] && $notif['lida_em'] === null): ?><a class="btn btn-sm btn-outline-primary" href="../ordens_servico/detalhes.php?id=<?php echo (int) $notif['os_id']; ?>&marcar_notificacao=<?php echo (int) $notif['id']; ?>">Abrir</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$notificacoes): ?><tr class="row-empty"><td colspan="4"><?php echo crud_empty_state('bell-off', 'Nenhuma notificação', 'Não há notificações para exibir com os filtros atuais.'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo dt_rodape($pagina, $total, $por_pagina, $total_paginas, $filtros_pag, 'listar.php', 'notificações'); ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
