<?php
// =============================================
// TICKETS — central de atendimento (estilo app-chat).
// Lista de conversas à esquerda, histórico à direita.
// =============================================

$me = (int) ($user['id'] ?? 0);

$initials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$parts) {
        return '?';
    }
    return strtoupper(implode('', array_map(
        static fn (string $part): string => mb_substr($part, 0, 1),
        array_slice($parts, 0, 2)
    )));
};

$colors = ['primary', 'info', 'success', 'warning', 'danger', 'secondary'];
$avatarColor = static function ($id) use ($colors): string {
    return $colors[abs((int) $id) % count($colors)];
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

$statusLabel = ['open' => 'Em aberto', 'closed' => 'Encerrado'];
$statusBadge = ['open' => 'bg-label-success', 'closed' => 'bg-label-secondary'];
$roleLabel = [
    'admin' => 'Administrador',
    'secretary' => 'Secretaria',
    'lead_mechanic' => 'Mecânico responsável',
    'mechanic' => 'Mecânico',
];
$currentStatus = (string) ($selected['status'] ?? '');
$counts = $counts ?? ['open' => 0, 'unread' => 0, 'read' => 0, 'closed' => 0];
$filters = [
    'open' => 'Abertos',
    'unread' => 'Não lidos',
    'read' => 'Lidos',
    'closed' => 'Encerrados',
];
$emptyLabel = match ($scope) {
    'unread' => 'Nenhum ticket não lido.',
    'read' => 'Nenhum ticket lido.',
    'closed' => 'Nenhum ticket encerrado.',
    default => 'Nenhum ticket aberto.',
};
?>
<div class="tickets-page">
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-messages"></i> Tickets</h1>
        <span class="page-subtitle">Converse com a equipe sobre cada atendimento</span>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ticketNewModal">
            <i class="icon-base ti tabler-plus"></i> Novo ticket
        </button>
    </div>
</div>

<div class="app-chat tickets-chat card overflow-hidden" id="ticketApp"
     data-list-url="/tickets/list"
     data-base-url="/tickets"
     data-selected="<?= (int) $selectedId ?>"
     data-current-user="<?= $me ?>"
     data-csrf="<?= e($csrf) ?>">
    <div class="row g-0">
        <!-- Lista de conversas -->
        <div class="col app-chat-contacts app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-contacts">
            <div class="sidebar-header border-bottom">
                <div class="d-flex align-items-center gap-2 px-4 py-3">
                    <div class="input-group input-group-merge flex-grow-1">
                        <span class="input-group-text"><i class="icon-base ti tabler-search icon-xs"></i></span>
                        <input type="text" class="form-control chat-search-input" id="ticketSearch"
                               placeholder="Buscar ticket..." aria-label="Buscar ticket"
                               value="<?= e($search) ?>">
                    </div>
                    <i class="icon-base ti tabler-x icon-lg cursor-pointer close-sidebar d-lg-none"
                       data-bs-toggle="sidebar" data-overlay data-target="#app-chat-contacts"></i>
                </div>
                <div class="px-4 pb-3">
                    <div class="d-flex gap-2">
                        <?php foreach (['open', 'unread', 'read'] as $filterScope): ?>
                        <button type="button"
                                class="btn btn-sm ticket-filter flex-fill <?= $scope === $filterScope ? 'btn-label-primary' : 'btn-label-secondary' ?>"
                                data-scope="<?= e($filterScope) ?>">
                            <?= e($filters[$filterScope]) ?>
                            <span class="ticket-filter-count <?= (int) ($counts[$filterScope] ?? 0) > 0 ? '' : 'd-none' ?>"
                                  data-count="<?= e($filterScope) ?>"><?= (int) ($counts[$filterScope] ?? 0) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button"
                            class="btn btn-sm ticket-filter w-100 mt-2 <?= $scope === 'closed' ? 'btn-label-primary' : 'btn-label-secondary' ?>"
                            data-scope="closed">
                        <i class="icon-base ti tabler-archive me-1"></i> Encerrados
                        <span class="ticket-filter-count <?= (int) ($counts['closed'] ?? 0) > 0 ? '' : 'd-none' ?>"
                              data-count="closed"><?= (int) ($counts['closed'] ?? 0) ?></span>
                    </button>
                </div>
            </div>
            <div class="sidebar-body">
                <ul class="list-unstyled chat-contact-list py-2 mb-0" id="ticketList">
                    <?php if (!$tickets): ?>
                    <li class="text-center text-body-secondary py-6 px-4" id="ticketListEmpty">
                        <?= e($emptyLabel) ?>
                    </li>
                    <?php endif; ?>
                    <?php foreach ($tickets as $ticket): ?>
                    <?php
                        $ticketId = (int) $ticket['id'];
                        $unread = (int) ($ticket['unread'] ?? 0);
                        $isSelected = $ticketId === (int) $selectedId;
                        $ticketHref = '/tickets?ticket=' . $ticketId
                            . ($scope !== 'open' ? '&scope=' . urlencode($scope) : '')
                            . ($search !== '' ? '&q=' . urlencode($search) : '');
                    ?>
                    <li class="chat-contact-list-item mb-1<?= $isSelected ? ' active' : '' ?><?= ($scope !== 'closed' && $unread > 0 && !$isSelected) ? ' is-unread' : '' ?>" data-ticket-id="<?= $ticketId ?>">
                        <a href="<?= e($ticketHref) ?>" class="d-flex align-items-center text-reset text-decoration-none p-0">
                            <div class="avatar avatar-sm flex-shrink-0">
                                <span class="avatar-initial rounded-circle bg-label-<?= e($avatarColor($ticketId)) ?>">
                                    <?= e($initials((string) ($ticket['creator_name'] ?? '?'))) ?>
                                </span>
                            </div>
                            <div class="chat-contact-info flex-grow-1 ms-3 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <h6 class="chat-contact-name text-truncate m-0 fw-semibold"><?= e($ticket['subject']) ?></h6>
                                    <small class="chat-contact-list-item-time"><?= e($timeLabel($ticket['last_message_at'] ?: $ticket['created_at'])) ?></small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <small class="chat-contact-status text-truncate">
                                        <?= e($ticket['last_message'] ?: 'Sem mensagens ainda') ?>
                                    </small>
                                    <?php if ($scope !== 'closed' && $unread > 0 && !$isSelected): ?>
                                    <span class="badge rounded-pill bg-primary unread-badge"><?= $unread ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-2 mt-1">
                                    <small class="chat-contact-assignee text-body-secondary text-truncate">
                                        <i class="icon-base ti tabler-user-check"></i>
                                        <?= e($ticket['assigned_name'] ?: 'Sem responsável') ?>
                                    </small>
                                </div>
                            </div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="px-4 pb-3">
                    <button type="button" class="btn btn-sm btn-label-secondary w-100 <?= !empty($hasMore) ? '' : 'd-none' ?>"
                            id="ticketLoadMore">
                        <i class="icon-base ti tabler-chevron-down me-1"></i> Carregar mais
                    </button>
                </div>
            </div>
        </div>

        <!-- Conversa -->
        <div class="col app-chat-history" id="app-chat-history">
            <div class="chat-history-wrapper h-100 d-flex flex-column">
                <div class="chat-history-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <div class="d-flex overflow-hidden align-items-center">
                            <i class="icon-base ti tabler-menu-2 icon-lg cursor-pointer d-lg-none d-block me-3"
                               data-bs-toggle="sidebar" data-overlay data-target="#app-chat-contacts"></i>
                            <?php if ($selected): ?>
                            <div class="avatar avatar-sm flex-shrink-0 avatar-online">
                                <span class="avatar-initial rounded-circle bg-label-<?= e($avatarColor($selected['id'])) ?>">
                                    <?= e($initials((string) ($selected['creator_name'] ?? '?'))) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <div class="chat-contact-info flex-grow-1 ms-4">
                                <h6 class="m-0" id="ticketSubject"><?= $selected ? e($selected['subject']) : 'Selecione um ticket' ?></h6>
                                <small class="text-body-secondary" id="ticketSubtitle">
                                    <?php if ($selected): ?>
                                        <?= e($selected['creator_name'] ?: 'Sistema') ?>
                                        <?php if (!empty($selected['assigned_name'])): ?>
                                            · com <?= e($selected['assigned_name']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($selected['order_number'])): ?>
                                            · OS #<?= str_pad((string) $selected['order_number'], 4, '0', STR_PAD_LEFT) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Escolha uma conversa na lista
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <?php if ($selected): ?>
                        <div class="d-flex align-items-center gap-2" id="ticketHeaderActions">
                            <span class="badge <?= e($statusBadge[$currentStatus] ?? 'bg-label-secondary') ?>" id="ticketStatusBadge">
                                <?= e($statusLabel[$currentStatus] ?? 'Em aberto') ?>
                            </span>
                            <?php if (!empty($selected['order_number'])): ?>
                            <a href="/service-orders/<?= (int) $selected['service_order_id'] ?>" class="btn btn-sm btn-label-primary d-none d-sm-inline-flex" title="Abrir OS">
                                <i class="icon-base ti tabler-external-link"></i>
                            </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-label-secondary" id="ticketAssignBtn"
                                    data-assigned-to="<?= (int) ($selected['assigned_to'] ?? 0) ?>"
                                    title="<?= e(!empty($selected['assigned_name']) ? 'Responsável: ' . $selected['assigned_name'] : 'Sem responsável') ?>"
                                    aria-label="Alterar responsável">
                                <i class="icon-base ti tabler-user-check"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-label-secondary" id="ticketToggleStatus"
                                    data-status="<?= e($currentStatus) ?>" title="<?= $currentStatus === 'closed' ? 'Reabrir ticket' : 'Encerrar ticket' ?>">
                                <i class="icon-base ti <?= $currentStatus === 'closed' ? 'tabler-lock-open' : 'tabler-lock' ?>"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ticket-context-wrap border-bottom px-4 py-2 <?= !empty($contextHtml) ? '' : 'd-none' ?>" id="ticketContextWrap">
                    <?= $contextHtml ?>
                </div>

                <div class="chat-history-body flex-grow-1" id="ticketHistoryBody">
                    <ul class="list-unstyled chat-history mb-0" id="ticketMessages">
                        <?php foreach ($messages as $message): ?>
                        <?php
                            $mine = (int) $message['user_id'] === $me;
                            $color = $avatarColor($message['user_id']);
                            $isSystem = ($message['kind'] ?? 'message') === 'system';
                        ?>
                        <?php if ($isSystem): ?>
                        <li class="chat-message ticket-system-message">
                            <div class="ticket-system-card">
                                <button type="button" class="btn btn-text-secondary p-0 d-flex align-items-center gap-2 w-100 text-start"
                                        data-bs-toggle="collapse" data-bs-target="#ticketSummary-<?= (int) $message['id'] ?>"
                                        aria-expanded="false" aria-controls="ticketSummary-<?= (int) $message['id'] ?>">
                                    <i class="icon-base ti tabler-clipboard-text text-primary"></i>
                                    <span class="fw-semibold text-primary">Resumo automático da OS</span>
                                    <i class="icon-base ti tabler-chevron-down ms-auto"></i>
                                </button>
                                <div class="collapse" id="ticketSummary-<?= (int) $message['id'] ?>">
                                    <div class="ticket-system-content mt-2"><?= nl2br(e((string) $message['message'])) ?></div>
                                </div>
                            </div>
                        </li>
                        <?php else: ?>
                        <li class="chat-message<?= $mine ? ' chat-message-right' : '' ?>">
                            <div class="d-flex overflow-hidden">
                                <?php if (!$mine): ?>
                                <div class="user-avatar flex-shrink-0 me-3">
                                    <div class="avatar avatar-sm">
                                        <span class="avatar-initial rounded-circle bg-label-<?= e($color) ?>">
                                            <?= e($initials((string) $message['user_name'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="chat-message-wrapper flex-grow-1">
                                    <div class="chat-message-text">
                                        <p class="mb-0"><?= nl2br(e($message['message'])) ?></p>
                                    </div>
                                    <div class="text-body-secondary mt-1<?= $mine ? ' text-end' : '' ?>">
                                        <small><?= e($message['user_name']) ?> · <?= e($timeLabel($message['created_at'])) ?></small>
                                    </div>
                                </div>
                                <?php if ($mine): ?>
                                <div class="user-avatar flex-shrink-0 ms-3">
                                    <div class="avatar avatar-sm">
                                        <span class="avatar-initial rounded-circle bg-label-<?= e($color) ?>">
                                            <?= e($initials((string) $message['user_name'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>

                    <div class="text-center text-body-secondary py-8 <?= $selected ? 'd-none' : '' ?>" id="ticketEmptyState">
                        <i class="icon-base ti tabler-messages icon-40px mb-2 d-block"></i>
                        Selecione um ticket para ver a conversa.
                    </div>
                    <div class="text-center text-body-secondary py-6 <?= ($selected && !$messages) ? '' : 'd-none' ?>" id="ticketNoMessages">
                        Nenhuma mensagem ainda. Envie a primeira.
                    </div>
                </div>

                <div class="alert alert-secondary mx-4 mb-3 <?= ($selected && $currentStatus === 'closed') ? '' : 'd-none' ?>" id="ticketClosedNotice">
                    <i class="icon-base ti tabler-lock me-1"></i>
                    Ticket encerrado. Ele fica na aba <strong>Encerrados</strong> — reabra para enviar novas mensagens.
                </div>

                <div class="chat-history-footer shadow-xs <?= ($selected && $currentStatus !== 'closed') ? '' : 'd-none' ?>" id="ticketFooter">
                    <form class="form-send-message d-flex justify-content-between align-items-center" id="ticketForm">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="text" class="form-control message-input border-0 me-3 shadow-none" id="ticketInput"
                               placeholder="Digite sua mensagem..." autocomplete="off" maxlength="2000">
                        <div class="message-actions d-flex align-items-center">
                            <?php if (!empty($ticketAudioEnabled)): ?>
                            <button type="button" class="btn btn-icon btn-text-secondary ticket-audio-btn me-2"
                                    data-ticket-audio
                                    data-target="#ticketInput"
                                    data-csrf="<?= e($csrf) ?>"
                                    data-endpoint="/api/transcribe"
                                    title="Gravar áudio e transcrever"
                                    aria-label="Gravar áudio e transcrever">
                                <i class="icon-base ti tabler-microphone"></i>
                            </button>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary d-flex send-msg-btn" id="ticketSendBtn">
                                <span class="align-middle d-md-inline-block d-none">Enviar</span>
                                <i class="icon-base ti tabler-send icon-16px ms-md-2 ms-0"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: novo ticket -->
<div class="modal fade" id="ticketNewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="ticketNewForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Novo ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label" for="ticketSubjectInput">Assunto *</label>
                        <div class="input-group input-group-merge">
                            <span class="input-group-text"><i class="icon-base ti tabler-message"></i></span>
                            <input type="text" class="form-control" id="ticketSubjectInput" name="subject" maxlength="200"
                                   placeholder="Ex.: Dúvida sobre a OS #0013" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="ticketOrderInput">Ordem de serviço (opcional)</label>
                        <select class="select2 form-select" id="ticketOrderInput" name="service_order_id" data-placeholder="Vincular a uma OS">
                            <option value=""></option>
                            <?php foreach ($orders as $order): ?>
                            <option value="<?= (int) $order['id'] ?>">
                                OS #<?= str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) ?>
                                <?= $order['client_name'] ? ' · ' . e($order['client_name']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="ticketAssignedInput">Responsável (opcional)</label>
                        <select class="select2 form-select" id="ticketAssignedInput" name="assigned_to" data-placeholder="Selecionar usuário">
                            <option value=""></option>
                            <?php foreach ($users as $member): ?>
                            <option value="<?= (int) $member['id'] ?>">
                                <?= e($member['name']) ?> · <?= e($roleLabel[$member['role']] ?? $member['role']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="ticketMessageInput">Primeira mensagem (opcional)</label>
                        <textarea class="form-control" id="ticketMessageInput" name="message" rows="3"
                                  placeholder="Descreva o assunto..."></textarea>
                    </div>
                    <div class="alert alert-danger mt-4 mb-0 d-none" id="ticketNewError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="ticketNewSubmit">
                        <i class="icon-base ti tabler-plus"></i> Criar ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: alterar responsável -->
<div class="modal fade" id="ticketAssignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="ticketAssignForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Alterar responsável</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label" for="ticketAssignSelect">Responsável</label>
                        <select class="form-select" id="ticketAssignSelect" name="assigned_to">
                            <option value="">Sem responsável</option>
                            <?php foreach ($users as $member): ?>
                            <option value="<?= (int) $member['id'] ?>">
                                <?= e($member['name']) ?> · <?= e($roleLabel[$member['role']] ?? $member['role']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-danger mt-4 mb-0 d-none" id="ticketAssignError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="ticketAssignSubmit">
                        <i class="icon-base ti tabler-user-check"></i> Salvar responsável
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: encerrar ticket -->
<div class="modal fade" id="ticketCloseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Encerrar ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-3">
                    <div class="badge rounded bg-label-warning p-2 flex-shrink-0">
                        <i class="icon-base ti tabler-lock icon-lg"></i>
                    </div>
                    <div>
                        <p class="mb-2">Encerrar o ticket <strong id="ticketCloseSubject"></strong>?</p>
                        <p class="text-body-secondary mb-0">Depois será necessário reabri-lo para enviar novas mensagens.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="ticketCloseConfirm">
                    <i class="icon-base ti tabler-lock"></i> Encerrar ticket
                </button>
            </div>
        </div>
    </div>
</div>
</div>
