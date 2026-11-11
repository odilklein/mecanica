<?php
$path = $resource->path();
$Status = \Modules\Mechanic\Support\BudgetStatus::class;
$budgetId = (int) $budget['id'];
$typeLabels = ['part' => 'Peça', 'service' => 'Serviço'];
$history = $history ?? [];
$statusAvailable = $statusAvailable ?? ($statuses ?? []);
$editable = $Status::isEditable((string) $budget['status']);
?>
<div class="page-header">
    <div>
        <h1>
            <i class="icon-base ti tabler-file-text"></i>
            Orçamento #<?= str_pad((string) $budget['number'], 4, '0', STR_PAD_LEFT) ?>
            <span class="badge <?= e($Status::badgeClass($budget['status'])) ?> ms-2"><?= e($Status::label($budget['status'])) ?></span>
        </h1>
        <span class="page-subtitle">Detalhes e itens do orçamento</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
        <a href="<?= e($path . '/' . $budgetId . '/print') ?>" target="_blank" class="btn btn-outline-primary"><i class="icon-base ti tabler-printer"></i> Imprimir</a>
        <?php if ($editable): ?>
        <a href="<?= e($path . '/' . $budgetId . '/edit') ?>" class="btn btn-primary"><i class="icon-base ti tabler-pencil"></i> Editar</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-info-circle"></i><div><h3 class="panel-title">Dados</h3></div></div>
            <div class="row g-3">
                <div class="col-12"><small class="text-body-secondary d-block">Cliente</small><span class="fw-medium"><?= e($budget['client_name'] ?: '—') ?></span></div>
                <?php if (!empty($budget['client_phone']) || !empty($budget['client_email'])): ?>
                <div class="col-12">
                    <small class="text-body-secondary d-block">Contato</small>
                    <span><?= e($budget['client_phone'] ?: '—') ?><?= $budget['client_email'] ? ' · ' . e($budget['client_email']) : '' ?></span>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <small class="text-body-secondary d-block">Veículo</small>
                    <span>
                        <?= e($budget['vehicle_plate'] ?: '—') ?>
                        <?= $budget['vehicle_model'] ? ' · ' . e($budget['vehicle_model']) : '' ?>
                        <?= !empty($budget['vehicle_year']) ? ' · ' . (int) $budget['vehicle_year'] : '' ?>
                        <?= !empty($budget['vehicle_color']) ? ' · ' . e($budget['vehicle_color']) : '' ?>
                    </span>
                </div>
                <div class="col-12">
                    <small class="text-body-secondary d-block">OS vinculada</small>
                    <span>
                        <?php if ($budget['order_number']): ?>
                        <a href="/service-orders/<?= (int) ($budget['service_order_id'] ?? 0) ?>" class="fw-medium">
                            OS #<?= str_pad((string) $budget['order_number'], 4, '0', STR_PAD_LEFT) ?>
                        </a>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </span>
                </div>
                <div class="col-6"><small class="text-body-secondary d-block">Data</small><span><?= $budget['budget_date'] ? e(date('d/m/Y', strtotime($budget['budget_date']))) : '—' ?></span></div>
                <div class="col-6"><small class="text-body-secondary d-block">Validade</small><span><?= $budget['valid_until'] ? e(date('d/m/Y', strtotime($budget['valid_until']))) : '—' ?></span></div>
                <?php if (!empty($budget['notes'])): ?>
                <div class="col-12"><small class="text-body-secondary d-block">Observações</small><p class="mb-0"><?= nl2br(e($budget['notes'])) ?></p></div>
                <?php endif; ?>
                <?php if (!empty($budget['created_by_name'])): ?>
                <div class="col-12"><small class="text-body-secondary d-block">Criado por</small><span><?= e($budget['created_by_name']) ?></span></div>
                <?php endif; ?>
            </div>
        </div></div>

        <div class="card mt-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-progress-check"></i><div><h3 class="panel-title">Status</h3></div></div>
            <form method="POST" action="<?= e($path . '/' . $budgetId . '/status') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label" for="budgetStatus">Situação do orçamento</label>
                    <select class="select2 form-select" id="budgetStatus" name="status" data-placeholder="Selecione">
                        <?php foreach ($statusAvailable as $statusKey): ?>
                        <option value="<?= e($statusKey) ?>" <?= $budget['status'] === $statusKey ? 'selected' : '' ?>><?= e($Status::label($statusKey)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="icon-base ti tabler-device-floppy"></i> Atualizar status
                </button>
                <?php if (!$editable): ?>
                <div class="alert alert-info py-2 mt-3 mb-0 small">
                    <i class="icon-base ti tabler-info-circle me-1"></i>
                    Para editar os itens, reabra o orçamento com status <strong>Aberto</strong>.
                </div>
                <?php endif; ?>
            </form>
        </div></div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-list-details"></i><div><h3 class="panel-title">Itens</h3></div></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Tipo</th><th>Descrição</th><th>Qtd</th><th>Unitário</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><span class="badge <?= $item['type'] === 'service' ? 'bg-label-info' : 'bg-label-primary' ?>"><?= e($typeLabels[$item['type']] ?? $item['type']) ?></span></td>
                            <td><?= e($item['description']) ?></td>
                            <td><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',')) ?></td>
                            <td>R$ <?= number_format((float) $item['unit_price'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$items): ?><tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhum item.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>

        <div class="card"><div class="card-body">
            <div class="row g-2">
                <div class="col-6 col-md-3"><small class="text-body-secondary d-block">Peças</small><span class="fw-medium">R$ <?= number_format((float) $budget['parts_total'], 2, ',', '.') ?></span></div>
                <div class="col-6 col-md-3"><small class="text-body-secondary d-block">Mão de obra</small><span class="fw-medium">R$ <?= number_format((float) $budget['labor_total'], 2, ',', '.') ?></span></div>
                <div class="col-6 col-md-3"><small class="text-body-secondary d-block">Desconto</small><span class="fw-medium">R$ <?= number_format((float) $budget['discount'], 2, ',', '.') ?></span></div>
                <div class="col-6 col-md-3"><small class="text-body-secondary d-block">Total</small><span class="fw-bold text-primary fs-5">R$ <?= number_format((float) $budget['total'], 2, ',', '.') ?></span></div>
            </div>
        </div></div>

        <div class="card mt-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-history"></i><div><h3 class="panel-title">Histórico</h3></div></div>
            <?php if ($history): ?>
            <ul class="timeline mb-0">
                <?php foreach ($history as $entry): ?>
                <?php
                $data = is_string($entry['data'] ?? null)
                    ? (json_decode((string) $entry['data'], true) ?: [])
                    : [];
                $action = (string) ($entry['action'] ?? '');
                if ($action === 'status' && isset($data['from'], $data['to'])) {
                    $label = 'Status: ' . $Status::label((string) $data['from'])
                        . ' → ' . $Status::label((string) $data['to']);
                    $point = 'info';
                } elseif ($action === 'create') {
                    $label = 'Orçamento criado';
                    $point = 'primary';
                } elseif ($action === 'delete') {
                    $label = 'Orçamento excluído';
                    $point = 'danger';
                } else {
                    $label = 'Orçamento atualizado';
                    $point = 'warning';
                }
                ?>
                <li class="timeline-item timeline-item-transparent">
                    <span class="timeline-point timeline-point-<?= e($point) ?>"></span>
                    <div class="timeline-event">
                        <div class="timeline-header mb-1">
                            <h6 class="mb-0"><?= e($label) ?></h6>
                            <small class="text-body-secondary text-nowrap ms-2">
                                <?= !empty($entry['created_at']) ? e(date('d/m/Y H:i', strtotime((string) $entry['created_at']))) : '' ?>
                            </small>
                        </div>
                        <small class="text-body"><?= e((string) (($entry['user_name'] ?? '') ?: 'Sistema')) ?></small>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-body-secondary mb-0">Sem histórico registrado.</p>
            <?php endif; ?>
        </div></div>
    </div>
</div>
