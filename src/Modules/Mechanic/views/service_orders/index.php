<?php
$labels = $resource->labels();
$path = $resource->path();
$start = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$end = min($page * $perPage, $total);
$Status = \Modules\Mechanic\Support\ServiceOrderStatus::class;
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-clipboard-list"></i>Ordens de serviço</h1>
        <span class="page-subtitle">Acompanhe o trabalho da oficina</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>/create" class="btn btn-primary"><i class="icon-base ti tabler-plus"></i> Nova OS</a>
    </div>
</div>

<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3">
        <div class="card crud-kpi">
            <div class="card-body">
                <div class="crud-kpi-icon bg-label-primary"><i class="icon-base ti tabler-clipboard-list"></i></div>
                <div class="crud-kpi-info">
                    <span class="crud-kpi-label">Ordens de serviço</span>
                    <span class="crud-kpi-value"><?= (int) $total ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <form method="GET" action="<?= e($path) ?>" class="table-panel">
        <div class="crud-toolbar">
            <div class="crud-search">
                <i class="icon-base ti tabler-search"></i>
                <input type="search" name="search" class="form-control" placeholder="Buscar por nº, cliente ou placa..." value="<?= e($search) ?>" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-outline-secondary"><i class="icon-base ti tabler-search"></i> Buscar</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle responsive-card">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Cliente</th>
                    <th>Veículo</th>
                    <th>Status</th>
                    <th>Prioridade</th>
                    <th>Entrada</th>
                    <th class="actions-cell">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                <tr>
                    <td data-label="Nº"><a href="<?= e($path . '/' . (int) $row['id']) ?>" class="fw-medium">#<?= str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT) ?></a></td>
                    <td data-label="Cliente"><?= e($row['client_name'] ?: '—') ?></td>
                    <td data-label="Veículo"><?= e($row['vehicle_plate'] ?: '—') ?><?= $row['vehicle_model'] ? ' · ' . e($row['vehicle_model']) : '' ?></td>
                    <td data-label="Status"><span class="badge <?= e($Status::badgeClass($row['status'])) ?>"><?= e($Status::label($row['status'])) ?></span></td>
                    <td data-label="Prioridade"><span class="badge <?= e($Status::priorityBadge($row['priority'])) ?>"><?= e($Status::priorityLabel($row['priority'])) ?></span></td>
                    <td data-label="Entrada"><?= $row['entry_date'] ? e(date('d/m/Y', strtotime($row['entry_date']))) : '—' ?></td>
                    <td class="actions-cell" data-label="Ações">
                        <div class="d-flex gap-1">
                            <a href="<?= e($path . '/' . (int) $row['id']) ?>" class="btn btn-sm btn-icon btn-text-info" title="Detalhes"><i class="icon-base ti tabler-eye"></i></a>
                            <a href="<?= e($path . '/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-icon btn-text-primary" title="Editar"><i class="icon-base ti tabler-pencil"></i></a>
                            <form method="POST" action="<?= e($path . '/' . (int) $row['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Excluir esta OS?')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button class="btn btn-sm btn-icon btn-text-danger" type="submit" title="Excluir"><i class="icon-base ti tabler-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                <tr class="row-empty">
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="icon-base ti tabler-clipboard-list"></i></div>
                            <h4>Nenhuma OS</h4>
                            <p>Nenhuma ordem de serviço encontrada.</p>
                            <a href="<?= e($path) ?>/create" class="btn btn-primary"><i class="icon-base ti tabler-plus"></i> Nova OS</a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-panel">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-body-secondary">Mostrando <?= $start ?> a <?= $end ?> de <?= (int) $total ?> ordens</span>
            <?= pagination_links($page, $lastPage, $path, array_filter(['search' => $search])) ?>
        </div>
    </div>
</div>
