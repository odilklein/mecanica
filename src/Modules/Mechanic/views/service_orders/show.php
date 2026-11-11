<?php
$path = $resource->path();
$Status = \Modules\Mechanic\Support\ServiceOrderStatus::class;
$PartStatus = \Modules\Mechanic\Support\PartOrderStatus::class;
$BudgetStatus = \Modules\Mechanic\Support\BudgetStatus::class;

$orderId = (int) $order['id'];
$status = (string) $order['status'];
$priority = (string) $order['priority'];
$taskStatuses = ['pending' => 'Pendente', 'in_progress' => 'Em execução', 'done' => 'Concluída'];
$money = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$qty = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return $parts
        ? strtoupper(implode('', array_map(static fn (string $p): string => mb_substr($p, 0, 1), array_slice($parts, 0, 2))))
        : '?';
};
$timeLabel = static function (?string $value): string {
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return '';
    }
    $date = date('Y-m-d', $ts);
    if ($date === date('Y-m-d')) {
        return date('H:i', $ts);
    }
    if ($date === date('Y-m-d', strtotime('-1 day'))) {
        return 'Ontem';
    }
    return date('d/m', $ts);
};

$clientInitials = $initials((string) ($order['client_name'] ?? ''));
$vehicleLabel = trim(($order['vehicle_brand'] ?? '') . ' ' . ($order['vehicle_model'] ?? ''));
$services = $serviceItems ?? [];
$partItems = $partItems ?? [];
$ticket = $ticket ?? null;
$ticketMessages = $ticketMessages ?? [];
$me = (int) ($user['id'] ?? 0);
$ticketStatus = (string) ($ticket['status'] ?? 'open');
$ticketId = (int) ($ticket['id'] ?? 0);
$eventCount = count($events);
$canManage = $canManage ?? false;
?>
<div class="page-header os-page-header">
    <div>
        <h1>
            <i class="icon-base ti tabler-clipboard-check"></i>
            OS #<?= str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) ?>
            <span class="badge <?= e($Status::badgeClass($status)) ?> ms-2"><?= e($Status::label($status)) ?></span>
        </h1>
        <span class="page-subtitle">
            <?= e($order['client_name'] ?: 'Cliente não informado') ?>
            <?= $order['vehicle_plate'] ? ' · ' . e($order['vehicle_plate']) : '' ?>
            <?= $vehicleLabel ? ' · ' . e($vehicleLabel) : '' ?>
            · Entrada <?= $order['entry_date'] ? e(date('d/m/Y', strtotime((string) $order['entry_date']))) : '—' ?>
        </span>
    </div>
    <div class="page-header-actions os-header-actions">
        <div class="os-priority-box">
            <small class="text-body-secondary d-block">Prioridade</small>
            <span class="badge <?= e($Status::priorityBadge($priority)) ?>"><?= e($Status::priorityLabel($priority)) ?></span>
        </div>
        <a href="<?= e($path) ?>" class="btn btn-label-secondary">
            <i class="icon-base ti tabler-arrow-left"></i> Voltar
        </a>
        <?php if ($ticket): ?>
        <a href="/tickets?ticket=<?= $ticketId ?>" class="btn btn-label-info">
            <i class="icon-base ti tabler-messages"></i> Central de tickets
        </a>
        <?php endif; ?>
        <?php if ($canManage): ?>
        <a href="/budgets/create?os_id=<?= $orderId ?>&cliente_id=<?= (int) $order['client_id'] ?>&veiculo_id=<?= (int) $order['vehicle_id'] ?>" class="btn btn-label-primary">
            <i class="icon-base ti tabler-file-plus"></i> Novo orçamento
        </a>
        <a href="<?= e($path . '/' . $orderId . '/edit') ?>" class="btn btn-primary">
            <i class="icon-base ti tabler-pencil"></i> Editar
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-6">
    <div class="col-12 col-lg-8">
        <!-- Serviços e peças necessárias -->
        <div class="card mb-6">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title m-0">Serviços e peças necessárias</h5>
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

            <div class="card-body">
                <?php if ($budget): ?>
                <?php if ($services): ?>
                <div class="os-subsection">
                    <div class="os-subsection-title">
                        <i class="icon-base ti tabler-tool"></i>
                        <span>Serviços</span>
                        <span class="badge bg-label-info"><?= count($services) ?></span>
                    </div>
                    <div class="os-service-list">
                        <?php foreach ($services as $item): ?>
                        <div class="os-service-row">
                            <span class="os-service-icon"><i class="icon-base ti tabler-tool"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-medium text-heading"><?= e((string) $item['description']) ?></div>
                                <small class="text-body-secondary">
                                    Mão de obra · <?= e($qty((float) $item['quantity'])) ?> × <?= e($money((float) $item['unit_price'])) ?>
                                </small>
                            </div>
                            <div class="text-end text-nowrap">
                                <div class="fw-semibold"><?= e($money((float) $item['subtotal'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($partItems): ?>
                <div class="os-subsection">
                    <div class="os-subsection-title">
                        <i class="icon-base ti tabler-package"></i>
                        <span>Peças necessárias</span>
                        <span class="badge bg-label-primary"><?= count($partItems) ?></span>
                    </div>
                    <div class="os-part-grid">
                        <?php foreach ($partItems as $item): ?>
                        <?php
                            $catalog = $item['catalog'] ?? null;
                            $name = (string) ($catalog['name'] ?? $item['description'] ?? 'Peça');
                            $brand = trim((string) ($catalog['brand'] ?? ''));
                            $code = trim((string) ($catalog['part_code'] ?? $catalog['sku'] ?? ''));
                            $category = trim((string) ($catalog['category'] ?? ''));
                            $description = implode(' · ', array_filter([$brand ?: null, $code ?: null, $category ?: null]));
                            $initialsPart = $initials($brand ?: $name);
                        ?>
                        <div class="os-part-card">
                            <div class="os-part-thumb">
                                <?php if ($catalog && !empty($catalog['image'])): ?>
                                <img src="<?= e((string) $catalog['image']) ?>" alt="<?= e($name) ?>" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                <span class="avatar-initial rounded-2 bg-label-primary" hidden><?= e($initialsPart) ?></span>
                                <?php else: ?>
                                <span class="avatar-initial rounded-2 bg-label-primary"><?= e($initialsPart) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="os-part-body">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <h6 class="mb-0 os-part-name" title="<?= e($name) ?>"><?= e($name) ?></h6>
                                    <span class="badge bg-label-primary flex-shrink-0">Peça</span>
                                </div>
                                <small class="text-body-secondary d-block os-part-desc" title="<?= e($description) ?>">
                                    <?= $description !== '' ? e($description) : 'Peça do orçamento' ?>
                                </small>
                                <div class="d-flex justify-content-between align-items-end gap-2 mt-2">
                                    <span class="small text-body-secondary">
                                        <?= e($qty((float) $item['quantity'])) ?> × <?= e($money((float) $item['unit_price'])) ?>
                                    </span>
                                    <strong class="text-heading"><?= e($money((float) $item['subtotal'])) ?></strong>
                                </div>
                                <?php if ($catalog): ?>
                                <a href="/parts/<?= (int) $catalog['id'] ?>" class="small d-inline-flex align-items-center gap-1 mt-2">
                                    <i class="icon-base ti tabler-external-link"></i> Ver no catálogo
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$services && !$partItems): ?>
                <p class="text-body-secondary mb-0">O orçamento ainda não possui itens.</p>
                <?php endif; ?>
                <?php else: ?>
                <div class="text-center text-body-secondary py-6">
                    <i class="icon-base ti tabler-file-off icon-40px d-block mb-2 opacity-50"></i>
                    Crie um orçamento para calcular peças, mão de obra e total.
                    <div class="mt-3">
                        <?php if ($canManage): ?>
                        <a href="/budgets/create?os_id=<?= $orderId ?>&cliente_id=<?= (int) $order['client_id'] ?>&veiculo_id=<?= (int) $order['vehicle_id'] ?>" class="btn btn-sm btn-primary">
                            <i class="icon-base ti tabler-plus"></i> Criar orçamento
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($budget): ?>
            <div class="d-flex justify-content-end align-items-center p-4 border-top">
                <div class="d-flex flex-column gap-2" style="min-width: 260px;">
                    <div class="d-flex justify-content-between">
                        <span class="w-px-100 text-heading">Peças:</span>
                        <h6 class="mb-0"><?= e($money((float) $budget['parts_total'])) ?></h6>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="w-px-100 text-heading">Mão de obra:</span>
                        <h6 class="mb-0"><?= e($money((float) $budget['labor_total'])) ?></h6>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="w-px-100 text-heading">Desconto:</span>
                        <h6 class="mb-0"><?= e($money((float) $budget['discount'])) ?></h6>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2">
                        <h6 class="w-px-100 mb-0">Total:</h6>
                        <h5 class="mb-0 text-primary"><?= e($money((float) $budget['total'])) ?></h5>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tarefas + conversa do ticket -->
        <div class="card mb-6 os-tasks-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title m-0">Tarefas</h5>
                    <small class="text-body-secondary">Modelo expandido: status, responsável, prazo e observações</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-label-secondary"><?= count($tasks) ?> tarefa(s)</span>
                    <?php if ($ticket): ?>
                    <a href="/tickets?ticket=<?= $ticketId ?>" class="btn btn-sm btn-label-info">
                        <i class="icon-base ti tabler-external-link"></i> Abrir na central
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body">
                <ul class="nav nav-tabs widget-nav-tabs pb-4 gap-2 gap-sm-4 mb-4 flex-wrap" role="tablist">
                    <li class="nav-item">
                        <a href="javascript:void(0);" class="nav-link btn active d-flex align-items-center gap-2" role="tab" data-bs-toggle="tab" data-bs-target="#osTabTasks" aria-selected="true">
                            <i class="icon-base ti tabler-checklist"></i> Tarefas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="javascript:void(0);" class="nav-link btn d-flex align-items-center gap-2" role="tab" data-bs-toggle="tab" data-bs-target="#osTabConversation" aria-selected="false">
                            <i class="icon-base ti tabler-messages"></i> Conversa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="javascript:void(0);" class="nav-link btn d-flex align-items-center gap-2" role="tab" data-bs-toggle="tab" data-bs-target="#osTabDetails" aria-selected="false">
                            <i class="icon-base ti tabler-info-circle"></i> Detalhes
                        </a>
                    </li>
                </ul>

                <div class="tab-content p-0">
                    <!-- ABA TAREFAS -->
                    <div class="tab-pane fade show active" id="osTabTasks" role="tabpanel">
                        <?php foreach ($tasks as $task): ?>
                        <?php
                        $taskTone = $task['status'] === 'done' ? 'success' : ($task['status'] === 'in_progress' ? 'primary' : 'secondary');
                        $taskAssigned = (int) ($task['assigned_to'] ?? 0);
                        $taskEditable = $canManage || $taskAssigned === 0 || $taskAssigned === $me;
                        ?>
                        <div class="os-task-card">
                            <?php if ($taskEditable): ?>
                            <form method="POST" action="<?= e($path . '/' . $orderId . '/task-update') ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                    <input type="text" class="form-control form-control-sm fw-semibold os-task-title" name="description"
                                           value="<?= e((string) $task['description']) ?>" maxlength="255" required>
                                    <span class="badge bg-label-<?= $taskTone ?> flex-shrink-0"><?= e($taskStatuses[$task['status']] ?? $task['status']) ?></span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label small mb-1">Status</label>
                                        <select class="form-select form-select-sm" name="status">
                                            <?php foreach ($taskStatuses as $key => $label): ?>
                                            <option value="<?= e($key) ?>" <?= $task['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label small mb-1">Responsável</label>
                                        <?php if ($canManage): ?>
                                        <select class="form-select form-select-sm" name="assigned_to">
                                            <option value="">Sem responsável</option>
                                            <?php foreach ($mechanics as $mechanic): ?>
                                            <option value="<?= (int) $mechanic['id'] ?>" <?= (int) ($task['assigned_to'] ?? 0) === (int) $mechanic['id'] ? 'selected' : '' ?>>
                                                <?= e($mechanic['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php else: ?>
                                        <div class="form-control form-control-sm bg-light"><?= e($task['assigned_name'] ?: 'Você') ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label small mb-1">Prazo</label>
                                        <input type="date" class="form-control form-control-sm" name="due_date"
                                               value="<?= e((string) ($task['due_date'] ?? '')) ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small mb-1">Observações</label>
                                        <textarea class="form-control form-control-sm" name="notes" rows="2"
                                                  placeholder="Detalhes da tarefa (opcional)"><?= e((string) ($task['notes'] ?? '')) ?></textarea>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                                    <small class="text-body-secondary">
                                        <?= e($task['creator_name'] ?: 'Sistema') ?>
                                        <?php if (!empty($task['created_at'])): ?> · <?= e(date('d/m', strtotime((string) $task['created_at']))) ?><?php endif; ?>
                                        <?php if (!empty($task['completed_at'])): ?>
                                        · concluída <?= e(date('d/m', strtotime((string) $task['completed_at']))) ?>
                                        <?php endif; ?>
                                    </small>
                                    <button type="submit" class="btn btn-sm btn-label-primary">
                                        <i class="icon-base ti tabler-device-floppy"></i> Salvar
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <h6 class="mb-0"><?= e((string) $task['description']) ?></h6>
                                <span class="badge bg-label-<?= $taskTone ?> flex-shrink-0"><?= e($taskStatuses[$task['status']] ?? $task['status']) ?></span>
                            </div>
                            <div class="d-flex flex-wrap gap-3 small text-body-secondary">
                                <span><i class="icon-base ti tabler-user icon-xs me-1"></i><?= e($task['assigned_name'] ?: 'Sem responsável') ?></span>
                                <?php if (!empty($task['due_date'])): ?>
                                <span><i class="icon-base ti tabler-calendar icon-xs me-1"></i>Prazo <?= e(date('d/m/Y', strtotime((string) $task['due_date']))) ?></span>
                                <?php endif; ?>
                                <span>Criada por <?= e($task['creator_name'] ?: 'Sistema') ?></span>
                            </div>
                            <?php if (!empty($task['notes'])): ?>
                            <p class="mt-2 mb-0 small"><?= nl2br(e((string) $task['notes'])) ?></p>
                            <?php endif; ?>
                            <div class="alert alert-secondary py-2 mt-2 mb-0 small">
                                <i class="icon-base ti tabler-lock icon-xs me-1"></i>
                                Somente o responsável ou a gestão pode editar esta tarefa.
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <?php if (!$tasks): ?>
                        <p class="text-body-secondary">Nenhuma tarefa definida.</p>
                        <?php endif; ?>

                        <form method="POST" action="<?= e($path . '/' . $orderId . '/tasks') ?>" class="os-task-add mt-4">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <h6 class="mb-3"><i class="icon-base ti tabler-plus"></i> Nova tarefa</h6>
                            <div class="row g-2">
                                <div class="col-12 <?= $canManage ? 'col-md-5' : 'col-md-7' ?>">
                                    <input type="text" class="form-control form-control-sm" name="description"
                                           placeholder="Descrição da tarefa..." maxlength="255" required>
                                </div>
                                <?php if ($canManage): ?>
                                <div class="col-6 col-md-3">
                                    <select class="form-select form-select-sm" name="assigned_to">
                                        <option value="">Sem responsável</option>
                                        <?php foreach ($mechanics as $mechanic): ?>
                                        <option value="<?= (int) $mechanic['id'] ?>"><?= e($mechanic['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-6 <?= $canManage ? 'col-md-2' : 'col-md-3' ?>">
                                    <input type="date" class="form-control form-control-sm" name="due_date">
                                </div>
                                <div class="col-12 col-md-2">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">
                                        <i class="icon-base ti tabler-plus"></i> Adicionar
                                    </button>
                                </div>
                                <div class="col-12">
                                    <textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="Observações (opcional)"></textarea>
                                    <?php if (!$canManage): ?>
                                    <div class="form-text">A tarefa será atribuída a você.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- ABA CONVERSA -->
                    <div class="tab-pane fade" id="osTabConversation" role="tabpanel">
                        <?php if ($ticket): ?>
                        <div class="os-ticket" id="osTicket"
                             data-ticket-id="<?= $ticketId ?>"
                             data-send-url="/tickets/<?= $ticketId ?>/messages"
                             data-status-url="/tickets/<?= $ticketId ?>/status"
                             data-csrf="<?= e($csrf) ?>"
                             data-current-user="<?= $me ?>">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge <?= $ticketStatus === 'closed' ? 'bg-label-secondary' : 'bg-label-success' ?>" id="osTicketStatusBadge">
                                        <?= $ticketStatus === 'closed' ? 'Encerrado' : 'Em aberto' ?>
                                    </span>
                                    <small class="text-body-secondary"><?= e($ticket['subject'] ?? '') ?></small>
                                </div>
                                <button type="button" class="btn btn-sm btn-label-secondary" id="osTicketToggleStatus"
                                        data-status="<?= e($ticketStatus) ?>">
                                    <i class="icon-base ti <?= $ticketStatus === 'closed' ? 'tabler-lock-open' : 'tabler-lock' ?>"></i>
                                    <?= $ticketStatus === 'closed' ? 'Reabrir' : 'Encerrar' ?>
                                </button>
                            </div>

                            <div class="os-ticket-messages" id="osTicketMessages">
                                <?php foreach ($ticketMessages as $message): ?>
                                <?php
                                    $isSystem = ($message['kind'] ?? 'message') === 'system';
                                    $mine = (int) $message['user_id'] === $me;
                                ?>
                                <?php if ($isSystem): ?>
                                <div class="ticket-system-card">
                                    <div class="d-flex align-items-center gap-2 mb-2 text-primary">
                                        <i class="icon-base ti tabler-clipboard-text"></i>
                                        <span class="fw-semibold">Resumo automático da OS</span>
                                    </div>
                                    <div class="ticket-system-content"><?= nl2br(e((string) $message['message'])) ?></div>
                                </div>
                                <?php else: ?>
                                <div class="os-ticket-message<?= $mine ? ' is-mine' : '' ?>">
                                    <?php if (!$mine): ?>
                                    <span class="avatar avatar-sm flex-shrink-0">
                                        <span class="avatar-initial rounded-circle bg-label-primary"><?= e($initials((string) $message['user_name'])) ?></span>
                                    </span>
                                    <?php endif; ?>
                                    <div class="os-ticket-bubble">
                                        <div class="os-ticket-text"><?= nl2br(e((string) $message['message'])) ?></div>
                                        <small class="text-body-secondary"><?= e($message['user_name']) ?> · <?= e($timeLabel($message['created_at'])) ?></small>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (!$ticketMessages): ?>
                                <p class="text-body-secondary text-center py-6 mb-0">Nenhuma mensagem ainda. Envie a primeira.</p>
                                <?php endif; ?>
                            </div>

                            <form class="os-ticket-composer" id="osTicketForm">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="text" class="form-control" id="osTicketInput" maxlength="2000"
                                       placeholder="Digite uma mensagem para a equipe..." autocomplete="off" required>
                                <?php if (!empty($ticketAudioEnabled)): ?>
                                <button type="button" class="btn btn-icon btn-text-secondary ticket-audio-btn"
                                        data-ticket-audio
                                        data-target="#osTicketInput"
                                        data-csrf="<?= e($csrf) ?>"
                                        data-endpoint="/api/transcribe"
                                        title="Gravar áudio e transcrever"
                                        aria-label="Gravar áudio e transcrever">
                                    <i class="icon-base ti tabler-microphone"></i>
                                </button>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary" id="osTicketSendBtn">
                                    <i class="icon-base ti tabler-send"></i>
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="icon-base ti tabler-info-circle"></i>
                            Você não participa do ticket desta OS.
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ABA DETALHES -->
                    <div class="tab-pane fade" id="osTabDetails" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="os-detail-block">
                                    <h6 class="mb-2"><i class="icon-base ti tabler-message-2 me-1"></i> Queixa do cliente</h6>
                                    <p class="mb-0 text-body"><?= $order['initial_complaint'] ? nl2br(e((string) $order['initial_complaint'])) : 'Não informada.' ?></p>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="os-detail-block">
                                    <h6 class="mb-2"><i class="icon-base ti tabler-notes me-1"></i> Observações internas</h6>
                                    <form method="POST" action="<?= e($path . '/' . $orderId . '/internal-notes') ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <textarea class="form-control" name="internal_notes" rows="3"
                                                  placeholder="Observações visíveis apenas para a equipe..."><?= e((string) ($order['internal_notes'] ?? '')) ?></textarea>
                                        <div class="d-flex justify-content-end mt-2">
                                            <button type="submit" class="btn btn-sm btn-label-primary">
                                                <i class="icon-base ti tabler-device-floppy"></i> Salvar observações
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="os-detail-block">
                                    <h6 class="mb-2"><i class="icon-base ti tabler-car me-1"></i> Veículo</h6>
                                    <div class="d-flex flex-wrap gap-3 text-body">
                                        <span><strong>Placa:</strong> <?= e($order['vehicle_plate'] ?: '—') ?></span>
                                        <span><strong>Modelo:</strong> <?= e($vehicleLabel ?: '—') ?></span>
                                        <span><strong>Ano:</strong> <?= $order['vehicle_year'] ? (int) $order['vehicle_year'] : '—' ?></span>
                                        <span><strong>Cor:</strong> <?= e($order['vehicle_color'] ?: '—') ?></span>
                                        <span><strong>KM:</strong> <?= $order['vehicle_km'] !== null ? number_format((int) $order['vehicle_km'], 0, ',', '.') : '—' ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                <?php if (!empty($order['vehicle_id']) && $canManage): ?>
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

        <!-- Status -->
        <?php if ($canManage && count($statusAvailable) > 1): ?>
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
                    <?php if ($status === 'ready_for_delivery'): ?>
                    <div class="alert alert-info py-2 mt-3 mb-0 small">
                        <i class="icon-base ti tabler-info-circle me-1"></i>
                        Registre a entrega/retirada para concluir a OS.
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>

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
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="icon-base ti tabler-device-floppy"></i> Salvar diagnóstico
                    </button>
                </form>
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
                <?php elseif ($canManage && $status === 'ready_for_delivery'): ?>
                <?php $currentKm = $order['vehicle_km'] !== null ? (int) $order['vehicle_km'] : 0; ?>
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
                        <input class="form-control" type="number" name="exit_km"
                               min="<?= $currentKm > 0 ? $currentKm : 0 ?>"
                               value="<?= $currentKm > 0 ? $currentKm : '' ?>"
                               placeholder="KM na saída">
                    </div>
                    <div class="form-text mb-3">O KM informado atualiza o veículo.</div>
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="icon-base ti tabler-device-floppy"></i> Registrar
                    </button>
                </form>
                <?php elseif ($canManage): ?>
                <div class="alert alert-info py-2 mb-0 small">
                    <i class="icon-base ti tabler-info-circle me-1"></i>
                    A entrega/retirada fica disponível quando a OS estiver <strong>Pronta para entrega</strong>.
                </div>
                <?php else: ?>
                <p class="text-body-secondary mb-0">Aguardando registro pela equipe.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Equipe / responsáveis -->
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">Equipe</h5>
            </div>
            <div class="card-body">
                <?php
                $responsibleIds = array_map(static fn (array $r): int => (int) $r['user_id'], $responsibles);
                $leadId = (int) ($order['lead_mechanic_id'] ?? 0);
                $showLeadRow = $leadId > 0
                    && !empty($order['lead_mechanic'])
                    && !in_array($leadId, $responsibleIds, true);
                ?>
                <?php if ($showLeadRow): ?>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="avatar avatar-sm">
                        <span class="avatar-initial rounded-circle bg-label-info">
                            <?= e(strtoupper(mb_substr((string) $order['lead_mechanic'], 0, 1))) ?>
                        </span>
                    </span>
                    <div class="flex-grow-1">
                        <h6 class="mb-0"><?= e((string) $order['lead_mechanic']) ?></h6>
                        <small class="text-body-secondary">Responsável pela OS</small>
                    </div>
                </div>
                <?php endif; ?>
                <?php foreach ($responsibles as $r): ?>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="avatar avatar-sm">
                        <span class="avatar-initial rounded-circle bg-label-primary">
                            <?= e(strtoupper(mb_substr((string) $r['name'], 0, 1))) ?>
                        </span>
                    </span>
                    <div class="flex-grow-1">
                        <h6 class="mb-0"><?= e($r['name']) ?></h6>
                        <small class="text-body-secondary"><?= e(roleLabel($r['role'])) ?></small>
                    </div>
                    <?php if ($canManage): ?>
                    <form method="POST" action="<?= e($path . '/' . $orderId . '/responsible/' . (int) $r['user_id'] . '/delete') ?>" onsubmit="return confirm('Remover responsável?')">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="Remover">
                            <i class="icon-base ti tabler-x"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <?php if (!$responsibles && !$showLeadRow): ?>
                <p class="text-body-secondary">Nenhum mecânico designado.</p>
                <?php endif; ?>

                <?php if ($canManage && $mechanics): ?>
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

        <!-- Pedidos de peças -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0">Pedidos de peças</h5>
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
