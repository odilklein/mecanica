<?php
$path = $resource->path();
$isEdit = $budget !== null;
$bid = $isEdit ? (int) $budget['id'] : 0;
$defaults = $defaults ?? [];
$curClient = $isEdit ? (int) $budget['client_id'] : (int) ($defaults['client_id'] ?? 0);
$curVehicle = $isEdit ? (int) ($budget['vehicle_id'] ?? 0) : (int) ($defaults['vehicle_id'] ?? 0);
$curServiceOrder = $isEdit ? (int) ($budget['service_order_id'] ?? 0) : (int) ($defaults['service_order_id'] ?? 0);
$itemsJson = json_encode(array_map(static function ($i) {
    return [
        'type' => $i['type'],
        'description' => $i['description'],
        'quantity' => (float) $i['quantity'],
        'unit_price' => (float) $i['unit_price'],
    ];
}, $items), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$vehiclesJson = json_encode($vehicles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$opts = $selectOptions ?? [];
$ordersData = [];
foreach (($opts['service_order_id'] ?? []) as $orderId => $orderLabel) {
    $ordersData[] = [
        'id' => (int) $orderId,
        'label' => (string) $orderLabel,
        'client_id' => (int) ($opts['order_clients'][$orderId] ?? 0),
        'vehicle_id' => (int) ($opts['order_vehicles'][$orderId] ?? 0),
    ];
}
$ordersJson = json_encode($ordersData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$validityDays = max(1, min(365, (int) setting('budget_validity_days', 15)));
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-file-text"></i><?= e($title) ?></h1>
        <span class="page-subtitle">Monte o documento com itens de peça e mão de obra</span>
    </div>
    <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
</div>

<form method="POST" action="<?= e($path) ?>/save" id="formOrcamento">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $bid ?>">
    <input type="hidden" name="items_json" id="items_json" value="[]">

    <div class="row g-3">
        <div class="col-lg-8 col-xl-9">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="panel-header"><i class="icon-base ti tabler-user"></i><div><h3 class="panel-title">Cliente e veículo</h3></div></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="cliente_id">Cliente *</label>
                            <select class="select2 form-select" id="cliente_id" name="client_id" data-placeholder="Buscar cliente" required>
                                <option value=""></option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $curClient === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="veiculo_id">Veículo</label>
                            <select class="select2 form-select" id="veiculo_id" name="vehicle_id" data-placeholder="Selecione o cliente" data-current="<?= $curVehicle ?>">
                                <option value="">Selecione o cliente</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="service_order_id">Ordem de serviço</label>
                            <select class="select2 form-select" id="service_order_id" name="service_order_id"
                                    data-placeholder="Sem OS vinculada" data-current="<?= $curServiceOrder ?>">
                                <option value="">Sem OS vinculada</option>
                                <?php foreach (($opts['service_order_id'] ?? []) as $orderId => $orderLabel): ?>
                                <option value="<?= (int) $orderId ?>" <?= $curServiceOrder === (int) $orderId ? 'selected' : '' ?>><?= e($orderLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Opcional. Ao escolher a OS, o veículo dela é sugerido.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="valid_until">Validade</label>
                            <input type="date" class="form-control" id="valid_until" name="valid_until" value="<?= e($budget['valid_until'] ?? date('Y-m-d', strtotime('+' . $validityDays . ' days'))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="discount">Desconto (R$)</label>
                            <input type="number" class="form-control" id="discount" name="discount" min="0" step="0.01" value="<?= e($budget['discount'] ?? '0') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Observações</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($budget['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="panel-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2"><i class="icon-base ti tabler-list"></i><h3 class="panel-title mb-0">Itens do orçamento</h3></div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddItem"><i class="icon-base ti tabler-plus"></i> Adicionar item</button>
                    </div>
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-8">
                            <label class="form-label" for="budgetPartSearch">Buscar peça no catálogo</label>
                            <select class="form-select part-autocomplete" id="budgetPartSearch" data-placeholder="Buscar por nome, marca ou código..."></select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-text">A peça entra como item com o preço de referência.</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th style="width:18%">Tipo</th><th>Item</th><th style="width:86px">Qtd</th><th style="width:120px">Preço</th><th style="width:110px">Subtotal</th><th style="width:40px"></th></tr></thead>
                            <tbody id="corpoItens"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-xl-3">
            <div class="card sticky-top" style="top: 80px;">
                <div class="card-body">
                    <div class="panel-header"><i class="icon-base ti tabler-calculator"></i><div><h3 class="panel-title">Resumo</h3></div></div>
                    <div class="d-flex justify-content-between mb-2"><span>Peças:</span><strong id="totalPecas">R$ 0,00</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Mão de obra:</span><strong id="totalServicos">R$ 0,00</strong></div>
                    <div class="d-flex justify-content-between mb-3"><span>Desconto:</span><strong id="totalDesconto">R$ 0,00</strong></div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3"><span class="h5 mb-0">TOTAL:</span><strong class="h4 mb-0 text-primary" id="totalGeral">R$ 0,00</strong></div>
                    <button type="submit" class="btn btn-success btn-lg w-100"><i class="icon-base ti tabler-device-floppy"></i> Salvar orçamento</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
window.BUDGET_ITEMS = <?= $itemsJson ?>;
window.BUDGET_VEHICLES = <?= $vehiclesJson ?>;
window.BUDGET_ORDERS = <?= $ordersJson ?>;
</script>
<script src="<?= e(asset('/js/part-autocomplete.js')) ?>"></script>
<script src="<?= e(asset('/js/budget-editor.js')) ?>"></script>
