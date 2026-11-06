<?php
$path = $resource->path();
$Status = \Modules\Mechanic\Support\ServiceOrderStatus::class;
$orderId = (int) $order['id'];
?>
<div class="page-header">
    <div>
        <h1>
            <i class="icon-base ti tabler-clipboard-list"></i>
            OS #<?= str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) ?>
            <span class="badge <?= e($Status::badgeClass($order['status'])) ?> ms-2"><?= e($Status::label($order['status'])) ?></span>
        </h1>
        <span class="page-subtitle">Detalhes da ordem de serviço</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
        <a href="<?= e($path . '/' . $orderId . '/edit') ?>" class="btn btn-primary"><i class="icon-base ti tabler-pencil"></i> Editar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="panel-header">
                    <i class="icon-base ti tabler-info-circle"></i>
                    <div><h3 class="panel-title">Dados</h3></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><small class="text-body-secondary d-block">Cliente</small><span class="fw-medium"><?= e($order['client_name'] ?: '—') ?></span></div>
                    <div class="col-md-6"><small class="text-body-secondary d-block">Telefone</small><span><?= e($order['client_phone'] ?: '—') ?></span></div>
                    <div class="col-md-6"><small class="text-body-secondary d-block">Veículo</small><span><?= e($order['vehicle_plate'] ?: '—') ?><?= $order['vehicle_model'] ? ' · ' . e($order['vehicle_brand'] . ' ' . $order['vehicle_model']) : '' ?></span></div>
                    <div class="col-md-6"><small class="text-body-secondary d-block">Responsável</small><span><?= e($order['lead_mechanic'] ?: '—') ?></span></div>
                    <div class="col-md-4"><small class="text-body-secondary d-block">Prioridade</small><span class="badge <?= e($Status::priorityBadge($order['priority'])) ?>"><?= e($Status::priorityLabel($order['priority'])) ?></span></div>
                    <div class="col-md-4"><small class="text-body-secondary d-block">Entrada</small><span><?= $order['entry_date'] ? e(date('d/m/Y', strtotime($order['entry_date']))) : '—' ?></span></div>
                    <div class="col-md-4"><small class="text-body-secondary d-block">Conclusão</small><span><?= $order['completion_date'] ? e(date('d/m/Y', strtotime($order['completion_date']))) : '—' ?></span></div>
                    <?php if (!empty($order['initial_complaint'])): ?>
                    <div class="col-12"><small class="text-body-secondary d-block">Queixa inicial</small><p class="mb-0"><?= nl2br(e($order['initial_complaint'])) ?></p></div>
                    <?php endif; ?>
                    <?php if (!empty($order['diagnosis'])): ?>
                    <div class="col-12"><small class="text-body-secondary d-block">Diagnóstico</small><p class="mb-0"><?= nl2br(e($order['diagnosis'])) ?></p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <div class="panel-header">
                    <i class="icon-base ti tabler-activity"></i>
                    <div><h3 class="panel-title">Alterar status</h3></div>
                </div>
                <form method="POST" action="<?= e($path . '/' . $orderId . '/status') ?>" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $order['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="panel-header">
                    <i class="icon-base ti tabler-list-check"></i>
                    <div><h3 class="panel-title">Tarefas</h3></div>
                </div>

                <ul class="list-group mb-3">
                    <?php foreach ($tasks as $task): ?>
                    <li class="list-group-item d-flex align-items-center justify-content-between">
                        <span class="<?= $task['status'] === 'done' ? 'text-decoration-line-through text-body-secondary' : '' ?>"><?= e($task['description']) ?></span>
                        <form method="POST" action="/tasks/<?= (int) $task['id'] ?>/toggle">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <button class="btn btn-sm <?= $task['status'] === 'done' ? 'btn-success' : 'btn-outline-secondary' ?>" type="submit" title="Alternar">
                                <i class="icon-base ti tabler-check"></i>
                            </button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$tasks): ?>
                    <li class="list-group-item text-body-secondary">Nenhuma tarefa cadastrada.</li>
                    <?php endif; ?>
                </ul>

                <form method="POST" action="<?= e($path . '/' . $orderId . '/tasks') ?>" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="text" class="form-control" name="description" placeholder="Nova tarefa..." maxlength="255" required>
                    <button type="submit" class="btn btn-outline-primary"><i class="icon-base ti tabler-plus"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>
