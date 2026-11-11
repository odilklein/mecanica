<?php
$start = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$end = min($page * $perPage, $total);
$filters = $filters ?? [];
$users = $users ?? [];
$actions = $actions ?? [];
$entities = $entities ?? [];
$queryParams = $queryParams ?? [];

$entityLabels = [
    'auth' => 'Login',
    'clients' => 'Cliente',
    'vehicles' => 'Veículo',
    'service-orders' => 'OS',
    'service_orders' => 'OS',
    'budgets' => 'Orçamento',
    'part-orders' => 'Pedido de peça',
    'part_orders' => 'Pedido de peça',
    'parts' => 'Peça',
    'part-categories' => 'Categoria de peça',
    'part_categories' => 'Categoria de peça',
    'suppliers' => 'Fornecedor',
    'users' => 'Funcionário',
    'settings' => 'Configurações',
    'tickets' => 'Ticket',
    'tasks' => 'Tarefa',
];
$actionLabels = [
    'create' => 'Criou',
    'update' => 'Atualizou',
    'delete' => 'Excluiu',
    'login' => 'Login',
    'logout' => 'Logout',
    'status' => 'Status',
    'delivery' => 'Entrega',
    'diagnosis' => 'Diagnóstico',
    'responsible' => 'Equipe',
    'task' => 'Tarefa',
    'part' => 'Peça',
];
$actionBadges = [
    'create' => 'success',
    'update' => 'primary',
    'delete' => 'danger',
    'login' => 'secondary',
    'status' => 'info',
    'delivery' => 'success',
    'diagnosis' => 'info',
    'responsible' => 'warning',
    'task' => 'warning',
    'part' => 'primary',
];
$recordLinks = [
    'clients' => '/clients/{id}',
    'vehicles' => '/vehicles/{id}/edit',
    'service-orders' => '/service-orders/{id}',
    'service_orders' => '/service-orders/{id}',
    'budgets' => '/budgets/{id}',
    'part-orders' => '/part-orders/{id}',
    'part_orders' => '/part-orders/{id}',
    'parts' => '/parts/{id}/edit',
    'suppliers' => '/suppliers/{id}/edit',
    'users' => '/users/{id}/edit',
];
$statusLabelFor = static function (string $entity, string $value): string {
    return match ($entity) {
        'service-orders', 'service_orders' => \Modules\Mechanic\Support\ServiceOrderStatus::label($value),
        'budgets' => \Modules\Mechanic\Support\BudgetStatus::label($value),
        'part-orders', 'part_orders' => \Modules\Mechanic\Support\PartOrderStatus::label($value),
        default => $value,
    };
};
$detailMap = [
    'from' => 'De',
    'to' => 'Para',
    'status' => 'Status',
    'supplier' => 'Fornecedor',
    'service_order_id' => 'OS',
    'order' => 'Pedido',
    'order_id' => 'OS',
    'user_id' => 'Usuário',
    'self' => 'Autoedição',
    'seed' => 'Seed',
    'name' => 'Nome',
    'email' => 'E-mail',
    'role' => 'Perfil',
    'plate' => 'Placa',
    'discount' => 'Desconto',
    'total' => 'Total',
    'valid_until' => 'Validade',
    'notes' => 'Observações',
];
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-history"></i>Auditoria</h1>
        <span class="page-subtitle">Registro de ações do sistema</span>
    </div>
</div>

<form method="GET" action="/audit" class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label" for="auditScope">Tipo</label>
                <select class="form-select" id="auditScope" name="scope">
                    <option value="changes" <?= ($filters['scope'] ?? 'changes') === 'changes' ? 'selected' : '' ?>>Alterações</option>
                    <option value="logins" <?= ($filters['scope'] ?? '') === 'logins' ? 'selected' : '' ?>>Logins</option>
                    <option value="all" <?= ($filters['scope'] ?? '') === 'all' ? 'selected' : '' ?>>Todos</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="auditUser">Usuário</label>
                <select class="select2 form-select" id="auditUser" name="user_id" data-placeholder="Todos">
                    <option value=""></option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?= (int) $user['id'] ?>" <?= (int) ($filters['user_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                        <?= e($user['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="auditAction">Ação</label>
                <select class="select2 form-select" id="auditAction" name="action" data-placeholder="Todas">
                    <option value=""></option>
                    <?php foreach ($actions as $action): ?>
                    <option value="<?= e($action) ?>" <?= ($filters['action'] ?? '') === $action ? 'selected' : '' ?>>
                        <?= e($actionLabels[$action] ?? $action) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="auditEntity">Entidade</label>
                <select class="select2 form-select" id="auditEntity" name="entity" data-placeholder="Todas">
                    <option value=""></option>
                    <?php foreach ($entities as $entity): ?>
                    <option value="<?= e($entity) ?>" <?= ($filters['entity'] ?? '') === $entity ? 'selected' : '' ?>>
                        <?= e($entityLabels[$entity] ?? $entity) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="auditFrom">De</label>
                <input type="date" class="form-control" id="auditFrom" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="auditTo">Até</label>
                <input type="date" class="form-control" id="auditTo" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                <a href="/audit" class="btn btn-outline-secondary">Limpar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="icon-base ti tabler-filter"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
</form>

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
                <?php
                $entityKey = (string) $row['entity'];
                $recordId = (int) ($row['entity_id'] ?? 0);
                $recordLink = ($recordId > 0 && isset($recordLinks[$entityKey]))
                    ? str_replace('{id}', (string) $recordId, $recordLinks[$entityKey])
                    : null;
                $badge = $actionBadges[$row['action']] ?? 'secondary';

                $detailParts = [];
                if (!empty($row['data'])) {
                    $decoded = json_decode((string) $row['data'], true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $key => $value) {
                            $label = $detailMap[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
                            if (in_array($key, ['from', 'to', 'status'], true)) {
                                $value = $statusLabelFor($entityKey, (string) $value);
                            } elseif (is_bool($value)) {
                                $value = $value ? 'Sim' : 'Não';
                            } elseif (is_array($value)) {
                                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                            }
                            $detailParts[] = $label . ': ' . (string) $value;
                        }
                    } else {
                        $detailParts[] = (string) $row['data'];
                    }
                }
                ?>
                <tr>
                    <td data-label="Data"><small class="text-body-secondary"><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></small></td>
                    <td data-label="Usuário"><?= e($row['user_name'] ?: 'Sistema') ?></td>
                    <td data-label="Ação">
                        <span class="badge bg-label-<?= e($badge) ?>"><?= e($actionLabels[$row['action']] ?? $row['action']) ?></span>
                    </td>
                    <td data-label="Entidade"><?= e($entityLabels[$entityKey] ?? $entityKey) ?></td>
                    <td data-label="Registro">
                        <?php if ($recordLink): ?>
                        <a href="<?= e($recordLink) ?>">#<?= $recordId ?></a>
                        <?php elseif ($recordId > 0): ?>
                        #<?= $recordId ?>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                    <td data-label="Detalhes">
                        <?php if ($detailParts): ?>
                        <small class="text-body-secondary d-block">
                            <?= e(implode(' · ', array_slice($detailParts, 0, 4))) ?><?= count($detailParts) > 4 ? ' …' : '' ?>
                        </small>
                        <?php else: ?>
                        <small class="text-body-secondary">—</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                <tr class="row-empty">
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="icon-base ti tabler-history"></i></div>
                            <h4>Nenhum registro</h4>
                            <p>Nenhuma ação encontrada com os filtros atuais.</p>
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
            <?= pagination_links($page, $lastPage, '/audit', $queryParams) ?>
        </div>
    </div>
</div>
