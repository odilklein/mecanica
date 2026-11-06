<?php
$labels = $resource->labels();
$path = $resource->path();
$start = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$end = min($page * $perPage, $total);
$Status = \Modules\Mechanic\Support\PartOrderStatus::class;
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-truck"></i>Pedidos de peças</h1>
        <span class="page-subtitle">Acompanhe as peças solicitadas nas ordens de serviço</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>/create" class="btn btn-primary"><i class="icon-base ti tabler-plus"></i> Novo pedido</a>
    </div>
</div>

<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3">
        <div class="card crud-kpi">
            <div class="card-body">
                <div class="crud-kpi-icon bg-label-primary"><i class="icon-base ti tabler-truck"></i></div>
                <div class="crud-kpi-info">
                    <span class="crud-kpi-label">Pedidos</span>
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
                <input type="search" name="search" class="form-control" placeholder="Buscar por OS, cliente ou fornecedor..." value="<?= e($search) ?>" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-outline-secondary"><i class="icon-base ti tabler-search"></i> Buscar</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle responsive-card">
            <thead>
                <tr>
                    <th>OS</th>
                    <th>Cliente</th>
                    <th>Fornecedor</th>
                    <th>Status</th>
                    <th>Previsão</th>
                    <th class="actions-cell">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                <tr>
                    <td data-label="OS"><a href="/service-orders/<?= (int) $row['service_order_id'] ?>" class="fw-medium">#<?= str_pad((string) $row['order_number'], 4, '0', STR_PAD_LEFT) ?></a></td>
                    <td data-label="Cliente"><?= e($row['client_name'] ?: '—') ?></td>
                    <td data-label="Fornecedor"><?= e($row['supplier'] ?: '—') ?></td>
                    <td data-label="Status"><span class="badge <?= e($Status::badgeClass($row['status'])) ?>"><?= e($Status::label($row['status'])) ?></span></td>
                    <td data-label="Previsão"><?= $row['expected_date'] ? e(date('d/m/Y', strtotime($row['expected_date']))) : '—' ?></td>
                    <td class="actions-cell" data-label="Ações">
                        <div class="d-flex gap-1">
                            <a href="<?= e($path . '/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-icon btn-text-primary" title="Editar"><i class="icon-base ti tabler-pencil"></i></a>
                            <form method="POST" action="<?= e($path . '/' . (int) $row['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Excluir este pedido?')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button class="btn btn-sm btn-icon btn-text-danger" type="submit" title="Excluir"><i class="icon-base ti tabler-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                <tr class="row-empty">
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="icon-base ti tabler-truck"></i></div>
                            <h4>Nenhum pedido</h4>
                            <p>Nenhum pedido de peça encontrado.</p>
                            <a href="<?= e($path) ?>/create" class="btn btn-primary"><i class="icon-base ti tabler-plus"></i> Novo pedido</a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-panel">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-body-secondary">Mostrando <?= $start ?> a <?= $end ?> de <?= (int) $total ?> pedidos</span>
            <?= pagination_links($page, $lastPage, $path, array_filter(['search' => $search])) ?>
        </div>
    </div>
</div>
