<?php
$path = $resource->path();
$isEdit = $record !== null;
$action = $isEdit ? $path . '/' . (int) $record['id'] : $path;
$opts = $selectOptions ?? [];
$items = $items ?? [];
$canManageStatus = $canManageStatus ?? true;
$canManageSuppliers = $canManageSuppliers ?? false;
$currentStatus = (string) ($old['status'] ?? 'requested');

$select = static function (string $name, string $label, string $icon, array $options, $value, string $col): string {
    $html = '<div class="' . e($col) . '"><label class="form-label" for="' . e($name) . '">' . e($label) . '</label>';
    $html .= '<select class="select2 form-select" id="' . e($name) . '" name="' . e($name) . '" data-placeholder="Selecione...">';
    $html .= '<option value=""></option>';
    foreach ($options as $ov => $ol) {
        $selected = ((string) $value === (string) $ov) ? ' selected' : '';
        $html .= '<option value="' . e((string) $ov) . '"' . $selected . '>' . e($ol) . '</option>';
    }
    $html .= '</select></div>';
    return $html;
};
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-truck"></i><?= e($title) ?></h1>
        <span class="page-subtitle">Vincule as peças à ordem de serviço</span>
    </div>
    <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="<?= e($action) ?>" id="formPartOrder">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="items_json" id="items_json" value="[]">

                    <div class="panel-header">
                        <i class="icon-base ti tabler-truck"></i>
                        <div>
                            <h3 class="panel-title"><?= $isEdit ? 'Editar pedido' : 'Novo pedido' ?></h3>
                            <p class="panel-subtitle">Informe a OS, o fornecedor e os itens solicitados</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?= $select('service_order_id', 'Ordem de serviço *', 'clipboard-list', $opts['service_order_id'] ?? [], $old['service_order_id'] ?? '', 'col-md-6') ?>

                        <?php if ($canManageStatus): ?>
                            <?= $select('status', 'Status', 'activity', $opts['status'] ?? [], $currentStatus, 'col-md-6') ?>
                        <?php else: ?>
                            <div class="col-md-6">
                                <label class="form-label" for="statusReadonly">Status</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="icon-base ti tabler-activity"></i></span>
                                    <input type="text" class="form-control" id="statusReadonly" value="Solicitado" readonly>
                                </div>
                                <input type="hidden" name="status" value="requested">
                                <div class="form-text">O mecânico apenas solicita a peça; o andamento é atualizado pela gestão.</div>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-8">
                            <label class="form-label" for="supplier">Fornecedor</label>
                            <select class="select2 form-select" id="supplier" name="supplier" data-placeholder="Selecione o fornecedor">
                                <option value=""></option>
                                <?php foreach (($opts['supplier'] ?? []) as $supplierValue => $supplierLabel): ?>
                                    <option value="<?= e((string) $supplierValue) ?>" <?= ((string) ($old['supplier'] ?? '') === (string) $supplierValue) ? 'selected' : '' ?>><?= e($supplierLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($canManageSuppliers): ?>
                                <div class="form-text">
                                    Não encontrou? <a href="/suppliers/create" target="_blank" rel="noopener">Cadastre um novo fornecedor</a>.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="expected_date">Previsão de entrega</label>
                            <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-calendar"></i></span>
                                <input type="date" class="form-control" id="expected_date" name="expected_date" value="<?= e($old['expected_date'] ?? '') ?>"></div>
                        </div>

                        <div class="col-12">
                            <div class="card border mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Peças do pedido</h6>
                                        <small class="text-body-secondary">Busque no catálogo ou digite uma peça manual</small>
                                    </div>
                                    <span class="badge bg-label-primary" id="partOrderItemsCount">0</span>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label class="form-label" for="partOrderSearch">Buscar peça no catálogo</label>
                                            <select class="form-select part-autocomplete" id="partOrderSearch" data-placeholder="Buscar por nome, marca ou código..."></select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="partOrderSearchQty">Quantidade</label>
                                            <input type="number" class="form-control" id="partOrderSearchQty" value="1" min="1" max="99" step="1" aria-label="Quantidade">
                                        </div>
                                    </div>

                                    <div class="input-group mt-3">
                                        <input type="text" class="form-control" id="partOrderManual" placeholder="Ou digite uma peça manual...">
                                        <button type="button" class="btn btn-outline-primary" id="partOrderManualAdd" aria-label="Adicionar peça manual"><i class="icon-base ti tabler-plus"></i></button>
                                    </div>

                                    <div class="dynamic-list mt-3" data-list="part-order-items">
                                        <div class="dynamic-list-items" id="partOrderItems"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                            <a href="<?= e($path) ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i> Salvar pedido</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.PART_ORDER_ITEMS = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
