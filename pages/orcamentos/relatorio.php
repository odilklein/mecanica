<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/status_os.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$titulo_pagina = 'Relatórios';
$ano_atual = date('Y');
$mes_num = (int) date('n');

// Rótulos curtos de mês usados nos gráficos de evolução
$rotulos_meses = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

// Janela dos últimos 6 meses (do mais antigo para o mais recente)
$meses_janela = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("first day of -$i month");
    $ym = date('Y-m', $ts);
    $meses_janela[$ym] = $rotulos_meses[(int) date('n', $ts)] . '/' . substr(date('Y', $ts), 2);
}

// ============ RESUMO DO MÊS ATUAL ============
$resumo = $conn->query("
    SELECT
        COUNT(*) AS total_orcamentos,
        COALESCE(SUM(CASE WHEN status = 'aprovado' THEN total_geral ELSE 0 END), 0) AS faturado,
        COALESCE(SUM(CASE WHEN status = 'aberto' THEN total_geral ELSE 0 END), 0) AS em_aberto,
        COUNT(CASE WHEN status = 'aprovado' THEN 1 END) AS aprovados
    FROM orcamentos
    WHERE MONTH(data_orcamento) = $mes_num AND YEAR(data_orcamento) = $ano_atual
")->fetch();

$ticket_medio = $resumo['aprovados'] > 0 ? $resumo['faturado'] / $resumo['aprovados'] : 0;
$taxa_aprovacao = $resumo['total_orcamentos'] > 0 ? round(($resumo['aprovados'] / $resumo['total_orcamentos']) * 100) : 0;

// Tempo médio de conclusão das OS (em dias)
$tempo_medio_horas = (float) $conn->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, data_entrada, data_conclusao))
    FROM ordens_servico
    WHERE data_conclusao IS NOT NULL
")->fetchColumn();
$tempo_medio_dias = $tempo_medio_horas > 0 ? round($tempo_medio_horas / 24, 1) : 0;

