<?php
$datatable = $datatable ?? [];
$columns = $datatable['columns'] ?? [];
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
        <a href="<?= e($resource->path()) ?>/create" class="btn btn-primary">
            <i class="icon-base ti tabler-plus"></i> <?= e($datatable['newLabel'] ?? 'Novo') ?>
        </a>
    </div>
</div>

<!-- Fixed Header (Vuexy — tables-datatables-extensions) -->
<div class="card">
    <h5 class="card-header text-md-start text-center border-bottom"><?= e($datatable['cardTitle'] ?? '') ?></h5>
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
