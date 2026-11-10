<?php
$BudgetStatus = \Modules\Mechanic\Support\BudgetStatus::class;

$money = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$shortMoney = static function (float $value): string {
    $abs = abs($value);
    if ($abs >= 1000000) {
        return 'R$ ' . number_format($value / 1000000, 1, ',', '.') . ' mi';
    }
    if ($abs >= 100000) {
        return 'R$ ' . number_format($value / 1000, 0, ',', '.') . ' mil';
    }
    if ($abs >= 1000) {
        return 'R$ ' . number_format($value / 1000, 1, ',', '.') . ' mil';
    }
    return 'R$ ' . number_format($value, 0, ',', '.');
};
$percent = static fn (float $value): string => ($value > 0 ? '+' : '') . number_format($value, 1, ',', '.') . '%';
$tone = static fn (float $value): string => $value < 0 ? 'danger' : 'success';

$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : '?';
};

$relativeTime = static function (?int $minutes): string {
    $minutes = (int) $minutes;
    if ($minutes < 1) {
        return 'agora';
    }
    if ($minutes < 60) {
        return 'há ' . $minutes . ' min';
    }
    $hours = (int) floor($minutes / 60);
    if ($hours < 24) {
        return 'há ' . $hours . ' h';
    }
    $days = (int) floor($hours / 24);
    return $days === 1 ? 'ontem' : 'há ' . $days . ' dias';
};

$progressTone = [
    'received' => 'secondary',
    'in_analysis' => 'info',
    'awaiting_approval' => 'warning',
    'awaiting_part' => 'warning',
    'in_progress' => 'primary',
    'ready_for_delivery' => 'success',
];

$eventMeta = [
    'created' => ['icon' => 'file-plus', 'point' => 'primary'],
    'status' => ['icon' => 'arrow-up-right', 'point' => 'info'],
    'responsible' => ['icon' => 'user-check', 'point' => 'warning'],
    'delivery' => ['icon' => 'truck-delivery', 'point' => 'success'],
];

$totalParts = array_sum($chartData['partsByStatus']['values']);
$weekTone = $tone((float) $kpis['week_delta']);
$ticketTone = $tone((float) $kpis['ticket_delta']);
$weekBadge = $kpis['previous_week'] > 0
    ? $percent((float) $kpis['week_delta'])
    : ($kpis['week_orders'] > 0 ? 'novos' : 'sem novos');
$weekBadgeTone = $kpis['previous_week'] > 0 ? $weekTone : 'info';
$canCreateOrder = $canCreateOrder ?? false;

$openSeries = $chartData['openOrdersByDay']['values'] ?? [];
$openFirst = $openSeries[0] ?? 0;
$openLast = $openSeries ? $openSeries[count($openSeries) - 1] : 0;
$openDelta = (int) $openLast - (int) $openFirst;
$openDeltaLabel = $openDelta === 0
    ? 'estável em 7 dias'
    : (($openDelta > 0 ? '+' : '') . $openDelta . ' em 7 dias');
$openDeltaTone = $openDelta > 0 ? 'warning' : ($openDelta < 0 ? 'success' : 'secondary');

$revenue6m = $canSeeFinancials ? array_sum($chartData['revenueByMonth']['values'] ?? []) : 0.0;
$ticketHasBase = (float) ($kpis['ticket_previous'] ?? 0) > 0;
$ticketBadge = $ticketHasBase
    ? $percent((float) $kpis['ticket_delta'])
    : ((float) $kpis['ticket_avg'] > 0 ? 'novo' : 'sem dados');
$ticketBadgeTone = $ticketHasBase ? $ticketTone : 'info';
?>

