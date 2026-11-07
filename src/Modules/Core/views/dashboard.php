<?php
$Status = \Modules\Mechanic\Support\ServiceOrderStatus::class;
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-layout-dashboard"></i>Painel</h1>
        <span class="page-subtitle">Acompanhe o trabalho da oficina</span>
    </div>
    <div class="page-header-actions">
        <a href="/service-orders/create" class="btn btn-primary"><i class="icon-base ti tabler-clipboard-plus"></i> Novo atendimento</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card crud-kpi"><div class="card-body">
            <div class="crud-kpi-icon bg-label-primary"><i class="icon-base ti tabler-users"></i></div>
            <div class="crud-kpi-info"><span class="crud-kpi-label">Clientes</span><span class="crud-kpi-value"><?= (int) $kpis['clients'] ?></span></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card crud-kpi"><div class="card-body">
            <div class="crud-kpi-icon bg-label-info"><i class="icon-base ti tabler-users-group"></i></div>
            <div class="crud-kpi-info"><span class="crud-kpi-label">Funcionários</span><span class="crud-kpi-value"><?= (int) $kpis['employees'] ?></span></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card crud-kpi"><div class="card-body">
            <div class="crud-kpi-icon bg-label-warning"><i class="icon-base ti tabler-clipboard-list"></i></div>
            <div class="crud-kpi-info"><span class="crud-kpi-label">OS em andamento</span><span class="crud-kpi-value"><?= (int) $kpis['open_orders'] ?></span></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card crud-kpi"><div class="card-body">
            <div class="crud-kpi-icon bg-label-danger"><i class="icon-base ti tabler-package"></i></div>
            <div class="crud-kpi-info"><span class="crud-kpi-label">Peças pendentes</span><span class="crud-kpi-value"><?= (int) $kpis['pending_parts'] ?></span></div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="panel-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2"><i class="icon-base ti tabler-clipboard-list"></i><h3 class="panel-title mb-0">OS em andamento</h3></div>
            <a href="/service-orders" class="btn btn-sm btn-outline-secondary">Ver todas</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle responsive-card mb-0">
                <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Prioridade</th><th>Entrada</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td data-label="OS"><a href="/service-orders/<?= (int) $r['id'] ?>" class="fw-medium">#<?= str_pad((string) $r['number'], 4, '0', STR_PAD_LEFT) ?></a></td>
                        <td data-label="Cliente"><?= e($r['client'] ?: '—') ?></td>
                        <td data-label="Veículo"><?= e($r['vehicle_plate'] ?: '—') ?><?= $r['vehicle_model'] ? ' · ' . e($r['vehicle_model']) : '' ?></td>
                        <td data-label="Prioridade"><span class="badge <?= e($Status::priorityBadge($r['priority'])) ?>"><?= e($Status::priorityLabel($r['priority'])) ?></span></td>
                        <td data-label="Entrada"><?= $r['entry_date'] ? e(date('d/m/Y', strtotime($r['entry_date']))) : '—' ?></td>
                        <td data-label="Status"><span class="badge <?= e($Status::badgeClass($r['status'])) ?>"><?= e($Status::label($r['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">Nenhuma OS em andamento.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
