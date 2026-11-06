<?php
$brand = setting('theme_color', '#b8270f');

$osLabels = [];
$osValues = [];
foreach (\Modules\Mechanic\Support\ServiceOrderStatus::STATUSES as $key => $label) {
    $osLabels[] = $label;
    $osValues[] = $osByStatus[$key] ?? 0;
}

$budgetLabels = [];
$budgetValues = [];
foreach (\Modules\Mechanic\Support\BudgetStatus::STATUSES as $key => $label) {
    $budgetLabels[] = $label;
    $budgetValues[] = $budgetsByStatus[$key]['count'] ?? 0;
}

$partsLabels = [];
$partsValues = [];
foreach (\Modules\Mechanic\Support\PartOrderStatus::STATUSES as $key => $label) {
    $partsLabels[] = $label;
    $partsValues[] = $partsByStatus[$key] ?? 0;
}
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-chart-line"></i>Relatórios</h1>
        <span class="page-subtitle">Visão geral da oficina</span>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card crud-kpi"><div class="card-body">
            <div class="crud-kpi-icon bg-label-primary"><i class="icon-base ti tabler-users"></i></div>
            <div class="crud-kpi-info"><span class="crud-kpi-label">Clientes</span><span class="crud-kpi-value"><?= (int) $kpis['clients'] ?></span></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card crud-kpi"><div class="card-body">
            <div class="crud-kpi-icon bg-label-success"><i class="icon-base ti tabler-car"></i></div>
            <div class="crud-kpi-info"><span class="crud-kpi-label">Veículos</span><span class="crud-kpi-value"><?= (int) $kpis['vehicles'] ?></span></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card crud-kpi"><div class="card-body">
            <div class="crud-kpi-icon bg-label-warning"><i class="icon-base ti tabler-clipboard-list"></i></div>
            <div class="crud-kpi-info"><span class="crud-kpi-label">OS em aberto</span><span class="crud-kpi-value"><?= (int) $kpis['open_orders'] ?></span></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card crud-kpi"><div class="card-body">
            <div class="crud-kpi-icon bg-label-info"><i class="icon-base ti tabler-currency-real"></i></div>
            <div class="crud-kpi-info"><span class="crud-kpi-label">Orçamentos aprovados</span><span class="crud-kpi-value">R$ <?= number_format((float) $kpis['approved_revenue'], 2, ',', '.') ?></span></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-chart-bar"></i><div><h3 class="panel-title">OS por status</h3></div></div>
            <div style="height: 300px;"><canvas id="osChart"></canvas></div>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-chart-pie"></i><div><h3 class="panel-title">Orçamentos por status</h3></div></div>
            <div style="height: 300px;"><canvas id="budgetChart"></canvas></div>
        </div></div>
    </div>
    <div class="col-12">
        <div class="card"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-truck"></i><div><h3 class="panel-title">Pedidos de peças por status</h3></div></div>
            <div style="height: 260px;"><canvas id="partsChart"></canvas></div>
        </div></div>
    </div>
</div>

<script src="/assets/vendor/libs/chartjs/chartjs.js"></script>
<script>
(function () {
    var brand = <?= json_encode($brand) ?>;
    var osLabels = <?= json_encode($osLabels, JSON_UNESCAPED_UNICODE) ?>;
    var osValues = <?= json_encode($osValues) ?>;
    var budgetLabels = <?= json_encode($budgetLabels, JSON_UNESCAPED_UNICODE) ?>;
    var budgetValues = <?= json_encode($budgetValues) ?>;
    var partsLabels = <?= json_encode($partsLabels, JSON_UNESCAPED_UNICODE) ?>;
    var partsValues = <?= json_encode($partsValues) ?>;

    if (typeof Chart === 'undefined') { return; }

    Chart.defaults.font.family = "'Public Sans', sans-serif";

    new Chart(document.getElementById('osChart'), {
        type: 'bar',
        data: { labels: osLabels, datasets: [{ label: 'OS', data: osValues, backgroundColor: brand, borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    new Chart(document.getElementById('budgetChart'), {
        type: 'doughnut',
        data: { labels: budgetLabels, datasets: [{ data: budgetValues, backgroundColor: ['#0dcaf0', '#198754', '#dc3545', '#6c757d'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('partsChart'), {
        type: 'bar',
        data: { labels: partsLabels, datasets: [{ label: 'Pedidos', data: partsValues, backgroundColor: '#6c757d', borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
})();
</script>