<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-layout-dashboard"></i>Painel</h1>
        <span class="page-subtitle"><?= $canCreateOrder ? 'Acompanhe o trabalho da oficina em tempo real' : 'Acompanhe as suas OS em tempo real' ?></span>
    </div>
    <div class="page-header-actions">
        <?php if ($canCreateOrder): ?>
        <a href="/service-orders/create" class="btn btn-primary"><i class="icon-base ti tabler-clipboard-plus"></i> Novo atendimento</a>
        <?php else: ?>
        <a href="/service-orders" class="btn btn-outline-primary"><i class="icon-base ti tabler-clipboard-list"></i> Ver minhas OS</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- OS abertas (barras dos últimos 7 dias) -->
    <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-3 col-6">
        <div class="card h-100">
            <div class="card-header pb-3">
                <h5 class="card-title mb-1"><?= $canCreateOrder ? 'OS abertas' : 'Minhas OS abertas' ?></h5>
                <p class="card-subtitle">Em andamento agora</p>
            </div>
            <div class="card-body">
                <small class="text-body-secondary d-block mb-1">Últimos 7 dias</small>
                <div id="ordersLastWeek"></div>
                <div class="d-flex justify-content-between align-items-center gap-3 dashboard-kpi-footer">
                    <h4 class="mb-0"><?= (int) $kpis['open_orders'] ?></h4>
                    <small class="text-<?= e($openDeltaTone) ?> text-nowrap" title="Variação de OS abertas nos últimos 7 dias"><?= e($openDeltaLabel) ?></small>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canSeeFinancials): ?>
    <!-- Faturamento aprovado (área dos últimos 6 meses) -->
    <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-3 col-6">
        <div class="card h-100">
            <div class="card-header pb-0">
                <h5 class="card-title mb-1">Faturamento</h5>
                <p class="card-subtitle">Aprovados nos últimos 6 meses</p>
            </div>
            <div id="revenueLastMonths"></div>
            <div class="card-body pt-0">
                <div class="d-flex flex-column align-items-start mt-3 gap-1">
                    <h4 class="mb-0" title="<?= e($money($revenue6m) . ' nos últimos 6 meses · histórico: ' . $money((float) $kpis['approved_revenue'])) ?>"><?= e($shortMoney($revenue6m)) ?></h4>
                    <small class="text-success" title="Taxa histórica de aprovação: aprovados ÷ decididos"><?= number_format((float) $kpis['approval_rate'], 0, ',', '.') ?>% de aprovação histórica</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Ticket médio -->
    <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="badge p-2 bg-label-danger mb-3 rounded"><i class="icon-base ti tabler-cash-banknote icon-28px"></i></div>
                <h5 class="card-title mb-1">Ticket médio</h5>
                <p class="card-subtitle">Média aprovada no mês</p>
                <p class="text-heading mb-3 mt-1" title="<?= e($money((float) $kpis['ticket_avg']) . ' este mês · média histórica: ' . $money((float) $kpis['ticket_all_time'])) ?>"><?= e($shortMoney((float) $kpis['ticket_avg'])) ?></p>
                <div>
                    <span class="badge bg-label-<?= e($ticketBadgeTone) ?>" title="Comparado à média do mês anterior"><?= e($ticketBadge) ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Tickets abertos (visão operacional, sem faturamento) -->
    <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="badge p-2 bg-label-info mb-3 rounded"><i class="icon-base ti tabler-messages icon-28px"></i></div>
                <h5 class="card-title mb-1">Tickets abertos</h5>
                <p class="card-subtitle">Central de atendimento</p>
                <p class="text-heading mb-3 mt-1"><?= (int) $kpis['open_tickets'] ?></p>
                <div>
                    <a href="/tickets" class="badge bg-label-info">Abrir tickets</a>
                </div>
            </div>
        </div>
    </div>

    <!-- OS prontas para entrega -->
    <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="badge p-2 bg-label-success mb-3 rounded"><i class="icon-base ti tabler-circle-check icon-28px"></i></div>
                <h5 class="card-title mb-1">Prontas</h5>
                <p class="card-subtitle">Aguardando entrega</p>
                <p class="text-heading mb-3 mt-1"><?= (int) $kpis['ready_orders'] ?></p>
                <div>
                    <a href="/service-orders" class="badge bg-label-success">Ver OS</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Peças pendentes -->
    <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="badge p-2 bg-label-warning mb-3 rounded"><i class="icon-base ti tabler-package icon-28px"></i></div>
                <h5 class="card-title mb-1"><?= $canCreateOrder ? 'Peças pendentes' : 'Minhas peças pendentes' ?></h5>
                <p class="card-subtitle">Pedidos em aberto</p>
                <p class="text-heading mb-3 mt-1"><?= (int) $kpis['pending_parts'] ?></p>
                <div>
                    <span class="badge bg-label-warning"><?= (int) $kpis['parts_on_the_way'] ?> a caminho</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Atendimentos da semana -->
    <div class="col-xxl-4 col-xl-4 col-lg-12 col-md-6 col-sm-8 col-12 mb-md-0 order-xxl-0 order-2">
        <div class="card pb-xxl-3">
            <div class="card-body row">
                <div class="d-flex flex-column col-5">
                    <div class="card-title mb-auto">
                        <h5 class="mb-2"><?= $canCreateOrder ? 'Atendimentos' : 'Meus atendimentos' ?></h5>
                        <p class="mb-0">7 dias</p>
                    </div>
                    <div class="mt-auto pt-3">
                        <h3 class="card-title mb-1"><?= (int) $kpis['week_orders'] ?></h3>
                        <span class="badge bg-label-<?= $weekBadgeTone ?>"><?= e($weekBadge) ?></span>
                    </div>
                </div>
                <div id="weekOrders" class="col-7"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Indicadores por aba -->
    <div class="col-xxl-8 col-12">
        <div class="card h-100 dashboard-indicators">
            <div class="card-header d-flex justify-content-between">
                <div class="card-title m-0">
                    <h5 class="mb-1">Indicadores</h5>
                    <p class="card-subtitle">Evolução dos últimos <?= count($chartData['indicatorTabs'][0]['labels']) ?> meses</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button" id="indicatorMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="indicatorMenu">
                        <a class="dropdown-item" href="/service-orders">Ver atendimentos</a>
                        <?php if ($canSeeFinancials): ?>
                        <a class="dropdown-item" href="/budgets">Ver orçamentos</a>
                        <a class="dropdown-item" href="/reports">Relatórios completos</a>
                        <?php else: ?>
                        <a class="dropdown-item" href="/part-orders">Ver pedidos de peças</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs widget-nav-tabs pb-4 gap-4 mx-1 d-flex flex-nowrap" role="tablist">
                    <?php foreach ($chartData['indicatorTabs'] as $tab): ?>
                    <li class="nav-item">
                        <a href="javascript:void(0);" class="nav-link btn d-flex flex-column align-items-center justify-content-center <?= $tab['active'] ? 'active' : '' ?>" role="tab" data-bs-toggle="tab" data-bs-target="#indicator-<?= e($tab['key']) ?>" aria-controls="indicator-<?= e($tab['key']) ?>" aria-selected="<?= $tab['active'] ? 'true' : 'false' ?>">
                            <div class="badge bg-label-secondary rounded p-2"><i class="icon-base ti tabler-<?= e($tab['icon']) ?> icon-md"></i></div>
                            <h6 class="tab-widget-title mb-0 mt-2"><?= e($tab['label']) ?></h6>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <li class="nav-item">
                        <a href="<?= $canSeeFinancials ? '/reports' : '/service-orders' ?>" class="nav-link btn d-flex align-items-center justify-content-center" title="<?= $canSeeFinancials ? 'Relatórios completos' : 'Ver todas as OS' ?>">
                            <div class="badge bg-label-secondary rounded p-2"><i class="icon-base ti tabler-plus icon-md"></i></div>
                        </a>
                    </li>
                </ul>
                <div class="tab-content p-0 ms-0 ms-sm-2">
                    <?php foreach ($chartData['indicatorTabs'] as $tab): ?>
                    <div class="tab-pane fade <?= $tab['active'] ? 'show active' : '' ?>" id="indicator-<?= e($tab['key']) ?>" role="tabpanel">
                        <div class="indicator-chart" data-indicator="<?= e($tab['key']) ?>"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pedidos de peças por status -->
    <div class="col-xl-4 col-md-6 order-xxl-0 order-1">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between pb-4">
                <div class="card-title mb-0">
                    <h5 class="mb-1"><?= $canCreateOrder ? 'Pedidos de peças' : 'Meus pedidos de peças' ?></h5>
                    <p class="card-subtitle"><?= (int) $totalParts ?> pedidos registrados</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button" id="partsMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="partsMenu">
                        <a class="dropdown-item" href="/part-orders">Ver pedidos</a>
                        <?php if ($canManageParts ?? false): ?>
                        <a class="dropdown-item" href="/parts">Catálogo de peças</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <div id="partsChart"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Clientes com mais movimento -->
    <div class="col-xxl-4 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <div class="card-title mb-0">
                    <h5 class="mb-1"><?= $canSeeFinancials ? 'Orçamentos por cliente' : 'Minhas OS por cliente' ?></h5>
                    <p class="card-subtitle"><?= $canSeeFinancials ? 'Volume aprovado por cliente' : 'Atendimentos registrados' ?></p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-text-secondary btn-icon rounded-pill text-body-secondary border-0 me-n1" type="button" id="clientsMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="icon-base ti tabler-dots-vertical icon-22px text-body-secondary"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="clientsMenu">
                        <a class="dropdown-item" href="/service-orders">Ver ordens de serviço</a>
                        <?php if ($canSeeFinancials): ?>
                        <a class="dropdown-item" href="/clients">Ver clientes</a>
                        <a class="dropdown-item" href="/budgets">Ver orçamentos</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <ul class="p-0 m-0">
                    <?php foreach ($topClients as $client): ?>
                    <li class="d-flex align-items-center mb-4">
                        <div class="avatar avatar-sm flex-shrink-0 me-3">
                            <span class="avatar-initial rounded-circle bg-label-primary"><?= e($initials((string) $client['name'])) ?></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0" title="<?= e((string) $client['name']) ?>"><?= $canSeeFinancials ? e($shortMoney((float) $client['total'])) : (int) $client['total'] . ' OS' ?></h6>
                                <small class="text-body dashboard-client-name" title="<?= e((string) $client['name']) ?>"><?= e((string) $client['name']) ?></small>
                            </div>
                            <div>
                                <p class="text-primary fw-medium mb-0 d-flex align-items-center gap-1" title="<?= $canSeeFinancials ? (int) $client['budgets'] . ' orçamento(s) aprovado(s)' : 'Participação nas OS' ?>">
                                    <i class="icon-base ti tabler-chart-pie"></i>
                                    <?= number_format((float) $client['share'], 1, ',', '.') ?>%
                                </p>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$topClients): ?>
                    <li class="text-center text-body-secondary py-4">Nenhum registro encontrado.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Status da oficina -->
    <div class="col-12 col-md-6 col-xxl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0 card-title"><?= $canCreateOrder ? 'Status da oficina' : 'Status das minhas OS' ?></h5>
                <div class="dropdown">
                    <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button" id="workshopMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="workshopMenu">
                        <a class="dropdown-item" href="/service-orders">Ver ordens de serviço</a>
                        <a class="dropdown-item" href="/part-orders">Ver pedidos de peças</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-start">
                    <div class="badge rounded bg-label-warning p-2 me-3"><i class="icon-base ti tabler-tool icon-lg"></i></div>
                    <div class="d-flex justify-content-between w-100 gap-2 align-items-center">
                        <div class="me-2">
                            <h6 class="mb-0"><?= (int) $kpis['open_orders'] ?> OS em andamento</h6>
                            <small class="text-body">Abertas neste momento</small>
                        </div>
                        <h6 class="mb-0 text-success text-nowrap"><?= (int) $kpis['ready_orders'] ?> prontas</h6>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 mb-1">
                    <small class="text-body-secondary">Novas OS por semana</small>
                    <small class="text-body-secondary">Últimas 8 semanas</small>
                </div>
                <div id="workshopArea"></div>
                <div class="d-flex justify-content-between mb-3">
                    <h6 class="mb-0">Aguardando aprovação</h6>
                    <p class="mb-0 fw-medium"><?= (int) $kpis['awaiting_approval'] ?></p>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <h6 class="mb-0">Aguardando peças</h6>
                    <p class="mb-0 fw-medium"><?= (int) $kpis['awaiting_part'] ?></p>
                </div>
                <div class="d-flex justify-content-between">
                    <h6 class="mb-0">Tickets abertos</h6>
                    <p class="mb-0 fw-medium"><a href="/tickets"><?= (int) $kpis['open_tickets'] ?></a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- OS em andamento com progresso -->
    <div class="col-xxl-4 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <div class="card-title mb-0">
                    <h5 class="mb-1"><?= $canCreateOrder ? 'OS em andamento' : 'Minhas OS em andamento' ?></h5>
                    <p class="card-subtitle">Andamento por etapa</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button" id="ordersMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="ordersMenu">
                        <a class="dropdown-item" href="/service-orders">Ver todas</a>
                        <?php if ($canCreateOrder): ?>
                        <a class="dropdown-item" href="/service-orders/create">Nova OS</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <ul class="p-0 m-0">
                    <?php foreach ($inProgress as $order): ?>
                    <li class="mb-4 d-flex align-items-center">
                        <div class="d-flex w-50 align-items-center me-3">
                            <div class="avatar avatar-sm flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-<?= e($progressTone[$order['status']] ?? 'secondary') ?>"><i class="icon-base ti tabler-clipboard-text"></i></span>
                            </div>
                            <div class="dashboard-os-name">
                                <h6 class="mb-0"><a href="/service-orders/<?= (int) $order['id'] ?>">#<?= str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) ?></a></h6>
                                <small class="text-body text-truncate d-block"><?= e($order['client'] ?: 'Sem cliente') ?></small>
                            </div>
                        </div>
                        <div class="d-flex flex-grow-1 align-items-center">
                            <div class="progress w-100 me-3" style="height:8px;">
                                <div class="progress-bar bg-<?= e($progressTone[$order['status']] ?? 'secondary') ?>" role="progressbar" style="width: <?= (int) $order['progress'] ?>%" aria-valuenow="<?= (int) $order['progress'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="text-body-secondary small"><?= (int) $order['progress'] ?>%</span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$inProgress): ?>
                    <li class="text-center text-body-secondary py-4">Nenhuma OS em andamento.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php if ($canSeeFinancials): ?>
    <!-- Últimos orçamentos -->
    <div class="col-md-6 col-12">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0 me-2">Últimos orçamentos</h5>
                <div class="dropdown">
                    <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button" id="budgetMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="budgetMenu">
                        <a class="dropdown-item" href="/budgets">Ver todos</a>
                        <a class="dropdown-item" href="/budgets/create">Novo orçamento</a>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless border-top mb-0">
                    <thead class="border-bottom">
                        <tr>
                            <th>ORÇAMENTO</th>
                            <th>EMISSÃO</th>
                            <th>STATUS</th>
                            <th class="text-end">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestBudgets as $budget): ?>
                        <tr>
                            <td>
                                <div class="d-flex flex-column">
                                    <a href="/budgets/<?= (int) $budget['id'] ?>" class="mb-0 text-heading fw-medium">#<?= str_pad((string) $budget['number'], 4, '0', STR_PAD_LEFT) ?></a>
                                    <small class="text-body text-truncate" style="max-width: 160px;"><?= e($budget['client'] ?: 'Sem cliente') ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <p class="mb-0 text-heading text-nowrap"><?= $budget['budget_date'] ? e(date('d/m/Y', strtotime((string) $budget['budget_date']))) : '—' ?></p>
                                    <small class="text-body">Emitido</small>
                                </div>
                            </td>
                            <td><span class="badge <?= e($BudgetStatus::badgeClass((string) $budget['status'])) ?>"><?= e($BudgetStatus::label((string) $budget['status'])) ?></span></td>
                            <td class="text-end"><span class="text-heading fw-medium text-nowrap"><?= e($money((float) $budget['total'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$latestBudgets): ?>
                        <tr><td colspan="4" class="text-center text-body-secondary py-5">Nenhum orçamento registrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Atividade recente -->
    <div class="<?= $canSeeFinancials ? 'col-xxl-6' : 'col-12' ?>">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title m-0 me-2 pt-1 mb-2 d-flex align-items-center"><i class="icon-base ti tabler-list-details me-2"></i> Atividade recente</h5>
                <div class="dropdown">
                    <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button" id="timelineMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="timelineMenu">
                        <a class="dropdown-item" href="/service-orders">Ver ordens de serviço</a>
                        <?php if (($user['role'] ?? '') === 'admin'): ?>
                        <a class="dropdown-item" href="/audit">Ver auditoria</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body pb-xxl-0">
                <ul class="timeline mb-0">
                    <?php foreach ($events as $event): ?>
                    <?php $meta = $eventMeta[$event['type']] ?? ['icon' => 'activity', 'point' => 'secondary']; ?>
                    <li class="timeline-item timeline-item-transparent">
                        <span class="timeline-point timeline-point-<?= e($meta['point']) ?>"></span>
                        <div class="timeline-event">
                            <div class="timeline-header mb-1">
                                <h6 class="mb-0"><?= e((string) $event['description']) ?></h6>
                                <small class="text-body-secondary text-nowrap ms-2"><?= e($relativeTime((int) $event['minutes_ago'])) ?></small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="icon-base ti tabler-<?= e($meta['icon']) ?> text-body-secondary"></i>
                                <?php if ($event['order_number'] !== null): ?>
                                <small class="text-body">
                                    <a href="/service-orders/<?= (int) $event['order_id'] ?>">OS #<?= str_pad((string) $event['order_number'], 4, '0', STR_PAD_LEFT) ?></a>
                                    <?= $event['user_name'] ? ' · ' . e((string) $event['user_name']) : '' ?>
                                </small>
                                <?php else: ?>
                                <small class="text-body"><?= e($event['user_name'] ?: 'Sistema') ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$events): ?>
                    <li class="text-center text-body-secondary py-5">Nenhuma atividade registrada.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
window.dashboardData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
