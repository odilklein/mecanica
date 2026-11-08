<?php
$start = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$end = min($page * $perPage, $total);
$icons = [
    'os_status' => 'arrows-exchange',
    'task' => 'list-check',
    'part' => 'package',
    'os_created' => 'clipboard-plus',
    'ticket' => 'messages',
];
$typeLabels = [
    'os_status' => 'Ordem de serviço',
    'task' => 'Tarefa',
    'part' => 'Pedido de peça',
    'os_created' => 'Nova ordem de serviço',
    'ticket' => 'Ticket',
];
$colors = [
    'os_status' => 'bg-label-info',
    'task' => 'bg-label-success',
    'part' => 'bg-label-warning',
    'os_created' => 'bg-label-primary',
    'ticket' => 'bg-label-info',
];
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-bell"></i>Notificações</h1>
        <span class="page-subtitle"><?= (int) $total ?> notificação(ões)</span>
    </div>
    <div class="page-header-actions">
        <form method="POST" action="/notifications/read-all" onsubmit="return true;">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <button type="submit" class="btn btn-outline-secondary"><i class="icon-base ti tabler-checks"></i> Marcar todas como lidas</button>
        </form>
    </div>
</div>

<div class="card">
    <ul class="list-group list-group-flush">
        <?php foreach ($items as $row): ?>
        <?php
            $unread = empty($row['read_at']);
            $actor = $row['created_by_name'] ?: 'Sistema';
            $recipient = $row['user_name'] ?: '—';
            $initials = '';
            if (preg_match_all('/\b\w/u', $actor, $m)) {
                $initials = strtoupper(implode('', array_slice($m[0], 0, 2)));
            }
            $initials = $initials ?: '?';
        ?>
        <li class="list-group-item d-flex align-items-start gap-3 <?= $unread ? 'bg-label-primary' : '' ?>">
            <div class="avatar">
                <span class="avatar-initial rounded-circle <?= e($colors[$row['type']] ?? 'bg-label-secondary') ?>">
                    <?= e($initials) ?>
                </span>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span class="fw-medium">
                        <?= e($actor) ?>
                        <span class="fw-normal text-body-secondary">·
                            <i class="icon-base ti tabler-<?= e($icons[$row['type']] ?? 'bell') ?> icon-xs me-1"></i>
                            <?= e($typeLabels[$row['type']] ?? 'Notificação') ?>
                        </span>
                    </span>
                    <small class="text-body-secondary"><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></small>
                </div>
                <p class="mb-2 mt-1"><?= e($row['message']) ?></p>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <small class="text-body-secondary">
                        <i class="icon-base ti tabler-user-check icon-xs me-1"></i>Para: <?= e($recipient) ?>
                    </small>
                    <?php if (!empty($row['link'])): ?>
                    <a href="<?= e($row['link']) ?>" class="small"><i class="icon-base ti tabler-external-link me-1"></i>Abrir</a>
                    <?php endif; ?>
                    <?php if ($unread): ?>
                    <form method="POST" action="/notifications/<?= (int) $row['id'] ?>/read" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-text-primary p-0"><i class="icon-base ti tabler-check me-1"></i>Marcar como lida</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
        <?php if (!$items): ?>
        <li class="list-group-item">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="icon-base ti tabler-bell-off"></i></div>
                <h4>Nenhuma notificação</h4>
                <p>Você não tem notificações no momento.</p>
            </div>
        </li>
        <?php endif; ?>
    </ul>
    <div class="table-panel">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-body-secondary">Mostrando <?= $start ?> a <?= $end ?> de <?= (int) $total ?> notificações</span>
            <?= pagination_links($page, $lastPage, '/notifications') ?>
        </div>
    </div>
</div>
