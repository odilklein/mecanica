<?php
$path = $resource->path();
$isEdit = $record !== null;
$action = $isEdit ? $path . '/' . (int) $record['id'] : $path;
$opts = $selectOptions ?? [];
$items = $items ?? [];
$canManageStatus = $canManageStatus ?? true;
$canManageSuppliers = $canManageSuppliers ?? false;
$currentStatus = (string) ($old['status'] ?? 'requested');
$orderDetails = $opts['order_details'] ?? [];

$select = static function (string $name, string $label, string $icon, array $options, $value, string $col, bool $allowEmpty = true): string {
    $html = '<div class="' . e($col) . '"><label class="form-label" for="' . e($name) . '">' . e($label) . '</label>';
    $html .= '<select class="select2 form-select" id="' . e($name) . '" name="' . e($name) . '" data-placeholder="Selecione...">';
    if ($allowEmpty) {
        $html .= '<option value=""></option>';
    }
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
                            <h3 class="panel-title"><?= $isEdit ? 'Editar pedido' : ($canManageStatus ? 'Novo pedido' : 'Solicitar peça') ?></h3>
                            <p class="panel-subtitle"><?= $canManageStatus ? 'Informe a OS, o fornecedor e os itens solicitados' : 'Escolha a OS e busque as peças necessárias' ?></p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?= $select('service_order_id', 'Ordem de serviço *', 'clipboard-list', $opts['service_order_id'] ?? [], $old['service_order_id'] ?? '', $canManageStatus ? 'col-md-6' : 'col-12') ?>

                        <?php if ($canManageStatus): ?>
                            <?= $select('status', 'Status', 'activity', $opts['status'] ?? [], $currentStatus, 'col-md-6', false) ?>
                            <div class="col-12">
                                <div class="form-text">Fluxo: Solicitado → Validado → Pedido → A caminho → Recebido. Cancelado encerra o pedido.</div>
                            </div>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info py-2 mb-0">
                                    <i class="icon-base ti tabler-info-circle me-1"></i>
                                    O pedido será criado como <strong>Solicitado</strong>. Fornecedor, previsão e andamento são preenchidos pela secretaria.
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($canManageStatus): ?>
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
                        <?php endif; ?>

                        <div class="col-12">
                            <div class="card border mb-0">
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar">
                                            <span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-car icon-22px"></i></span>
                                        </span>
                                        <div class="min-w-0">
                                            <h6 class="mb-0" id="partOrderVehicleTitle">Selecione a OS</h6>
                                            <small class="text-body-secondary d-block text-truncate" id="partOrderVehicleInfo">O veículo da OS aparecerá aqui.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                                        <div class="col-12">
                                            <label class="form-label" for="partOrderSearch">Buscar peça no catálogo</label>
                                            <select class="form-select part-autocomplete" id="partOrderSearch" data-placeholder="Buscar por nome, marca ou código..."></select>
                                            <div class="form-text">Ao escolher uma peça, confira os detalhes antes de adicionar.</div>
                                        </div>
                                    </div>

                                    <div class="input-group mt-3">
                                        <input type="text" class="form-control" id="partOrderManual" placeholder="Ou digite uma peça manual...">
                                        <button type="button" class="btn btn-outline-primary" id="partOrderManualAdd" aria-label="Adicionar peça manual"><i class="icon-base ti tabler-plus"></i></button>
                                    </div>

                                    <div class="dynamic-list mt-3" data-list="part-order-items">
                                        <div class="dynamic-list-items" id="partOrderItems"></div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3 d-none" id="partOrderItemsPager">
                                        <small class="text-body-secondary" id="partOrderItemsInfo"></small>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" id="partOrderItemsPrev">Anterior</button>
                                            <button type="button" class="btn btn-outline-secondary" id="partOrderItemsNext">Próxima</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                            <a href="<?= e($path) ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i> <?= $canManageStatus ? 'Salvar pedido' : 'Solicitar peça' ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: quick view da peça -->
<div class="modal fade" id="partQuickViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes da peça</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="part-image-preview border rounded-3 d-flex align-items-center justify-content-center bg-label-secondary"
                             id="quickViewImage" style="min-height: 220px;"></div>
                    </div>
                    <div class="col-md-8">
                        <h5 id="quickViewName" class="mb-1"></h5>
                        <p class="text-body-secondary mb-3" id="quickViewMeta"></p>
                        <div class="d-flex flex-wrap gap-4 mb-3">
                            <div id="quickViewSkuWrap" class="d-none">
                                <small class="text-body-secondary d-block">SKU</small>
                                <span class="fw-medium" id="quickViewSku"></span>
                            </div>
                            <div id="quickViewBarcodeWrap" class="d-none">
                                <small class="text-body-secondary d-block">Código de barras</small>
                                <span class="fw-medium" id="quickViewBarcode"></span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="badge bg-label-success" id="quickViewPrice"></span>
                            <a href="#" class="btn btn-sm btn-label-primary d-none" id="quickViewLink" target="_blank" rel="noopener">
                                <i class="icon-base ti tabler-external-link"></i> Abrir no catálogo
                            </a>
                        </div>
                        <div id="quickViewDescriptionWrap" class="mb-3 d-none">
                            <small class="text-body-secondary d-block mb-1">Descrição</small>
                            <p class="mb-0" id="quickViewDescription"></p>
                        </div>
                        <div id="quickViewApplicationsWrap" class="d-none">
                            <small class="text-body-secondary d-block mb-1">Aplicação veicular</small>
                            <p class="mb-0" id="quickViewApplications"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex align-items-center gap-2 me-auto">
                    <label class="form-label mb-0" for="quickViewQty">Quantidade</label>
                    <input type="number" class="form-control" id="quickViewQty" value="1" min="1" max="99" step="1" style="max-width: 100px;">
                </div>
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="quickViewAdd">
                    <i class="icon-base ti tabler-plus"></i> Adicionar peça
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.PART_ORDER_ITEMS = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.PART_ORDER_ORDERS = <?= json_encode($orderDetails, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
