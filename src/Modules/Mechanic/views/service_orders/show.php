<?php
$path = $resource->path();
$Status = \Modules\Mechanic\Support\ServiceOrderStatus::class;
$PartStatus = \Modules\Mechanic\Support\PartOrderStatus::class;
$BudgetStatus = \Modules\Mechanic\Support\BudgetStatus::class;

$orderId = (int) $order['id'];
$status = (string) $order['status'];
$priority = (string) $order['priority'];
$taskStatuses = ['pending' => 'Pendente', 'in_progress' => 'Em execução', 'done' => 'Concluída'];
$itemTypeLabels = ['part' => 'Peça', 'service' => 'Serviço'];

$clientInitials = '';
if (preg_match_all('/\b\w/u', (string) ($order['client_name'] ?? ''), $m)) {
    $clientInitials = strtoupper(implode('', array_slice($m[0], 0, 2)));
}
$clientInitials = $clientInitials ?: '?';

$vehicleLabel = trim(($order['vehicle_brand'] ?? '') . ' ' . ($order['vehicle_model'] ?? ''));
$budgetItems = $budgetItems ?? [];
$eventCount = count($events);
?>
<div class="page-header">
    <div>
        <h1>
            <i class="icon-base ti tabler-clipboard-check"></i>
            OS #<?= str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) ?>
            <span class="badge <?= e($Status::badgeClass($status)) ?> ms-2"><?= e($Status::label($status)) ?></span>
            <span class="badge <?= e($Status::priorityBadge($priority)) ?> ms-1"><?= e($Status::priorityLabel($priority)) ?></span>
        </h1>
        <span class="page-subtitle">
            <?= e($order['client_name'] ?: 'Cliente não informado') ?>
            <?= $order['vehicle_plate'] ? ' · ' . e($order['vehicle_plate']) : '' ?>
            <?= $vehicleLabel ? ' · ' . e($vehicleLabel) : '' ?>
            · Entrada <?= $order['entry_date'] ? e(date('d/m/Y', strtotime((string) $order['entry_date']))) : '—' ?>
        </span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>" class="btn btn-label-secondary">
            <i class="icon-base ti tabler-arrow-left"></i> Voltar
        </a>
        <a href="/budgets/create?os_id=<?= $orderId ?>&cliente_id=<?= (int) $order['client_id'] ?>&veiculo_id=<?= (int) $order['vehicle_id'] ?>" class="btn btn-label-primary">
            <i class="icon-base ti tabler-file-plus"></i> Novo orçamento
        </a>
        <a href="<?= e($path . '/' . $orderId . '/edit') ?>" class="btn btn-primary">
            <i class="icon-base ti tabler-pencil"></i> Editar
        </a>
    </div>
</div>

