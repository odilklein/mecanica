<?php
$order = $order ?? [];
$items = $items ?? [];
$history = $history ?? [];
$canManageStatus = $canManageStatus ?? false;
$Status = \Modules\Mechanic\Support\PartOrderStatus::class;
$status = (string) ($order['status'] ?? 'requested');

$money = static fn (mixed $value): string => $value !== null && $value !== ''
    ? 'R$ ' . number_format((float) $value, 2, ',', '.')
    : '—';
$qty = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
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

$total = 0.0;
$pricedItems = 0;
foreach ($items as $item) {
    if ($item['unit_price'] !== null) {
        $pricedItems++;
        $total += (float) $item['unit_price'] * (float) $item['quantity'];
    }
}
$hasPrices = $pricedItems > 0;
$itemCount = count($items);
$itemCountLabel = $itemCount . ' ' . ($itemCount === 1 ? 'item' : 'itens');

$orderNumber = !empty($order['order_number'])
    ? str_pad((string) $order['order_number'], 4, '0', STR_PAD_LEFT)
    : null;
$vehicle = trim(implode(' ', array_filter([
    $order['brand'] ?? null,
    $order['model'] ?? null,
    $order['year'] ?? null,
])));
$vehicleLabel = trim(($order['plate'] ?? '') . ($vehicle !== '' ? ' · ' . $vehicle : ''));
?>
<div class="page-header">
    <div>
        <h1>
            <i class="icon-base ti tabler-truck"></i>
            Pedido #<?= (int) ($order['id'] ?? 0) ?>
            <span class="badge <?= e($Status::badgeClass($status)) ?> ms-2"><?= e($Status::label($status)) ?></span>
        </h1>
        <span class="page-subtitle">
            <?php if ($orderNumber): ?>OS #<?= e($orderNumber) ?><?php endif; ?>
            <?php if (!empty($order['client_name'])): ?> · <?= e((string) $order['client_name']) ?><?php endif; ?>
            <?php if ($vehicle !== ''): ?> · <?= e($vehicle) ?><?php endif; ?>
        </span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($resource->path()) ?>" class="btn btn-label-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
        <?php if ($canManageStatus): ?>
        <a href="<?= e($resource->path() . '/' . (int) ($order['id'] ?? 0) . '/edit') ?>" class="btn btn-primary"><i class="icon-base ti tabler-pencil"></i> Editar pedido</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-6">
    <div class="col-12 col-xl-8">
        <div class="card mb-6">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Itens do pedido</h5>
                <span class="badge bg-label-primary"><?= e($itemCountLabel) ?></span>
            </div>
            <div class="card-body">
                <?php if ($items): ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Peça</th>
                                <th>SKU</th>
                                <th class="text-end">Qtd.</th>
                                <th class="text-end text-nowrap">Unitário</th>
                                <th class="text-end text-nowrap">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <?php
                            $itemPrice = $item['unit_price'] !== null ? (float) $item['unit_price'] : null;
                            $subtotal = $itemPrice !== null ? $itemPrice * (float) $item['quantity'] : null;
                            $meta = trim(implode(' · ', array_filter([
                                (string) ($item['brand'] ?? ''),
                                (string) ($item['part_code'] ?? ''),
                                (string) ($item['category'] ?? ''),
                            ])));
                            $productUrl = (string) ($item['product_url'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-sm me-3">
                                            <span class="avatar-initial rounded-circle bg-label-primary"><?= e($initials((string) $item['description'])) ?></span>
                                        </span>
                                        <div>
                                            <div class="fw-medium"><?= e((string) $item['description']) ?></div>
                                            <small class="text-body-secondary">
                                                <?= $meta !== '' ? e($meta) : 'Peça manual' ?>
                                                <?php if ($productUrl !== ''): ?>
                                                · <a href="<?= e($productUrl) ?>" target="_blank" rel="noopener" class="text-primary">Abrir no catálogo</a>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e((string) (($item['sku'] ?? '') ?: '—')) ?></td>
                                <td class="text-end"><?= e($qty((float) $item['quantity'])) ?></td>
                                <td class="text-end text-nowrap"><?= e($money($itemPrice)) ?></td>
                                <td class="text-end text-nowrap"><?= e($money($subtotal)) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total</th>
                                <th class="text-end text-nowrap"><?= $hasPrices ? e($money($total)) : '—' ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php if ($hasPrices && $pricedItems < count($items)): ?>
                <small class="text-body-secondary d-block mt-2">Itens sem preço não entram no total.</small>
                <?php endif; ?>
                <?php else: ?>
                <p class="text-body-secondary mb-0">Nenhum item registrado neste pedido.</p>
                <?php endif; ?>

                <?php if (!empty($order['notes'])): ?>
                <hr class="my-4">
                <small class="text-body-secondary d-block mb-1">Observações</small>
                <p class="mb-0"><?= nl2br(e((string) $order['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Histórico</h5></div>
            <div class="card-body">
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
                        $label = 'Pedido criado';
                        $point = 'primary';
                    } elseif ($action === 'delete') {
                        $label = 'Pedido excluído';
                        $point = 'danger';
                    } else {
                        $label = 'Pedido atualizado';
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
                <p class="text-body-secondary mb-0">Sem histórico registrado para este pedido.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card mb-6">
            <div class="card-header"><h5 class="card-title mb-0">Resumo</h5></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-body">Status</span>
                    <span class="badge <?= e($Status::badgeClass($status)) ?>"><?= e($Status::label($status)) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-body">OS</span>
                    <?php if ($orderNumber): ?>
                    <a href="/service-orders/<?= (int) ($order['service_order_id'] ?? 0) ?>" class="fw-medium">#<?= e($orderNumber) ?></a>
                    <?php else: ?>
                    <span class="fw-medium">—</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-body">Cliente</span>
                    <span class="fw-medium text-end"><?= e((string) ($order['client_name'] ?? '') ?: '—') ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-body">Veículo</span>
                    <span class="fw-medium text-end"><?= e($vehicleLabel !== '' ? $vehicleLabel : '—') ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-body">Fornecedor</span>
                    <span class="fw-medium text-end"><?= e((string) ($order['supplier'] ?? '') ?: '—') ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-body">Previsão</span>
                    <span class="fw-medium"><?= !empty($order['expected_date']) ? e(date('d/m/Y', strtotime((string) $order['expected_date']))) : '—' ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-body">Recebido em</span>
                    <span class="fw-medium"><?= !empty($order['received_at']) ? e(date('d/m/Y H:i', strtotime((string) $order['received_at']))) : '—' ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-body">Recebido por</span>
                    <span class="fw-medium text-end"><?= e((string) ($order['received_by_name'] ?? '') ?: '—') ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-body">Criado em</span>
                    <span class="fw-medium"><?= !empty($order['created_at']) ? e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) : '—' ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-body">Criado por</span>
                    <span class="fw-medium text-end"><?= e((string) ($order['created_by_name'] ?? '') ?: '—') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
