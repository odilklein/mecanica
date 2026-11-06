<?php
$labels = $resource->labels();
$path = $resource->path();
$columns = $resource->columns();
?>
<div class="page-header">
    <div>
        <h1 class="mb-1"><?= e($labels['plural']) ?></h1>
        <span class="page-subtitle"><?= e($total) ?> registro(s) encontrado(s)</span>
    </div>
    <a href="<?= e($path) ?>/create" class="btn btn-primary"><i class="icon-base ti tabler-plus me-1"></i>Novo(a) <?= e($labels['singular']) ?></a>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="<?= e($path) ?>" class="d-flex gap-2 w-100">
            <div class="input-group">
                <span class="input-group-text"><i class="icon-base ti tabler-search"></i></span>
                <input type="search" name="search" class="form-control" placeholder="Buscar <?= e(strtolower($labels['plural'])) ?>..." value="<?= e($search) ?>">
            </div>
            <button class="btn btn-outline-secondary" type="submit">Buscar</button>
        </form>
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?><th><?= e($col['label']) ?></th><?php endforeach; ?>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <?php $value = (string) ($row[$col['field']] ?? ''); ?>
                    <td><?= e($value !== '' ? $value : '—') ?></td>
                    <?php endforeach; ?>
                    <td>
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
                <tr>
                    <td colspan="<?= count($columns) + 1 ?>" class="text-center py-5 text-body-secondary">Nenhum registro encontrado.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($lastPage > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination justify-content-end mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($path) ?>?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Anterior</a></li>
                <?php for ($i = 1; $i <= $lastPage; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($path) ?>?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($path) ?>?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Próxima</a></li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
