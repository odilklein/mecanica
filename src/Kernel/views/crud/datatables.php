<?php
$datatable = $datatable ?? [];
$columns = $datatable['columns'] ?? [];
$filters = $datatable['filters'] ?? [];
$configJson = json_encode(
    $datatable,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-<?= e($resource->icon()) ?>"></i><?= e($datatable['title'] ?? $resource->labels()['plural']) ?></h1>
        <span class="page-subtitle"><?= e($datatable['subtitle'] ?? '') ?></span>
    </div>
    <div class="page-header-actions">
        <?php if ($datatable['canCreate'] ?? true): ?>
        <a href="<?= e($resource->path()) ?>/create" class="btn btn-primary">
            <i class="icon-base ti tabler-plus"></i> <?= e($datatable['newLabel'] ?? 'Novo') ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Fixed Header (tables-datatables-extensions) -->
<div class="card">
    <h5 class="card-header text-md-start text-center border-bottom"><?= e($datatable['cardTitle'] ?? '') ?></h5>
    <?php if ($filters): ?>
    <div class="card-body border-bottom py-3" id="dtFilters">
        <div class="row g-2 align-items-end">
            <?php foreach ($filters as $filter): ?>
            <div class="col-md-3">
                <label class="form-label" for="dtFilter-<?= e($filter['field']) ?>"><?= e($filter['label']) ?></label>
                <select class="select2 form-select" id="dtFilter-<?= e($filter['field']) ?>" data-dt-filter="<?= e($filter['field']) ?>" data-placeholder="Todos">
                    <option value=""></option>
                </select>
            </div>
            <?php endforeach; ?>
            <div class="col-md-2">
                <button type="button" class="btn btn-label-secondary w-100" id="dtClearFilters">
                    <i class="icon-base ti tabler-filter-off"></i> Limpar
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="card-datatable table-responsive">
        <table class="dt-fixedheader table table-bordered">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <th><?= e($col['title'] ?? '') ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <th><?= e($col['title'] ?? '') ?></th>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>window.CRUD_DATATABLE = <?= $configJson ?>;</script>
