<?php
$labels = $resource->labels();
$path = $resource->path();
$columns = $resource->columns();
$start = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$end = min($page * $perPage, $total);
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-<?= e($resource->icon()) ?>"></i><?= e($labels['plural']) ?></h1>
        <span class="page-subtitle">Gerencie os <?= e(strtolower($labels['plural'])) ?> do sistema</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>/create" class="btn btn-primary"><i class="icon-base ti tabler-plus"></i> Novo(a) <?= e($labels['singular']) ?></a>
    </div>
</div>

<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3">
        <div class="card crud-kpi">
            <div class="card-body">
                <div class="crud-kpi-icon bg-label-primary"><i class="icon-base ti tabler-<?= e($resource->icon()) ?>"></i></div>
                <div class="crud-kpi-info">
                    <span class="crud-kpi-label"><?= e($labels['plural']) ?></span>
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
                <input type="search" name="search" class="form-control" placeholder="Buscar <?= e(strtolower($labels['plural'])) ?>..." value="<?= e($search) ?>" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-outline-secondary"><i class="icon-base ti tabler-search"></i> Buscar</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle responsive-card">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?><th><?= e($col['label']) ?></th><?php endforeach; ?>
                    <th class="actions-cell">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                <tr>
                    <?php $firstCol = true; ?>
                    <?php foreach ($columns as $col): ?>
                    <?php
                        $raw = $row[$col['field']] ?? null;
                        $value = (string) $raw;
                        if (isset($col['format']) && is_callable($col['format'])) {
                            $value = (string) $col['format']($raw);
                        }
                        $detail = $resource->detailPath();
                    ?>
                    <td data-label="<?= e($col['label']) ?>">
                        <?php if ($firstCol && $detail !== null): ?>
                        <a href="<?= e($detail . '/' . (int) $row['id']) ?>" class="fw-medium"><?= e($value !== '' ? $value : '—') ?></a>
                        <?php else: ?>
                        <?= e($value !== '' ? $value : '—') ?>
                        <?php endif; ?>
                    </td>
                    <?php $firstCol = false; ?>
                    <?php endforeach; ?>
                    <td class="actions-cell" data-label="Ações">
                        <div class="d-flex gap-1">
                            <a href="<?= e($path . '/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-icon btn-text-primary" title="Editar"><i class="icon-base ti tabler-pencil"></i></a>
                            <form method="POST" action="<?= e($path . '/' . (int) $row['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Excluir este registro?')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button class="btn btn-sm btn-icon btn-text-danger" type="submit" title="Excluir"><i class="icon-base ti tabler-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (!$items): ?>
                <tr class="row-empty">
                    <td colspan="<?= count($columns) + 1 ?>">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="icon-base ti tabler-<?= e($resource->icon()) ?>"></i></div>
                            <h4>Nenhum registro</h4>
                            <p>Nenhum(a) <?= e(strtolower($labels['singular'])) ?> encontrado(a).</p>
                            <a href="<?= e($path) ?>/create" class="btn btn-primary"><i class="icon-base ti tabler-plus"></i> Novo(a) <?= e($labels['singular']) ?></a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-panel">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-body-secondary">Mostrando <?= $start ?> a <?= $end ?> de <?= (int) $total ?> <?= e(strtolower($labels['plural'])) ?></span>
            <?= pagination_links($page, $lastPage, $path, array_filter(['search' => $search])) ?>
        </div>
    </div>
</div>
