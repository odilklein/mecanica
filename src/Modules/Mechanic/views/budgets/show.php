<?php
$path = $resource->path();
$Status = \Modules\Mechanic\Support\BudgetStatus::class;
$budgetId = (int) $budget['id'];
$typeLabels = ['part' => 'Peça', 'service' => 'Serviço'];
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
        <a href="<?= e($path . '/' . $budgetId . '/edit') ?>" class="btn btn-primary"><i class="icon-base ti tabler-pencil"></i> Editar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-info-circle"></i><div><h3 class="panel-title">Dados</h3></div></div>
            <div class="row g-3">
                <div class="col-12"><small class="text-body-secondary d-block">Cliente</small><span class="fw-medium"><?= e($budget['client_name'] ?: '—') ?></span></div>
                <div class="col-12"><small class="text-body-secondary d-block">Veículo</small><span><?= e($budget['vehicle_plate'] ?: '—') ?><?= $budget['vehicle_model'] ? ' · ' . e($budget['vehicle_model']) : '' ?></span></div>
                <div class="col-12"><small class="text-body-secondary d-block">OS vinculada</small><span><?= $budget['order_number'] ? 'OS #' . str_pad((string) $budget['order_number'], 4, '0', STR_PAD_LEFT) : '—' ?></span></div>
                <div class="col-6"><small class="text-body-secondary d-block">Data</small><span><?= $budget['budget_date'] ? e(date('d/m/Y', strtotime($budget['budget_date']))) : '—' ?></span></div>
                <div class="col-6"><small class="text-body-secondary d-block">Validade</small><span><?= $budget['valid_until'] ? e(date('d/m/Y', strtotime($budget['valid_until']))) : '—' ?></span></div>
                <?php if (!empty($budget['notes'])): ?>
                <div class="col-12"><small class="text-body-secondary d-block">Observações</small><p class="mb-0"><?= nl2br(e($budget['notes'])) ?></p></div>
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
                        <?php foreach (($statuses ?? []) as $statusKey => $statusLabel): ?>
                        <option value="<?= e($statusKey) ?>" <?= $budget['status'] === $statusKey ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="icon-base ti tabler-device-floppy"></i> Atualizar status
                </button>
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
    </div>
</div>
