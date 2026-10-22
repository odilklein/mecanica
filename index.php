<?php
require_once __DIR__ . '/includes/sessao.php';
require_once __DIR__ . '/config/conexao.php';
verificar_sessao();

$titulo_pagina = 'Painel';
$tipo_usuario = $_SESSION['usuario_tipo'];
$total_clientes = (int) $conn->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
$total_funcionarios = (int) $conn->query('SELECT COUNT(*) FROM usuarios WHERE ativo = 1')->fetchColumn();
$filtro_os = $tipo_usuario === 'mecanico' ? ' JOIN os_responsaveis r ON r.os_id = os.id AND r.usuario_id = ' . (int) $_SESSION['usuario_id'] . ' AND r.concluido_em IS NULL ' : '';
$os_abertas = (int) $conn->query("SELECT COUNT(*) FROM ordens_servico os $filtro_os WHERE os.status NOT IN ('entregue', 'cancelada')")->fetchColumn();
$pecas_pendentes = $tipo_usuario === 'mecanico'
    ? (int) $conn->query("SELECT COUNT(*) FROM pedidos_pecas p JOIN os_responsaveis r ON r.os_id = p.os_id AND r.usuario_id = " . (int) $_SESSION['usuario_id'] . " AND r.concluido_em IS NULL WHERE p.status IN ('solicitado','validado','pedido','a_caminho')")->fetchColumn()
    : (int) $conn->query("SELECT COUNT(*) FROM pedidos_pecas WHERE status IN ('solicitado','validado','pedido','a_caminho')")->fetchColumn();
$filtro_os_view = $tipo_usuario === 'mecanico'
    ? ' AND EXISTS (SELECT 1 FROM os_responsaveis r WHERE r.os_id = osv.id AND r.usuario_id = ' . (int) $_SESSION['usuario_id'] . ' AND r.concluido_em IS NULL) '
    : '';
$stmt = $conn->query("SELECT osv.id, osv.numero, osv.status, osv.status_label, osv.prioridade, osv.cliente, osv.veiculo, osv.placa FROM vw_ordens_servico_detalhada osv WHERE osv.status NOT IN ('entregue', 'cancelada') $filtro_os_view ORDER BY FIELD(osv.prioridade, 'urgente', 'alta', 'normal', 'baixa'), osv.data_entrada LIMIT 10");
$ordens = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/mensagens.php';
?>

<div class="page-header">
    <div>
        <h1>Painel</h1>
        <span class="page-subtitle">Acompanhe o trabalho da oficina</span>
    </div>
    <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?>
    <a href="pages/ordens_servico/novo.php" class="btn btn-primary">
        <i class="icon-base ti tabler-clipboard-plus me-1"></i> Novo atendimento
    </a>
    <?php endif; ?>
</div>

<div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading">Clientes</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2"><?php echo $total_clientes; ?></h4>
                        </div>
                        <small class="mb-0 text-body-secondary">cadastrados</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-users icon-26px"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading">Funcionários</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2"><?php echo $total_funcionarios; ?></h4>
                        </div>
                        <small class="mb-0 text-body-secondary">ativos</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-info"><i class="icon-base ti tabler-users-group icon-26px"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading">OS em andamento</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2"><?php echo $os_abertas; ?></h4>
                        </div>
                        <small class="mb-0 text-body-secondary"><?php echo $tipo_usuario === 'mecanico' ? 'atribuídas a você' : 'não encerradas'; ?></small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-warning"><i class="icon-base ti tabler-clipboard-list icon-26px"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading">Peças pendentes</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2"><?php echo $pecas_pendentes; ?></h4>
                        </div>
                        <small class="mb-0 text-body-secondary">aguardando chegada</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger"><i class="icon-base ti tabler-package icon-26px"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-6">
    <div class="col-lg-9">
        <div class="card">
            <h5 class="card-header">Ordens de serviço em andamento
                <a href="pages/ordens_servico/listar.php" class="float-end">Ver todas</a>
            </h5>
            <div class="table-responsive text-nowrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Cliente</th>
                            <th>Veículo</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        <?php foreach ($ordens as $ordem): ?>
                        <tr>
                            <td>
                                <a href="pages/ordens_servico/detalhes.php?id=<?php echo (int) $ordem['id']; ?>">
                                    <span class="fw-medium">#<?php echo str_pad((int) $ordem['numero'], 4, '0', STR_PAD_LEFT); ?></span>
                                </a>
                            </td>
                            <td><?php echo esc($ordem['cliente']); ?></td>
                            <td><?php echo esc($ordem['placa'] ?: $ordem['veiculo'] ?: '-'); ?></td>
                            <td><span class="badge badge-status-<?php echo esc($ordem['status']); ?>"><?php echo esc($ordem['status_label']); ?></span></td>
                            <td><span class="badge badge-prioridade-<?php echo esc($ordem['prioridade']); ?>"><?php echo esc(ucfirst($ordem['prioridade'])); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$ordem): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="icon-base ti tabler-clipboard-check"></i></div>
                                    <h4>Nenhuma OS em andamento</h4>
                                    <p>Tudo em ordem! Nenhuma ordem de serviço aberta no momento.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card">
            <h5 class="card-header">Ações rápidas</h5>
            <div class="card-body">
                <div class="quick-actions">
                    <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?>
                    <a href="pages/ordens_servico/novo.php" class="quick-action-btn"><i class="icon-base ti tabler-clipboard-plus"></i><span>Novo atendimento</span></a>
                    <?php endif; ?>
                    <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?>
                    <a href="pages/clientes/listar.php" class="quick-action-btn"><i class="icon-base ti tabler-user-plus"></i><span>Novo cliente</span></a>
                    <?php endif; ?>
                    <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?>
                    <a href="pages/funcionarios/listar.php" class="quick-action-btn"><i class="icon-base ti tabler-users-group"></i><span>Funcionários</span></a>
                    <?php endif; ?>
                    <a href="pages/pedidos_pecas/listar.php" class="quick-action-btn"><i class="icon-base ti tabler-truck"></i><span>Pedidos de peças</span></a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