<div class="row g-6">
    <div class="col-12 col-lg-8">
        <!-- Queixa inicial -->
        <div class="card mb-6">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <span class="avatar rounded-circle bg-label-warning">
                        <i class="icon-base ti tabler-message-2 icon-lg"></i>
                    </span>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Queixa inicial</h6>
                        <p class="mb-0 text-body"><?= $order['initial_complaint'] ? nl2br(e($order['initial_complaint'])) : 'Não informada.' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Itens e serviços (orçamento) -->
        <div class="card mb-6">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title m-0">Itens e serviços</h5>
                    <?php if ($budget): ?>
                    <small class="text-body-secondary">
                        Orçamento #<?= str_pad((string) $budget['number'], 4, '0', STR_PAD_LEFT) ?>
                        · <?= e($BudgetStatus::label($budget['status'])) ?>
                    </small>
                    <?php else: ?>
                    <small class="text-body-secondary">Nenhum orçamento vinculado a esta OS.</small>
                    <?php endif; ?>
                </div>
                <?php if ($budget): ?>
                <a href="/budgets/<?= (int) $budget['id'] ?>" class="btn btn-sm btn-label-primary">
                    <i class="icon-base ti tabler-file-text"></i> Ver orçamento
                </a>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0 responsive-card">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Item</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Unitário</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($budgetItems as $item): ?>
                        <tr>
                            <td data-label="Tipo">
                                <span class="badge <?= $item['type'] === 'service' ? 'bg-label-info' : 'bg-label-primary' ?>">
                                    <?= e($itemTypeLabels[$item['type']] ?? $item['type']) ?>
                                </span>
                            </td>
                            <td data-label="Item"><?= e($item['description']) ?></td>
                            <td data-label="Qtd" class="text-center"><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',')) ?></td>
                            <td data-label="Unitário" class="text-end">R$ <?= number_format((float) $item['unit_price'], 2, ',', '.') ?></td>
                            <td data-label="Subtotal" class="text-end">R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$budgetItems): ?>
                        <tr class="row-empty">
                            <td colspan="5" class="text-center text-body-secondary py-6">
                                <?php if ($budget): ?>
                                O orçamento ainda não possui itens.
                                <?php else: ?>
                                Crie um orçamento para calcular peças, mão de obra e total.
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="/budgets/create?os_id=<?= $orderId ?>&cliente_id=<?= (int) $order['client_id'] ?>&veiculo_id=<?= (int) $order['vehicle_id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="icon-base ti tabler-plus"></i> Criar orçamento
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($budget): ?>
            <div class="d-flex justify-content-end align-items-center p-4 border-top">
                <div class="d-flex flex-column gap-2" style="min-width: 260px;">
                    <div class="d-flex justify-content-between">
                        <span class="w-px-100 text-heading">Peças:</span>
                        <h6 class="mb-0">R$ <?= number_format((float) $budget['parts_total'], 2, ',', '.') ?></h6>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="w-px-100 text-heading">Mão de obra:</span>
                        <h6 class="mb-0">R$ <?= number_format((float) $budget['labor_total'], 2, ',', '.') ?></h6>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="w-px-100 text-heading">Desconto:</span>
                        <h6 class="mb-0">R$ <?= number_format((float) $budget['discount'], 2, ',', '.') ?></h6>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2">
                        <h6 class="w-px-100 mb-0">Total:</h6>
                        <h5 class="mb-0 text-primary">R$ <?= number_format((float) $budget['total'], 2, ',', '.') ?></h5>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tarefas -->
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">Tarefas</h5>
            </div>
            <div class="card-body">
                <?php foreach ($tasks as $task): ?>
                <form method="POST" action="<?= e($path . '/' . $orderId . '/task-status') ?>" class="d-flex align-items-center gap-2 mb-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                    <i class="icon-base ti tabler-grip-vertical text-body-secondary"></i>
                    <span class="flex-grow-1 <?= $task['status'] === 'done' ? 'text-decoration-line-through text-body-secondary' : '' ?>">
                        <?= e($task['description']) ?>
                    </span>
                    <select class="select2 form-select form-select-sm" name="task_status" data-width="150px" data-minimum-results-for-search="Infinity" onchange="this.form.submit()">
                        <?php foreach ($taskStatuses as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $task['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endforeach; ?>

                <?php if (!$tasks): ?>
                <p class="text-body-secondary">Nenhuma tarefa definida.</p>
                <?php endif; ?>

                <form method="POST" action="<?= e($path . '/' . $orderId . '/tasks') ?>" class="input-group mt-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="text" class="form-control" name="description" placeholder="Nova tarefa..." maxlength="255" required>
                    <button type="submit" class="btn btn-outline-primary"><i class="icon-base ti tabler-plus"></i></button>
                </form>
            </div>
        </div>

        <!-- Histórico / timeline -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title m-0">Histórico da OS</h5>
            </div>
            <div class="card-body pt-1">
                <?php if ($events): ?>
                <ul class="timeline pb-0 mb-0">
                    <?php foreach ($events as $i => $ev): ?>
                    <?php $last = $i === $eventCount - 1; ?>
                    <li class="timeline-item timeline-item-transparent <?= $last ? 'border-transparent' : 'border-primary' ?>">
                        <span class="timeline-point timeline-point-<?= $last ? 'secondary' : 'primary' ?>"></span>
                        <div class="timeline-event <?= $last ? 'pb-0' : '' ?>">
                            <div class="timeline-header">
                                <h6 class="mb-0"><?= e($ev['description']) ?></h6>
                                <small class="text-body-secondary"><?= e(date('d/m/Y H:i', strtotime((string) $ev['created_at']))) ?></small>
                            </div>
                            <p class="mt-3 <?= $last ? 'mb-0' : 'mb-3' ?>">
                                <i class="icon-base ti tabler-user icon-xs me-1"></i>
                                <?= e($ev['author'] ?: 'Sistema') ?>
                            </p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-body-secondary mb-0">Nenhum evento registrado.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <!-- Cliente -->
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">Cliente</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-6">
                    <div class="avatar me-3">
                        <span class="avatar-initial rounded-circle bg-label-primary"><?= e($clientInitials) ?></span>
                    </div>
                    <div class="d-flex flex-column">
                        <a href="/clients/<?= (int) $order['client_id'] ?>" class="text-body">
                            <h6 class="mb-0"><?= e($order['client_name'] ?: '—') ?></h6>
                        </a>
                        <span class="text-body-secondary">
                            <?= $order['client_since'] ? 'Cliente desde ' . e(date('m/Y', strtotime((string) $order['client_since']))) : 'Cliente' ?>
                        </span>
                    </div>
                </div>

                <div class="d-flex align-items-center mb-6">
                    <span class="avatar rounded-circle bg-label-info me-3 d-flex align-items-center justify-content-center">
                        <i class="icon-base ti tabler-clipboard-list icon-lg"></i>
                    </span>
                    <h6 class="text-nowrap mb-0"><?= (int) $clientOrdersCount ?> <?= $clientOrdersCount === 1 ? 'ordem de serviço' : 'ordens de serviço' ?></h6>
                </div>

                <h6 class="mb-1">Contato</h6>
                <p class="mb-1">E-mail: <?= e($order['client_email'] ?: '—') ?></p>
                <p class="mb-1">Telefone: <?= e($order['client_phone'] ?: '—') ?></p>
                <?php if (!empty($order['client_cpf'])): ?>
                <p class="mb-0">CPF: <?= e($order['client_cpf']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Veículo -->
        <div class="card mb-6">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title m-0">Veículo</h5>
                <?php if (!empty($order['vehicle_id'])): ?>
                <h6 class="m-0"><a href="/vehicles/<?= (int) $order['vehicle_id'] ?>/edit">Editar</a></h6>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <h6 class="mb-1"><?= e($order['vehicle_plate'] ?: '—') ?></h6>
                <p class="mb-1"><?= e($vehicleLabel ?: 'Modelo não informado') ?></p>
                <p class="mb-1">Ano: <?= $order['vehicle_year'] ? (int) $order['vehicle_year'] : '—' ?> · Cor: <?= e($order['vehicle_color'] ?: '—') ?></p>
                <p class="mb-1">KM: <?= $order['vehicle_km'] !== null ? number_format((int) $order['vehicle_km'], 0, ',', '.') : '—' ?></p>
                <p class="mb-0">FIPE: <?= $order['vehicle_fipe_value'] !== null ? 'R$ ' . number_format((float) $order['vehicle_fipe_value'], 2, ',', '.') : '—' ?></p>
            </div>
        </div>

        <!-- Entrega / retirada -->
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">Entrega / retirada</h5>
            </div>
            <div class="card-body">
                <?php if ($delivery): ?>
                <p class="mb-2"><strong><?= $delivery['modality'] === 'delivery' ? 'Entrega pela oficina' : 'Retirada pelo cliente' ?></strong></p>
                <p class="mb-1">Responsável: <?= e($delivery['responsible'] ?: '—') ?></p>
                <p class="mb-1">Destino: <?= e($delivery['destination'] ?: '—') ?></p>
                <p class="mb-0">KM na saída: <?= $delivery['exit_km'] !== null ? (int) $delivery['exit_km'] : '—' ?></p>
                <?php else: ?>
                <form method="POST" action="<?= e($path . '/' . $orderId . '/delivery') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="deliveryModality">Modalidade</label>
                        <select class="select2 form-select" id="deliveryModality" name="modality" data-placeholder="Selecione">
                            <option value="pickup">Retirada pelo cliente</option>
                            <option value="delivery">Entrega pela oficina</option>
                        </select>
                    </div>
                    <div class="input-group input-group-merge mb-3">
                        <span class="input-group-text"><i class="icon-base ti tabler-user"></i></span>
                        <input class="form-control" name="responsible" maxlength="120" placeholder="Responsável / motorista">
                    </div>
                    <div class="input-group input-group-merge mb-3">
                        <span class="input-group-text"><i class="icon-base ti tabler-map-pin"></i></span>
                        <input class="form-control" name="destination" maxlength="255" placeholder="Destino">
                    </div>
                    <div class="input-group input-group-merge mb-3">
                        <span class="input-group-text"><i class="icon-base ti tabler-road"></i></span>
                        <input class="form-control" type="number" name="exit_km" min="0" placeholder="KM na saída">
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="icon-base ti tabler-device-floppy"></i> Registrar
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Equipe / responsáveis -->
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">Equipe</h5>
            </div>
            <div class="card-body">
                <?php foreach ($responsibles as $r): ?>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="avatar avatar-sm">
                        <span class="avatar-initial rounded-circle bg-label-primary">
                            <?= e(strtoupper(substr((string) $r['name'], 0, 1))) ?>
                        </span>
                    </span>
                    <div class="flex-grow-1">
                        <h6 class="mb-0"><?= e($r['name']) ?></h6>
                        <small class="text-body-secondary"><?= e(roleLabel($r['role'])) ?></small>
                    </div>
                    <form method="POST" action="<?= e($path . '/' . $orderId . '/responsible/' . (int) $r['user_id'] . '/delete') ?>" onsubmit="return confirm('Remover responsável?')">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="Remover">
                            <i class="icon-base ti tabler-x"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>

                <?php if (!$responsibles): ?>
                <p class="text-body-secondary">Nenhum mecânico designado.</p>
                <?php endif; ?>

                <?php if ($mechanics): ?>
                <form method="POST" action="<?= e($path . '/' . $orderId . '/responsible') ?>" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <div class="mb-3">
                        <select class="select2 form-select" name="user_id" data-placeholder="Designar mecânico" required>
                            <option value=""></option>
                            <?php foreach ($mechanics as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> — <?= e(roleLabel($m['role'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="icon-base ti tabler-user-plus"></i> Designar
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status -->
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">Status</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= e($path . '/' . $orderId . '/status') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <div class="mb-3">
                        <select class="select2 form-select" name="status" data-placeholder="Selecione o status">
                            <?php foreach ($statusAvailable as $st): ?>
                            <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($Status::label($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="icon-base ti tabler-activity"></i> Atualizar status
                    </button>
                </form>
            </div>
        </div>

        <!-- Diagnóstico -->
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">Diagnóstico</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= e($path . '/' . $orderId . '/diagnosis') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="diagnosis">Diagnóstico técnico</label>
                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="4"><?= e($order['diagnosis'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="internal_notes">Observações internas</label>
                        <textarea class="form-control" id="internal_notes" name="internal_notes" rows="2"><?= e($order['internal_notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="icon-base ti tabler-device-floppy"></i> Salvar diagnóstico
                    </button>
                </form>
            </div>
        </div>

        <!-- Peças -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0">Peças</h5>
                <a href="/part-orders/create?os_id=<?= $orderId ?>" class="btn btn-sm btn-label-primary">
                    <i class="icon-base ti tabler-plus"></i> Novo pedido
                </a>
            </div>
            <div class="card-body">
                <?php if ($parts): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 responsive-card">
                        <thead>
                            <tr>
                                <th>Itens</th>
                                <th>Fornecedor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parts as $p): ?>
                            <tr>
                                <td data-label="Itens"><?= e($p['items'] ?: 'Itens não informados') ?></td>
                                <td data-label="Fornecedor"><?= e($p['supplier'] ?: '—') ?></td>
                                <td data-label="Status"><span class="badge <?= e($PartStatus::badgeClass($p['status'])) ?>"><?= e($PartStatus::label($p['status'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-body-secondary mb-0">Nenhuma peça solicitada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
