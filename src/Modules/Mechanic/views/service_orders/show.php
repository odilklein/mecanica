<?php
$path = $resource->path();
$Status = \Modules\Mechanic\Support\ServiceOrderStatus::class;
$PartStatus = \Modules\Mechanic\Support\PartOrderStatus::class;
$orderId = (int) $order['id'];
$taskStatuses = ['pending' => 'Pendente', 'in_progress' => 'Em execução', 'done' => 'Concluída'];
?>
<div class="page-header">
    <div>
        <h1>
            <i class="icon-base ti tabler-clipboard-check"></i>
            OS #<?= str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) ?>
            <span class="badge <?= e($Status::badgeClass($order['status'])) ?> ms-2"><?= e($Status::label($order['status'])) ?></span>
        </h1>
        <span class="page-subtitle">Detalhes do atendimento</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
        <a href="<?= e($path . '/' . $orderId . '/edit') ?>" class="btn btn-primary"><i class="icon-base ti tabler-pencil"></i> Editar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-car"></i><div><h3 class="panel-title">Atendimento</h3></div></div>
            <div class="row g-3">
                <div class="col-md-6"><small class="text-body-secondary d-block">Cliente</small><span class="fw-medium"><?= e($order['client_name'] ?: '—') ?></span><?php if ($order['client_phone']): ?><br><small><?= e($order['client_phone']) ?></small><?php endif; ?></div>
                <div class="col-md-6"><small class="text-body-secondary d-block">Veículo</small><span><?= e($order['vehicle_plate'] ?: '—') ?><?= $order['vehicle_model'] ? ' · ' . e($order['vehicle_brand'] . ' ' . $order['vehicle_model']) : '' ?></span></div>
                <div class="col-md-4"><small class="text-body-secondary d-block">Prioridade</small><span class="badge <?= e($Status::priorityBadge($order['priority'])) ?>"><?= e($Status::priorityLabel($order['priority'])) ?></span></div>
                <div class="col-md-4"><small class="text-body-secondary d-block">Entrada</small><span><?= $order['entry_date'] ? e(date('d/m/Y', strtotime($order['entry_date']))) : '—' ?></span></div>
                <div class="col-md-4"><small class="text-body-secondary d-block">Responsável</small><span><?= e($order['lead_mechanic'] ?: '—') ?></span></div>
                <?php if (!empty($order['initial_complaint'])): ?>
                <div class="col-12"><small class="text-body-secondary d-block">Queixa inicial</small><p class="mb-0"><?= nl2br(e($order['initial_complaint'])) ?></p></div>
                <?php endif; ?>
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-stethoscope"></i><div><h3 class="panel-title">Diagnóstico técnico</h3></div></div>
            <form method="POST" action="<?= e($path . '/' . $orderId . '/diagnosis') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label" for="diagnosis">Diagnóstico</label>
                    <textarea class="form-control" id="diagnosis" name="diagnosis" rows="4"><?= e($order['diagnosis'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="internal_notes">Observações internas</label>
                    <textarea class="form-control" id="internal_notes" name="internal_notes" rows="2"><?= e($order['internal_notes'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i> Salvar diagnóstico</button>
            </form>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-package"></i><div><h3 class="panel-title">Peças vinculadas</h3></div></div>
            <?php if ($parts): ?>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th>Itens</th><th>Fornecedor</th><th>Previsão</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($parts as $p): ?>
                <tr>
                    <td><?= e($p['items'] ?: 'Itens não informados') ?></td>
                    <td><?= e($p['supplier'] ?: '—') ?></td>
                    <td><?= $p['expected_date'] ? e(date('d/m/Y', strtotime($p['expected_date']))) : '—' ?></td>
                    <td><span class="badge <?= e($PartStatus::badgeClass($p['status'])) ?>"><?= e($PartStatus::label($p['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php else: ?>
            <p class="text-body-secondary mb-0">Nenhuma peça solicitada. <a href="/part-orders/create">Adicionar pedido</a></p>
            <?php endif; ?>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-list-check"></i><div><h3 class="panel-title">Tarefas</h3></div></div>
            <?php if ($tasks): ?>
            <?php foreach ($tasks as $task): ?>
            <form method="POST" action="<?= e($path . '/' . $orderId . '/task-status') ?>" class="d-flex align-items-center gap-2 mb-2">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                <i class="icon-base ti tabler-grip-vertical text-body-secondary"></i>
                <span class="flex-grow-1 <?= $task['status'] === 'done' ? 'text-decoration-line-through text-body-secondary' : '' ?>"><?= e($task['description']) ?></span>
                <select class="form-select form-select-sm w-auto" name="task_status" onchange="this.form.submit()">
                    <?php foreach ($taskStatuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $task['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="text-body-secondary">Nenhuma tarefa definida.</p>
            <?php endif; ?>
            <form method="POST" action="<?= e($path . '/' . $orderId . '/tasks') ?>" class="input-group mt-2">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="text" class="form-control" name="description" placeholder="Nova tarefa..." maxlength="255" required>
                <button type="submit" class="btn btn-outline-primary"><i class="icon-base ti tabler-plus"></i></button>
            </form>
        </div></div>

        <div class="card"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-history"></i><div><h3 class="panel-title">Histórico</h3></div></div>
            <?php if ($events): ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($events as $ev): ?>
                <li class="d-flex gap-2 mb-2">
                    <i class="icon-base ti tabler-point text-primary"></i>
                    <div><span><?= e($ev['description']) ?></span><br><small class="text-body-secondary"><?= e($ev['author'] ?: 'Sistema') ?> · <?= e(date('d/m/Y H:i', strtotime($ev['created_at']))) ?></small></div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <span class="text-body-secondary">Nenhum evento registrado.</span>
            <?php endif; ?>
        </div></div>
    </div>

    <div class="col-lg-4">
        <a href="/budgets/create" class="btn btn-primary w-100 mb-3"><i class="icon-base ti tabler-file-plus"></i> Novo orçamento</a>

        <div class="card mb-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-activity"></i><div><h3 class="panel-title">Atualizar status</h3></div></div>
            <form method="POST" action="<?= e($path . '/' . $orderId . '/status') ?>" class="d-flex gap-2">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <select class="form-select" name="status">
                    <?php foreach ($statusAvailable as $st): ?>
                    <option value="<?= e($st) ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= e($Status::label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </form>
        </div></div>

        <?php if ($mechanics): ?>
        <div class="card mb-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-user-plus"></i><div><h3 class="panel-title">Designar mecânico</h3></div></div>
            <form method="POST" action="<?= e($path . '/' . $orderId . '/responsible') ?>" class="d-flex gap-2">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <select class="form-select" name="user_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($mechanics as $m): ?>
                    <option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> (<?= e(roleLabel($m['role'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-primary"><i class="icon-base ti tabler-plus"></i></button>
            </form>
        </div></div>
        <?php endif; ?>

        <div class="card mb-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-users"></i><div><h3 class="panel-title">Responsáveis</h3></div></div>
            <?php if ($responsibles): ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($responsibles as $r): ?>
                <li class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar avatar-sm"><span class="avatar-initial rounded-circle bg-label-primary"><?= e(strtoupper(substr($r['name'], 0, 1))) ?></span></span>
                    <span class="flex-grow-1"><span class="d-block"><?= e($r['name']) ?></span><small class="text-body-secondary"><?= e(roleLabel($r['role'])) ?></small></span>
                    <form method="POST" action="<?= e($path . '/' . $orderId . '/responsible/' . (int) $r['user_id'] . '/delete') ?>" onsubmit="return confirm('Remover responsável?')">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-icon btn-text-danger"><i class="icon-base ti tabler-x"></i></button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-body-secondary mb-0">Nenhum mecânico designado.</p>
            <?php endif; ?>
        </div></div>

        <div class="card"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-truck"></i><div><h3 class="panel-title">Entrega ou retirada</h3></div></div>
            <?php if ($delivery): ?>
            <p class="mb-2"><strong><?= $delivery['modality'] === 'delivery' ? 'Entrega pela oficina' : 'Retirada pelo cliente' ?></strong></p>
            <small class="text-body-secondary d-block">Responsável: <?= e($delivery['responsible'] ?: '—') ?></small>
            <small class="text-body-secondary d-block">Destino: <?= e($delivery['destination'] ?: '—') ?></small>
            <small class="text-body-secondary d-block">KM saída: <?= $delivery['exit_km'] !== null ? (int) $delivery['exit_km'] : '—' ?></small>
            <?php else: ?>
            <form method="POST" action="<?= e($path . '/' . $orderId . '/delivery') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <select class="form-select mb-2" name="modality">
                    <option value="pickup">Retirada pelo cliente</option>
                    <option value="delivery">Entrega pela oficina</option>
                </select>
                <input class="form-control mb-2" name="responsible" maxlength="120" placeholder="Responsável / motorista">
                <input class="form-control mb-2" name="destination" maxlength="255" placeholder="Destino">
                <input class="form-control mb-2" type="number" name="exit_km" min="0" placeholder="KM na saída">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="icon-base ti tabler-device-floppy"></i> Registrar</button>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
</div>
