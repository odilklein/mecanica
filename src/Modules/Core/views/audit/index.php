<?php
$start = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$end = min($page * $perPage, $total);
$labels = [
    'create' => 'Criou',
    'update' => 'Atualizou',
    'delete' => 'Excluiu',
    'login' => 'Login',
];
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-history"></i>Auditoria</h1>
        <span class="page-subtitle">Registro de ações do sistema</span>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle responsive-card">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <th>Entidade</th>
                    <th>Registro</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                <tr>
                    <td data-label="Data"><small class="text-body-secondary"><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></small></td>
                    <td data-label="Usuário"><?= e($row['user_name'] ?: 'Sistema') ?></td>
                    <td data-label="Ação"><span class="badge bg-label-secondary"><?= e($labels[$row['action']] ?? $row['action']) ?></span></td>
                    <td data-label="Entidade"><?= e($row['entity']) ?></td>
                    <td data-label="Registro"><?= $row['entity_id'] ? '#' . (int) $row['entity_id'] : '—' ?></td>
                    <td data-label="Detalhes"><small class="text-body-secondary"><?= $row['data'] ? e(mb_substr((string) $row['data'], 0, 120)) : '—' ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                <tr class="row-empty">
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="icon-base ti tabler-history"></i></div>
                            <h4>Nenhum registro</h4>
                            <p>Nenhuma ação registrada ainda.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-panel">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-body-secondary">Mostrando <?= $start ?> a <?= $end ?> de <?= (int) $total ?> registros</span>
            <?= pagination_links($page, $lastPage, '/audit') ?>
        </div>
    </div>
</div>
