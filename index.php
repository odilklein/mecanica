<?php
require_once __DIR__ . '/includes/sessao.php';
require_once __DIR__ . '/config/conexao.php';

verificar_sessao();

$titulo_pagina = "Dashboard";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/mensagens.php';

// ============ QUERIES PARA O DASHBOARD ============

// Totais gerais
$total_clientes = (int) $conn->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$total_veiculos = (int) $conn->query("SELECT COUNT(*) FROM veiculos")->fetchColumn();
$total_pecas = (int) $conn->query("SELECT COUNT(*) FROM pecas")->fetchColumn();

// Agendamentos hoje
$stmt_agenda = $conn->query("SELECT COUNT(*) FROM agendamentos WHERE data_agendamento = CURDATE()");
$agendamentos_hoje = (int) $stmt_agenda->fetchColumn();

// Orçamentos abertos
$stmt_orc_aberto = $conn->query("SELECT COUNT(*) FROM orcamentos WHERE status = 'aberto'");
$orcamentos_abertos = (int) $stmt_orc_aberto->fetchColumn();

// Faturamento (total aprovado)
$stmt_fat = $conn->query("SELECT COALESCE(SUM(total_geral), 0) FROM orcamentos WHERE status = 'aprovado'");
$faturamento = (float) $stmt_fat->fetchColumn();

// Estoque baixo
$stmt_est = $conn->query("SELECT COUNT(*) FROM pecas WHERE quantidade <= estoque_minimo");
$estoque_baixo = (int) $stmt_est->fetchColumn();

