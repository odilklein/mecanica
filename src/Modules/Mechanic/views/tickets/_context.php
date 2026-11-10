<?php
// Contexto da OS exibido dentro do ticket (queixa, observações, veículo e peças).
$order = $context['order'] ?? [];
$items = $context['items'] ?? [];
$partOrders = $context['part_orders'] ?? [];
$Status = \Modules\Mechanic\Support\ServiceOrderStatus::class;
$PartStatus = \Modules\Mechanic\Support\PartOrderStatus::class;

$parts = array_values(array_filter($items, static fn (array $item): bool => ($item['type'] ?? '') === 'part'));
$services = array_values(array_filter($items, static fn (array $item): bool => ($item['type'] ?? '') === 'service'));

$vehicleParts = array_filter([
    $order['brand'] ?? null,
    $order['model'] ?? null,
    $order['year'] ?? null,
]);
$vehicle = implode(' ', $vehicleParts);
$money = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$qty = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
$orderNumber = str_pad((string) ($order['number'] ?? 0), 4, '0', STR_PAD_LEFT);
$orderId = (int) ($order['id'] ?? 0);
$status = (string) ($order['status'] ?? 'received');
?>
<div class="ticket-os-context">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-text-secondary p-0 d-flex align-items-start gap-2 text-start flex-grow-1"
                data-bs-toggle="collapse" data-bs-target="#ticketContextBody" aria-expanded="false" aria-controls="ticketContextBody">
            <i class="icon-base ti tabler-info-circle mt-1"></i>
            <span class="min-w-0">
                <span class="fw-medium d-flex flex-wrap align-items-center gap-2">
                    Contexto da OS #<?= $orderNumber ?>
                    <span class="badge <?= e($Status::badgeClass($status)) ?>"><?= e($Status::label($status)) ?></span>
                </span>
                <small class="text-body-secondary d-block text-truncate">
                    <?= e($order['plate'] ?: 'Sem placa') ?>
                    <?php if ($vehicle !== ''): ?> · <?= e($vehicle) ?><?php endif; ?>
                    <?php if (!empty($order['client_name'])): ?> · <?= e((string) $order['client_name']) ?><?php endif; ?>
                </small>
            </span>
            <i class="icon-base ti tabler-chevron-down ms-auto mt-1"></i>
        </button>
        <a href="/service-orders/<?= $orderId ?>" class="btn btn-sm btn-label-primary">
            <i class="icon-base ti tabler-external-link"></i> Abrir OS
        </a>
    </div>

    <div class="collapse" id="ticketContextBody">
        <div class="row g-3 mt-0">
            <div class="col-md-6">
                <div class="mb-2">
                    <small class="text-body-secondary d-block">Veículo</small>
                    <span class="fw-medium"><?= e($order['plate'] ?: '—') ?></span>
                    <?php if ($vehicle !== ''): ?>
                    <span class="text-body">· <?= e($vehicle) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($order['color'])): ?>
                    <span class="text-body-secondary">· <?= e($order['color']) ?></span>
                    <?php endif; ?>
                    <?php if ($order['current_km'] !== null): ?>
                    <span class="text-body-secondary">· <?= number_format((int) $order['current_km'], 0, ',', '.') ?> km</span>
                    <?php endif; ?>
                </div>
                <div class="mb-2">
                    <small class="text-body-secondary d-block">Queixa do cliente</small>
                    <span><?= $order['initial_complaint'] ? nl2br(e((string) $order['initial_complaint'])) : 'Não informada.' ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-2">
                    <small class="text-body-secondary d-block">Observações internas</small>
                    <span><?= $order['internal_notes'] ? nl2br(e((string) $order['internal_notes'])) : 'Nenhuma observação.' ?></span>
                </div>
                <?php if ($parts): ?>
                <div class="mb-2">
                    <small class="text-body-secondary d-block">Peças necessárias</small>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach (array_slice($parts, 0, 6) as $part): ?>
                        <li class="d-flex justify-content-between gap-2">
                            <span><?= e((string) $part['description']) ?> ×<?= e($qty((float) $part['quantity'])) ?></span>
                            <span class="text-body-secondary text-nowrap"><?= e($money((float) $part['subtotal'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if ($services && !$parts): ?>
                <div class="mb-2">
                    <small class="text-body-secondary d-block">Serviços</small>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach (array_slice($services, 0, 4) as $service): ?>
                        <li><?= e((string) $service['description']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($partOrders): ?>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php foreach ($partOrders as $po): ?>
            <span class="badge <?= e($PartStatus::badgeClass((string) $po['status'])) ?>">
                <?= e($po['supplier'] ?: 'Pedido') ?> · <?= e($PartStatus::label((string) $po['status'])) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
