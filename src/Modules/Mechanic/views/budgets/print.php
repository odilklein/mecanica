<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento #<?= str_pad((string) $budget['number'], 4, '0', STR_PAD_LEFT) ?></title>
    <link rel="icon" href="/assets/img/favicon/favicon.ico">
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, "Segoe UI", Arial, sans-serif; color: #111; margin: 0; padding: 32px; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: #666; font-size: 12px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #b8270f; padding-bottom: 16px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background: #f4f4f4; }
        .totals { margin-top: 16px; margin-left: auto; width: 320px; }
        .totals div { display: flex; justify-content: space-between; padding: 6px 0; }
        .totals .total { border-top: 2px solid #b8270f; font-size: 18px; font-weight: bold; padding-top: 10px; }
        .actions { margin-top: 24px; }
        .actions button { padding: 10px 20px; background: #b8270f; color: #fff; border: 0; border-radius: 6px; cursor: pointer; }
        .signature { margin-top: 56px; width: 320px; }
        .signature .line { border-top: 1px solid #333; margin-bottom: 6px; }
        .terms { margin-top: 24px; font-size: 12px; }
        @media print {
            .actions { display: none; }
            body { padding: 0; }
            .signature { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <?php
    $companyName = (string) setting('company_name', setting('seo_title', 'Mecânica')) ?: 'Mecânica';
    $companyAddress = trim((string) setting('company_address', ''));
    $companyCity = trim((string) setting('company_city', '')
        . ((string) setting('company_state', '') !== '' ? '/' . setting('company_state', '') : ''));
    if ($companyCity !== '') {
        $companyAddress .= ($companyAddress !== '' ? ' · ' : '') . $companyCity;
    }
    $companyLines = array_values(array_filter([
        (string) setting('company_document', ''),
        $companyAddress,
        (string) setting('company_phone', ''),
        (string) setting('company_email', ''),
    ], static fn (string $line): bool => trim($line) !== ''));
    ?>
    <div class="header">
        <div>
            <h1><?= e($companyName) ?></h1>
            <?php foreach ($companyLines as $line): ?>
            <div class="muted"><?= e($line) ?></div>
            <?php endforeach; ?>
            <div class="muted">Orçamento #<?= str_pad((string) $budget['number'], 4, '0', STR_PAD_LEFT) ?></div>
        </div>
        <div class="muted" style="text-align:right">
            <div>Data: <?= $budget['budget_date'] ? e(date('d/m/Y', strtotime($budget['budget_date']))) : '—' ?></div>
            <div>Validade: <?= $budget['valid_until'] ? e(date('d/m/Y', strtotime($budget['valid_until']))) : '—' ?></div>
            <div>Status: <?= e(\Modules\Mechanic\Support\BudgetStatus::label($budget['status'])) ?></div>
        </div>
    </div>

    <div style="display:flex; gap:40px; font-size:13px">
        <div>
            <strong>Cliente</strong><br>
            <?= e($budget['client_name'] ?: '—') ?>
            <?php if (!empty($budget['client_phone'])): ?><br><span class="muted"><?= e($budget['client_phone']) ?></span><?php endif; ?>
            <?php if (!empty($budget['client_email'])): ?><br><span class="muted"><?= e($budget['client_email']) ?></span><?php endif; ?>
        </div>
        <div>
            <strong>Veículo</strong><br>
            <?= e($budget['vehicle_plate'] ?: '—') ?>
            <br><span class="muted">
                <?= e($budget['vehicle_model'] ?: 'Modelo não informado') ?>
                <?= !empty($budget['vehicle_year']) ? ' · ' . (int) $budget['vehicle_year'] : '' ?>
                <?= !empty($budget['vehicle_color']) ? ' · ' . e($budget['vehicle_color']) : '' ?>
            </span>
        </div>
        <?php if (!empty($budget['order_number'])): ?>
        <div><strong>OS</strong><br>#<?= str_pad((string) $budget['order_number'], 4, '0', STR_PAD_LEFT) ?></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($budget['notes'])): ?>
    <p class="muted" style="margin-top:16px"><?= nl2br(e($budget['notes'])) ?></p>
    <?php endif; ?>

    <table>
        <thead><tr><th>Tipo</th><th>Descrição</th><th>Qtd</th><th>Unitário</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= $item['type'] === 'service' ? 'Serviço' : 'Peça' ?></td>
                <td><?= e($item['description']) ?></td>
                <td><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',')) ?></td>
                <td>R$ <?= number_format((float) $item['unit_price'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="5" class="muted">Nenhum item.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <div class="totals">
        <div><span>Peças</span><strong>R$ <?= number_format((float) $budget['parts_total'], 2, ',', '.') ?></strong></div>
        <div><span>Mão de obra</span><strong>R$ <?= number_format((float) $budget['labor_total'], 2, ',', '.') ?></strong></div>
        <div><span>Desconto</span><strong>R$ <?= number_format((float) $budget['discount'], 2, ',', '.') ?></strong></div>
        <div class="total"><span>TOTAL</span><span>R$ <?= number_format((float) $budget['total'], 2, ',', '.') ?></span></div>
    </div>

    <?php $terms = trim((string) setting('budget_terms', '')); ?>
    <?php if ($terms !== ''): ?>
    <div class="terms">
        <strong>Termos e condições</strong>
        <div class="muted"><?= nl2br(e($terms)) ?></div>
    </div>
    <?php endif; ?>

    <div class="signature">
        <div class="line"></div>
        <div class="muted">Assinatura do cliente</div>
    </div>

    <p class="muted" style="margin-top:32px">Documento gerado em <?= e(date('d/m/Y H:i')) ?>.</p>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>