// Próximos agendamentos
$stmt_prox = $conn->query("
    SELECT a.id, a.data_agendamento, a.hora, a.status, 
           c.nome AS cliente, v.marca, v.modelo, v.placa, s.nome AS servico
    FROM agendamentos a
    JOIN clientes c ON c.id = a.cliente_id
    LEFT JOIN veiculos v ON v.id = a.veiculo_id
    LEFT JOIN servicos s ON s.id = a.servico_id
    WHERE a.data_agendamento >= CURDATE()
    ORDER BY a.data_agendamento ASC, a.hora ASC
    LIMIT 5
");
$proximos_agendamentos = $stmt_prox->fetchAll();

// Últimos orçamentos
$stmt_orcs = $conn->query("
    SELECT o.id, o.numero, o.data_orcamento, o.status, o.total_geral, o.desconto,
           c.nome AS cliente, v.modelo AS veiculo, v.placa
    FROM orcamentos o
    JOIN clientes c ON c.id = o.cliente_id
    LEFT JOIN veiculos v ON v.id = o.veiculo_id
    ORDER BY o.data_criacao DESC
    LIMIT 5
");
$ultimos_orcamentos = $stmt_orcs->fetchAll();

// Peças com estoque baixo
$stmt_baixo = $conn->query("
    SELECT p.id, p.codigo, p.nome, p.quantidade, p.estoque_minimo, p.categoria
    FROM pecas p
    WHERE p.quantidade <= p.estoque_minimo
    ORDER BY (p.quantidade - p.estoque_minimo) ASC
    LIMIT 5
");
$pecas_baixo = $stmt_baixo->fetchAll();

// Data de hoje formatada
$hoje = date('d/m/Y');

function badgeStatus($status) {
    switch ($status) {
        case 'agendado': return 'badge-info';
        case 'confirmado': return 'badge-primary';
        case 'concluido': return 'badge-success';
        case 'aberto': return 'badge-info';
        case 'aprovado': return 'badge-success';
        case 'reprovado': return 'badge-danger';
        case 'cancelado': return 'badge-secondary';
        default: return 'badge-secondary';
    }
}

function badgeStatusOrc($status) {
    switch ($status) {
        case 'aberto': return 'badge-info';
        case 'aprovado': return 'badge-success';
        case 'reprovado': return 'badge-danger';
        case 'cancelado': return 'badge-secondary';
        default: return 'badge-secondary';
    }
}
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-th-large"></i>Dashboard</h1>
        <span class="page-subtitle">Visão geral da oficina - <?php echo $hoje; ?></span>
    </div>
    <div class="page-header-actions">
        <a href="pages/agendamentos/cadastrar.php" class="btn btn-primary btn-icon">
            <i class="fas fa-calendar-plus"></i> Novo Agendamento
        </a>
        <a href="pages/orcamentos/cadastrar.php" class="btn btn-outline-primary btn-icon">
            <i class="fas fa-file-invoice-dollar"></i> Novo Orçamento
        </a>
    </div>
</div>

<!-- ============ KPI CARDS ============ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card border-accent">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Clientes</span>
                <span class="stat-icon"><i class="fas fa-users"></i></span>
            </div>
            <div class="stat-value"><?php echo $total_clientes; ?></div>
            <div class="stat-delta up"><i class="fas fa-arrow-up"></i> total cadastrados</div>
            <i class="fas fa-users stat-decoration"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card border-blue">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Veículos</span>
                <span class="stat-icon blue"><i class="fas fa-car"></i></span>
            </div>
            <div class="stat-value"><?php echo $total_veiculos; ?></div>
            <div class="stat-delta up"><i class="fas fa-arrow-up"></i> em atendimento</div>
            <i class="fas fa-car stat-decoration"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card border-yellow">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Agend. Hoje</span>
                <span class="stat-icon yellow"><i class="fas fa-calendar-check"></i></span>
            </div>
            <div class="stat-value"><?php echo $agendamentos_hoje; ?></div>
            <div class="stat-delta">para <?php echo $hoje; ?></div>
            <i class="fas fa-calendar-check stat-decoration"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card border-green">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Orç. Abertos</span>
                <span class="stat-icon green"><i class="fas fa-file-invoice"></i></span>
            </div>
            <div class="stat-value"><?php echo $orcamentos_abertos; ?></div>
            <div class="stat-delta up"><i class="fas fa-arrow-up"></i> aguardando aprovação</div>
            <i class="fas fa-file-invoice stat-decoration"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card border-accent">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Faturamento</span>
                <span class="stat-icon"><i class="fas fa-money-bill-wave"></i></span>
            </div>
            <div class="stat-value" style="font-size:1.2rem;"><?php echo formatar_moeda($faturamento); ?></div>
            <div class="stat-delta up"><i class="fas fa-arrow-up"></i> total aprovado</div>
            <i class="fas fa-money-bill-wave stat-decoration"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card border-red">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Estoque Baixo</span>
                <span class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></span>
            </div>
            <div class="stat-value"><?php echo $estoque_baixo; ?></div>
            <div class="stat-delta down"><i class="fas fa-arrow-down"></i> itens críticos</div>
            <i class="fas fa-boxes-stacked stat-decoration"></i>
        </div>
    </div>
</div>

<!-- ============ CONTEÚDO PRINCIPAL ============ -->
<div class="row g-3">
    <!-- Coluna esquerda -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-calendar-check me-2"></i> Próximos Agendamentos
                <a href="pages/agendamentos/listar.php" class="btn btn-sm btn-link float-end">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <?php if (count($proximos_agendamentos) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Veículo</th>
                                <th>Serviço</th>
                                <th>Data</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proximos_agendamentos as $ag): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle"><?php echo strtoupper(substr($ag['cliente'], 0, 1)); ?></div>
                                        <strong><?php echo limpar($ag['cliente']); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($ag['marca']): ?>
                                        <small><?php echo limpar($ag['marca'] . ' ' . $ag['modelo']); ?> <span class="text-muted"><?php echo limpar($ag['placa']); ?></span></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo limpar($ag['servico'] ?? '-'); ?></small></td>
                                <td>
                                    <small>
                                        <?php echo date('d/m', strtotime($ag['data_agendamento'])); ?>
                                        <span class="text-muted"><?php echo substr($ag['hora'], 0, 5); ?></span>
                                    </small>
                                </td>
                                <td><span class="badge <?php echo badgeStatus($ag['status']); ?>"><?php echo ucfirst($ag['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <h4>Nenhum agendamento futuro</h4>
                    <p>Crie um novo agendamento para começar.</p>
                    <a href="pages/agendamentos/cadastrar.php" class="btn btn-primary btn-icon">
                        <i class="fas fa-plus"></i> Novo Agendamento
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-file-invoice-dollar me-2"></i> Últimos Orçamentos
                <a href="pages/orcamentos/listar.php" class="btn btn-sm btn-link float-end">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <?php if (count($ultimos_orcamentos) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Veículo</th>
                                <th>Data</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_orcamentos as $orc): ?>
                            <tr>
                                <td><strong><?php echo str_pad($orc['numero'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo limpar($orc['cliente']); ?></td>
                                <td><small><?php echo $orc['veiculo'] ? limpar($orc['veiculo'] . ' ' . $orc['placa']) : '-'; ?></small></td>
                                <td><small><?php echo date('d/m/Y', strtotime($orc['data_orcamento'])); ?></small></td>
                                <td><strong><?php echo formatar_moeda($orc['total_geral']); ?></strong></td>
                                <td><span class="badge <?php echo badgeStatusOrc($orc['status']); ?>"><?php echo ucfirst($orc['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h4>Nenhum orçamento ainda</h4>
                    <p>Crie o primeiro orçamento do sistema.</p>
                    <a href="pages/orcamentos/cadastrar.php" class="btn btn-primary btn-icon">
                        <i class="fas fa-plus"></i> Novo Orçamento
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Coluna direita -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle me-2" style="color:var(--warning);"></i> Estoque Baixo
                <a href="pages/pecas/listar.php" class="btn btn-sm btn-link float-end">Ver estoque</a>
            </div>
            <div class="card-body">
                <?php if (count($pecas_baixo) > 0): ?>
                    <?php foreach ($pecas_baixo as $peca): ?>
                    <a href="pages/pecas/editar.php?id=<?php echo $peca['id']; ?>" class="text-decoration-none">
                        <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded" style="background: var(--warning-bg); border-left: 3px solid var(--warning);">
                            <i class="fas fa-boxes-stacked" style="color: var(--warning);"></i>
                            <div class="flex-grow-1 min-w-0">
                                <strong class="d-block" style="font-size:0.82rem; color: var(--text-primary);"><?php echo limpar($peca['nome']); ?></strong>
                                <small style="color: var(--warning);">
                                    Restam <?php echo $peca['quantidade']; ?> / mínimo <?php echo $peca['estoque_minimo']; ?>
                                </small>
                            </div>
                            <span class="badge badge-warning"><?php echo $peca['codigo']; ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="fas fa-check-circle" style="font-size:2.5rem; color: var(--success); opacity:0.5;"></i>
                    <p class="mt-2 mb-0" style="font-size:0.85rem; color: var(--text-muted);">Estoque saudável, sem alertas.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-bolt me-2"></i> Ações Rápidas
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="pages/clientes/cadastrar.php" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i> <span class="nav-text">Novo Cliente</span>
                    </a>
                    <a href="pages/veiculos/cadastrar.php" class="quick-action-btn">
                        <i class="fas fa-car blue"></i> <span class="nav-text">Cadastrar Veículo</span>
                    </a>
                    <a href="pages/agendamentos/cadastrar.php" class="quick-action-btn">
                        <i class="fas fa-calendar-plus green"></i> <span class="nav-text">Novo Agendamento</span>
                    </a>
                    <a href="pages/orcamentos/cadastrar.php" class="quick-action-btn">
                        <i class="fas fa-file-invoice-dollar yellow"></i> <span class="nav-text">Novo Orçamento</span>
                    </a>
                    <a href="pages/pecas/entrada.php" class="quick-action-btn">
                        <i class="fas fa-arrow-right-arrow-left blue"></i> <span class="nav-text">Movimentar Estoque</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>