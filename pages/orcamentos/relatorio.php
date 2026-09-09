<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$titulo_pagina = "Relatórios";
$ano_atual = date('Y');

// ============ QUERIES PARA RELATÓRIOS ============

// Resumo do mês atual
$mes_atual = date('m');
$stmt_resumo = $conn->query("
    SELECT
        COUNT(*) AS total_orcamentos,
        COALESCE(SUM(CASE WHEN status = 'aprovado' THEN total_geral ELSE 0 END), 0) AS faturado,
        COALESCE(SUM(CASE WHEN status = 'aberto' THEN total_geral ELSE 0 END), 0) AS em_aberto,
        COUNT(CASE WHEN status = 'aprovado' THEN 1 END) AS aprovados
    FROM orcamentos
    WHERE MONTH(data_orcamento) = $mes_atual AND YEAR(data_orcamento) = $ano_atual
");
$resumo = $stmt_resumo->fetch();

// Faturamento por mês (últimos 6 meses)
$stmt_fat_mensal = $conn->query("
    SELECT DATE_FORMAT(data_orcamento, '%Y-%m') AS mes,
           COALESCE(SUM(total_geral), 0) AS total
    FROM orcamentos
    WHERE status = 'aprovado' AND data_orcamento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(data_orcamento, '%Y-%m')
    ORDER BY mes
");
$faturamento_mensal = $stmt_fat_mensal->fetchAll();

// Serviços mais realizados
$stmt_servicos = $conn->query("
    SELECT s.nome, COUNT(oi.id) AS total, COALESCE(SUM(oi.subtotal), 0) AS receita
    FROM orcamento_itens oi
    JOIN servicos s ON s.id = oi.servico_id
    JOIN orcamentos o ON o.id = oi.orcamento_id
    WHERE o.status IN ('aprovado', 'aberto') AND oi.tipo = 'servico'
    GROUP BY s.nome
    ORDER BY total DESC
    LIMIT 5
");
$top_servicos = $stmt_servicos->fetchAll();

// Peças mais vendidas
$stmt_pecas = $conn->query("
    SELECT p.nome, COUNT(oi.id) AS total, COALESCE(SUM(oi.subtotal), 0) AS receita
    FROM orcamento_itens oi
    JOIN pecas p ON p.id = oi.peca_id
    JOIN orcamentos o ON o.id = oi.orcamento_id
    WHERE o.status IN ('aprovado', 'aberto') AND oi.tipo = 'peca'
    GROUP BY p.nome
    ORDER BY total DESC
    LIMIT 5
");
$top_pecas = $stmt_pecas->fetchAll();

// Orçamentos por status
$stmt_status = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM orcamentos
    GROUP BY status
");
$orcamentos_status = $stmt_status->fetchAll();
$status_map = [
    'aberto' => ['label' => 'Abertos', 'badge' => 'badge-info'],
    'aprovado' => ['label' => 'Aprovados', 'badge' => 'badge-success'],
    'reprovado' => ['label' => 'Reprovados', 'badge' => 'badge-danger'],
    'cancelado' => ['label' => 'Cancelados', 'badge' => 'badge-secondary'],
];

// Clientes top (por valor de orçamentos aprovados)
$stmt_top_clientes = $conn->query("
    SELECT c.nome, COUNT(o.id) AS total_orcamentos, COALESCE(SUM(o.total_geral), 0) AS total_gasto
    FROM orcamentos o
    JOIN clientes c ON c.id = o.cliente_id
    WHERE o.status = 'aprovado'
    GROUP BY c.nome
    ORDER BY total_gasto DESC
    LIMIT 5
");
$top_clientes = $stmt_top_clientes->fetchAll();

$nomes_meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$mes_nome = $nomes_meses[(int) $mes_atual];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-chart-line"></i>Relatórios</h1>
        <span class="page-subtitle">Análise de desempenho da oficina - <?php echo ucfirst($mes_nome); ?> <?php echo $ano_atual; ?></span>
    </div>
</div>

<!-- ============ RESUMO DO MÊS ============ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card border-accent">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Orçamentos</span>
                <span class="stat-icon"><i class="fas fa-file-invoice"></i></span>
            </div>
            <div class="stat-value"><?php echo $resumo['total_orcamentos']; ?></div>
            <div class="stat-delta">no mês</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card border-green">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Faturado</span>
                <span class="stat-icon green"><i class="fas fa-money-bill-wave"></i></span>
            </div>
            <div class="stat-value" style="font-size:1.1rem;"><?php echo formatar_moeda($resumo['faturado']); ?></div>
            <div class="stat-delta up"><i class="fas fa-arrow-up"></i> <?php echo $resumo['aprovados']; ?> aprovados</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card border-yellow">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Em Aberto</span>
                <span class="stat-icon yellow"><i class="fas fa-hourglass-half"></i></span>
            </div>
            <div class="stat-value" style="font-size:1.1rem;"><?php echo formatar_moeda($resumo['em_aberto']); ?></div>
            <div class="stat-delta">aguardando aprovação</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card border-blue">
            <span class="stat-border"></span>
            <div class="stat-header">
                <span class="stat-label">Taxa Aprovação</span>
                <span class="stat-icon blue"><i class="fas fa-percent"></i></span>
            </div>
            <div class="stat-value">
                <?php
                $taxa = $resumo['total_orcamentos'] > 0 ? round(($resumo['aprovados'] / $resumo['total_orcamentos']) * 100) : 0;
                echo $taxa . '%';
                ?>
            </div>
            <div class="stat-delta">no mês</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Faturamento mensal -->
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-chart-column me-2"></i> Faturamento por Mês (aprovados)
            </div>
            <div class="card-body">
                <?php if (count($faturamento_mensal) > 0): ?>
                <div class="d-flex align-items-end gap-2" style="height:180px;">
                    <?php
                    $max_val = 0;
                    foreach ($faturamento_mensal as $f) $max_val = max($max_val, $f['total']);
                    if ($max_val == 0) $max_val = 1;
                    foreach ($faturamento_mensal as $f):
                        $altura = max(6, round(($f['total'] / $max_val) * 160));
                        $mes_label = DateTime::createFromFormat('Y-m', $f['mes'])->format('M/y');
                    ?>
                    <div class="flex-grow-1 d-flex flex-column align-items-center gap-1" title="<?php echo formatar_moeda($f['total']); ?>">
                        <span class="small" style="font-size:0.62rem; color:var(--text-muted);"><?php echo formatar_moeda($f['total']); ?></span>
                        <div style="width:100%; height:<?php echo $altura; ?>px; background:linear-gradient(180deg, var(--accent), var(--accent-hover)); border-radius:6px 6px 0 0; min-height:6px;"></div>
                        <span class="small" style="font-size:0.68rem; color:var(--text-muted);"><?php echo $mes_label; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-column"></i>
                    <h4>Sem dados de faturamento</h4>
                    <p>Aprove orçamentos para visualizar o gráfico.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-trophy me-2"></i> Top Clientes (maior valor gasto)
            </div>
            <div class="card-body p-0">
                <?php if (count($top_clientes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Orçamentos</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_clientes as $i => $tc): ?>
                            <tr>
                                <td><span class="badge badge-secondary"><?php echo $i + 1; ?>º</span></td>
                                <td><strong><?php echo limpar($tc['nome']); ?></strong></td>
                                <td><?php echo $tc['total_orcamentos']; ?></td>
                                <td class="text-end fw-600 text-success"><?php echo formatar_moeda($tc['total_gasto']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-trophy"></i>
                    <h4>Sem clientes com orçamentos aprovados</h4>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Coluna direita -->
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-circle-check me-2"></i> Orçamentos por Status
            </div>
            <div class="card-body">
                <?php if (count($orcamentos_status) > 0): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($status_map as $key => $info):
                            $total_status = 0;
                            foreach ($orcamentos_status as $os) if ($os['status'] == $key) $total_status = $os['total'];
                            $total_geral_status = array_sum(array_column($orcamentos_status, 'total'));
                            $percent = $total_geral_status > 0 ? round(($total_status / $total_geral_status) * 100) : 0;
                        ?>
                        <div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small fw-500" style="color:var(--text-secondary);"><?php echo $info['label']; ?></span>
                                <span class="badge <?php echo $info['badge']; ?>"><?php echo $total_status; ?> (<?php echo $percent; ?>%)</span>
                            </div>
                            <div class="progress" style="height:8px; background:var(--border-color);">
                                <div class="progress-bar" role="progressbar" style="width:<?php echo $percent; ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-circle-check"></i>
                    <h4>Nenhum orçamento registrado</h4>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-wrench me-2"></i> Serviços Mais Realizados
            </div>
            <div class="card-body p-0">
                <?php if (count($top_servicos) > 0): ?>
                <ul class="list-group list-group-flush" style="--bs-list-group-bg:transparent; --bs-list-group-border-color:var(--border-color);">
                    <?php foreach ($top_servicos as $s): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center" style="background:transparent; color:var(--text-primary);">
                        <div>
                            <strong style="font-size:0.82rem;"><?php echo limpar($s['nome']); ?></strong>
                            <div class="small text-muted"><?php echo $s['total']; ?>x realizado</div>
                        </div>
                        <span class="text-success fw-600 small"><?php echo formatar_moeda($s['receita']); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-wrench"></i>
                    <h4>Sem serviços registrados</h4>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-boxes-stacked me-2"></i> Peças Mais Vendidas
            </div>
            <div class="card-body p-0">
                <?php if (count($top_pecas) > 0): ?>
                <ul class="list-group list-group-flush" style="--bs-list-group-bg:transparent; --bs-list-group-border-color:var(--border-color);">
                    <?php foreach ($top_pecas as $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center" style="background:transparent; color:var(--text-primary);">
                        <div>
                            <strong style="font-size:0.82rem;"><?php echo limpar($p['nome']); ?></strong>
                            <div class="small text-muted"><?php echo $p['total']; ?>x vendida</div>
                        </div>
                        <span class="text-success fw-600 small"><?php echo formatar_moeda($p['receita']); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-boxes-stacked"></i>
                    <h4>Sem peças vendidas</h4>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>