// ============ SÉRIES MENSAIS (6 meses) ============
$fat_map = [];
foreach ($conn->query("
    SELECT DATE_FORMAT(data_orcamento, '%Y-%m') AS mes, COALESCE(SUM(total_geral), 0) AS total
    FROM orcamentos
    WHERE status = 'aprovado' AND data_orcamento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mes
")->fetchAll() as $row) {
    $fat_map[$row['mes']] = (float) $row['total'];
}

$os_map = [];
foreach ($conn->query("
    SELECT DATE_FORMAT(data_entrada, '%Y-%m') AS mes, COUNT(*) AS total
    FROM ordens_servico
    WHERE data_entrada >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mes
")->fetchAll() as $row) {
    $os_map[$row['mes']] = (int) $row['total'];
}

$grafico_faturamento = ['labels' => [], 'valores' => []];
$grafico_os_mes = ['labels' => [], 'valores' => []];
foreach ($meses_janela as $ym => $rotulo) {
    $grafico_faturamento['labels'][] = $rotulo;
    $grafico_faturamento['valores'][] = $fat_map[$ym] ?? 0;
    $grafico_os_mes['labels'][] = $rotulo;
    $grafico_os_mes['valores'][] = $os_map[$ym] ?? 0;
}

// ============ ORÇAMENTOS POR STATUS ============
$status_map = [
    'aberto' => ['label' => 'Abertos', 'token' => 'info'],
    'aprovado' => ['label' => 'Aprovados', 'token' => 'success'],
    'reprovado' => ['label' => 'Reprovados', 'token' => 'danger'],
    'cancelado' => ['label' => 'Cancelados', 'token' => 'gray'],
];
$grafico_orc_status = ['labels' => [], 'valores' => [], 'cores' => []];
foreach ($conn->query("SELECT status, COUNT(*) AS total FROM orcamentos GROUP BY status")->fetchAll() as $row) {
    if ((int) $row['total'] <= 0) {
        continue;
    }
    $info = $status_map[$row['status']] ?? ['label' => ucfirst($row['status']), 'token' => 'gray'];
    $grafico_orc_status['labels'][] = $info['label'];
    $grafico_orc_status['valores'][] = (int) $row['total'];
    $grafico_orc_status['cores'][] = $info['token'];
}

// ============ ORDENS DE SERVIÇO POR STATUS ============
$os_status_map = [
    'recebida' => ['label' => 'Recebida', 'token' => 'gray'],
    'em_analise' => ['label' => 'Em análise', 'token' => 'amber'],
    'aguardando_aprovacao' => ['label' => 'Aguard. aprovação', 'token' => 'orange'],
    'aguardando_peca' => ['label' => 'Aguard. peça', 'token' => 'violet'],
    'em_execucao' => ['label' => 'Em execução', 'token' => 'info'],
    'pronta_para_entrega' => ['label' => 'Pronta p/ entrega', 'token' => 'success'],
    'entregue' => ['label' => 'Entregue', 'token' => 'green'],
    'cancelada' => ['label' => 'Cancelada', 'token' => 'danger'],
];
$grafico_os_status = ['labels' => [], 'valores' => [], 'cores' => []];
foreach ($conn->query("SELECT status, COUNT(*) AS total FROM ordens_servico GROUP BY status")->fetchAll() as $row) {
    if ((int) $row['total'] <= 0) {
        continue;
    }
    $info = $os_status_map[$row['status']] ?? ['label' => ucfirst($row['status']), 'token' => 'gray'];
    $grafico_os_status['labels'][] = $info['label'];
    $grafico_os_status['valores'][] = (int) $row['total'];
    $grafico_os_status['cores'][] = $info['token'];
}

// ============ ORDENS DE SERVIÇO POR PRIORIDADE ============
$os_prio_map = [
    'baixa' => ['label' => 'Baixa', 'token' => 'gray'],
    'normal' => ['label' => 'Normal', 'token' => 'info'],
    'alta' => ['label' => 'Alta', 'token' => 'warning'],
    'urgente' => ['label' => 'Urgente', 'token' => 'danger'],
];
$grafico_os_prioridade = ['labels' => [], 'valores' => [], 'cores' => []];
foreach ($conn->query("SELECT prioridade, COUNT(*) AS total FROM ordens_servico GROUP BY prioridade")->fetchAll() as $row) {
    $info = $os_prio_map[$row['prioridade']] ?? ['label' => ucfirst($row['prioridade']), 'token' => 'gray'];
    $grafico_os_prioridade['labels'][] = $info['label'];
    $grafico_os_prioridade['valores'][] = (int) $row['total'];
    $grafico_os_prioridade['cores'][] = $info['token'];
}

// ============ TOP SERVIÇOS E PEÇAS ============
$grafico_servicos = ['labels' => [], 'valores' => [], 'receita' => []];
foreach ($conn->query("
    SELECT oi.descricao_item AS nome, COUNT(oi.id) AS total, COALESCE(SUM(oi.subtotal), 0) AS receita
    FROM orcamento_itens oi
    JOIN orcamentos o ON o.id = oi.orcamento_id
    WHERE o.status IN ('aprovado', 'aberto') AND oi.tipo = 'servico'
    GROUP BY oi.descricao_item
    ORDER BY total DESC
    LIMIT 6
")->fetchAll() as $row) {
    $grafico_servicos['labels'][] = $row['nome'];
    $grafico_servicos['valores'][] = (int) $row['total'];
    $grafico_servicos['receita'][] = (float) $row['receita'];
}

$grafico_pecas = ['labels' => [], 'valores' => [], 'receita' => []];
foreach ($conn->query("
    SELECT oi.descricao_item AS nome, COUNT(oi.id) AS total, COALESCE(SUM(oi.subtotal), 0) AS receita
    FROM orcamento_itens oi
    JOIN orcamentos o ON o.id = oi.orcamento_id
    WHERE o.status IN ('aprovado', 'aberto') AND oi.tipo = 'peca'
    GROUP BY oi.descricao_item
    ORDER BY total DESC
    LIMIT 6
")->fetchAll() as $row) {
    $grafico_pecas['labels'][] = $row['nome'];
    $grafico_pecas['valores'][] = (int) $row['total'];
    $grafico_pecas['receita'][] = (float) $row['receita'];
}

// ============ TOP CLIENTES ============
$top_clientes = $conn->query("
    SELECT c.nome, COUNT(o.id) AS total_orcamentos, COALESCE(SUM(o.total_geral), 0) AS total_gasto
    FROM orcamentos o
    JOIN clientes c ON c.id = o.cliente_id
    WHERE o.status = 'aprovado'
    GROUP BY c.id, c.nome
    ORDER BY total_gasto DESC
    LIMIT 6
")->fetchAll();
$grafico_clientes = ['labels' => [], 'valores' => []];
foreach ($top_clientes as $row) {
    $grafico_clientes['labels'][] = $row['nome'];
    $grafico_clientes['valores'][] = (float) $row['total_gasto'];
}

// ============ COMPOSIÇÃO DA RECEITA (peças x mão de obra) ============
$composicao = $conn->query("
    SELECT COALESCE(SUM(total_pecas), 0) AS pecas, COALESCE(SUM(total_mao_obra), 0) AS mao_obra
    FROM orcamentos
    WHERE status = 'aprovado'
")->fetch();
$grafico_receita_composicao = [
    'labels' => ['Peças', 'Mão de obra'],
    'valores' => [(float) $composicao['pecas'], (float) $composicao['mao_obra']],
];

// ============ VEÍCULOS ATENDIDOS POR MARCA ============
$grafico_marcas = ['labels' => [], 'valores' => []];
foreach ($conn->query("
    SELECT COALESCE(NULLIF(TRIM(v.marca), ''), 'Não informado') AS marca, COUNT(*) AS total
    FROM ordens_servico os
    LEFT JOIN veiculos v ON v.id = os.veiculo_id
    GROUP BY marca
    ORDER BY total DESC
    LIMIT 8
")->fetchAll() as $row) {
    $grafico_marcas['labels'][] = $row['marca'];
    $grafico_marcas['valores'][] = (int) $row['total'];
}

// ============ PEDIDOS DE PEÇAS POR STATUS ============
$pedido_status_map = [
    'solicitado' => ['label' => 'Solicitado', 'token' => 'warning'],
    'validado' => ['label' => 'Validado', 'token' => 'info'],
    'pedido' => ['label' => 'Pedido', 'token' => 'violet'],
    'a_caminho' => ['label' => 'A caminho', 'token' => 'blue'],
    'recebido' => ['label' => 'Recebido', 'token' => 'success'],
    'cancelado' => ['label' => 'Cancelado', 'token' => 'gray'],
];
$grafico_pedidos_status = ['labels' => [], 'valores' => [], 'cores' => []];
foreach ($conn->query("SELECT status, COUNT(*) AS total FROM pedidos_pecas GROUP BY status")->fetchAll() as $row) {
    if ((int) $row['total'] <= 0) {
        continue;
    }
    $info = $pedido_status_map[$row['status']] ?? ['label' => ucfirst($row['status']), 'token' => 'gray'];
    $grafico_pedidos_status['labels'][] = $info['label'];
    $grafico_pedidos_status['valores'][] = (int) $row['total'];
    $grafico_pedidos_status['cores'][] = $info['token'];
}

$nomes_meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$mes_nome = $nomes_meses[$mes_num];

$relatorios_config = [
    'faturamento' => $grafico_faturamento,
    'orcamentosStatus' => $grafico_orc_status,
    'osMes' => $grafico_os_mes,
    'osStatus' => $grafico_os_status,
    'osPrioridade' => $grafico_os_prioridade,
    'servicos' => $grafico_servicos,
    'pecas' => $grafico_pecas,
    'clientes' => $grafico_clientes,
    'receitaComposicao' => $grafico_receita_composicao,
    'marcas' => $grafico_marcas,
    'pedidosStatus' => $grafico_pedidos_status,
];

$scripts_extra = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
$scripts_extra .= '<script>window.RelatoriosConfig = ' . json_encode(
    $relatorios_config,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) . ';</script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/relatorios.js"></script>';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-report-analytics"></i>Relatórios</h1>
        <span class="page-subtitle">Análise de desempenho da oficina - <?php echo ucfirst($mes_nome); ?> <?php echo $ano_atual; ?></span>
    </div>
</div>

<!-- Navegação por seções -->
<ul class="nav nav-tabs mb-4" id="relatorioTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-visao-geral" type="button" role="tab" aria-selected="true">
            <i class="icon-base ti tabler-layout-dashboard me-1"></i>Visão geral
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ordens" type="button" role="tab" aria-selected="false">
            <i class="icon-base ti tabler-clipboard-list me-1"></i>Ordens de serviço
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-servicos" type="button" role="tab" aria-selected="false">
            <i class="icon-base ti tabler-tools me-1"></i>Serviços e peças
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-financeiro" type="button" role="tab" aria-selected="false">
            <i class="icon-base ti tabler-cash me-1"></i>Financeiro
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ============ VISÃO GERAL ============ -->
    <div class="tab-pane fade show active" id="tab-visao-geral" role="tabpanel">
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="stat-card border-accent">
                    <span class="stat-border"></span>
                    <div class="stat-header">
                        <span class="stat-label">Orçamentos</span>
                        <span class="stat-icon"><i class="icon-base ti tabler-file-text"></i></span>
                    </div>
                    <div class="stat-value"><?php echo (int) $resumo['total_orcamentos']; ?></div>
                    <div class="stat-delta"><?php echo (int) $resumo['aprovados']; ?> aprovados no mês</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card border-green">
                    <span class="stat-border"></span>
                    <div class="stat-header">
                        <span class="stat-label">Faturado</span>
                        <span class="stat-icon green"><i class="icon-base ti tabler-cash"></i></span>
                    </div>
                    <div class="stat-value" style="font-size:1.15rem;"><?php echo formatar_moeda($resumo['faturado']); ?></div>
                    <div class="stat-delta up"><i class="icon-base ti tabler-trending-up"></i> Ticket médio <?php echo formatar_moeda($ticket_medio); ?></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card border-yellow">
                    <span class="stat-border"></span>
                    <div class="stat-header">
                        <span class="stat-label">Em Aberto</span>
                        <span class="stat-icon yellow"><i class="icon-base ti tabler-hourglass"></i></span>
                    </div>
                    <div class="stat-value" style="font-size:1.15rem;"><?php echo formatar_moeda($resumo['em_aberto']); ?></div>
                    <div class="stat-delta">aguardando aprovação</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card border-blue">
                    <span class="stat-border"></span>
                    <div class="stat-header">
                        <span class="stat-label">Taxa Aprovação</span>
                        <span class="stat-icon blue"><i class="icon-base ti tabler-percentage"></i></span>
                    </div>
                    <div class="stat-value"><?php echo $taxa_aprovacao; ?>%</div>
                    <div class="stat-delta"><i class="icon-base ti tabler-clock"></i> conclusão em <?php echo $tempo_medio_dias > 0 ? $tempo_medio_dias . ' dias' : '-'; ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-chart-bar me-2"></i> Faturamento por Mês <span class="text-muted small ms-2">(orçamentos aprovados)</span>
                    </div>
                    <div class="card-body">
                        <div class="chart-box"><canvas id="chartFaturamento"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-chart-pie me-2"></i> Orçamentos por Status
                    </div>
                    <div class="card-body">
                        <div class="chart-box"><canvas id="chartOrcamentosStatus"></canvas></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ ORDENS DE SERVIÇO ============ -->
    <div class="tab-pane fade" id="tab-ordens" role="tabpanel">
        <div class="row g-3 mb-3">
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-calendar-stats me-2"></i> Ordens de Serviço por Mês
                    </div>
                    <div class="card-body">
                        <div class="chart-box"><canvas id="chartOsMes"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-flag me-2"></i> OS por Prioridade
                    </div>
                    <div class="card-body">
                        <div class="chart-box"><canvas id="chartOsPrioridade"></canvas></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-clipboard-list me-2"></i> Ordens de Serviço por Status
                    </div>
                    <div class="card-body">
                        <div class="chart-box chart-box-tall"><canvas id="chartOsStatus"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-truck me-2"></i> Pedidos de Peças por Status
                    </div>
                    <div class="card-body">
                        <div class="chart-box chart-box-tall"><canvas id="chartPedidosStatus"></canvas></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SERVIÇOS E PEÇAS ============ -->
    <div class="tab-pane fade" id="tab-servicos" role="tabpanel">
        <div class="row g-3 mb-3">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-tools me-2"></i> Serviços Mais Executados
                    </div>
                    <div class="card-body">
                        <div class="chart-box chart-box-tall"><canvas id="chartServicos"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-package me-2"></i> Peças Mais Requisitadas
                    </div>
                    <div class="card-body">
                        <div class="chart-box chart-box-tall"><canvas id="chartPecas"></canvas></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="icon-base ti tabler-car me-2"></i> Atendimentos por Marca de Veículo
                    </div>
                    <div class="card-body">
                        <div class="chart-box"><canvas id="chartMarcas"></canvas></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ FINANCEIRO ============ -->
    <div class="tab-pane fade" id="tab-financeiro" role="tabpanel">
        <div class="row g-3">
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-chart-pie me-2"></i> Composição da Receita
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="chart-box chart-box-tall flex-grow-1"><canvas id="chartReceitaComposicao"></canvas></div>
                        <p class="text-muted small text-center mb-0 mt-2">
                            Total aprovado: <strong><?php echo formatar_moeda($composicao['pecas'] + $composicao['mao_obra']); ?></strong>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="icon-base ti tabler-trophy me-2"></i> Top Clientes <span class="text-muted small ms-2">(maior valor gasto)</span>
                    </div>
                    <div class="card-body">
                        <div class="chart-box chart-box-tall"><canvas id="chartClientes"></canvas></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var abas = document.getElementById('relatorioTabs');
    if (!abas) return;
    abas.addEventListener('shown.bs.tab', function () {
        // Canvases dentro de abas ocultas são criados com 0px; forçamos o resize.
        var instancias = window.RelatoriosGraficos || {};
        Object.keys(instancias).forEach(function (id) {
            var grafico = instancias[id];
            if (grafico && typeof grafico.resize === 'function') {
                grafico.resize();
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
