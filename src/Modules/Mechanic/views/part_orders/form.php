<?php
$path = $resource->path();
$isEdit = $record !== null;
$action = $isEdit ? $path . '/' . (int) $record['id'] : $path;
$opts = $selectOptions ?? [];
$items = $items ?? [];

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
                        <?= $select('status', 'Status', 'activity', $opts['status'] ?? [], $old['status'] ?? 'requested', 'col-md-6') ?>

                        <div class="col-md-8">
                            <label class="form-label" for="supplier">Fornecedor</label>
                            <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-building-store"></i></span>
                                <input type="text" class="form-control" id="supplier" name="supplier" value="<?= e($old['supplier'] ?? '') ?>" maxlength="120" placeholder="Ex.: Auto Peças Silva"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="expected_date">Previsão de entrega</label>
                            <div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-calendar"></i></span>
                                <input type="date" class="form-control" id="expected_date" name="expected_date" value="<?= e($old['expected_date'] ?? '') ?>"></div>
                        </div>

                        <div class="col-12">
                            <div class="card border mb-3">
                                <div class="card-body py-3">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-9">
                                            <label class="form-label" for="partOrderSearch">Buscar peça no catálogo</label>
                                            <select class="form-select part-autocomplete" id="partOrderSearch" data-placeholder="Digite nome, marca ou código..."></select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="partOrderSearchQty">Quantidade</label>
                                            <input type="number" class="form-control" id="partOrderSearchQty" value="1" min="1" step="1">
                                        </div>
                                    </div>
                                    <div class="form-text">Escolha uma sugestão. Para uma peça que não está na lista, use “Adicionar item”.</div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Itens do pedido</label>
                                <button type="button" class="btn btn-sm btn-label-primary" id="addPartItem">
                                    <i class="icon-base ti tabler-plus"></i> Adicionar item
                                </button>
                            </div>

                            <div class="table-responsive border rounded">
                                <table class="table align-middle mb-0 responsive-card">
                                    <thead>
                                        <tr>
                                            <th>Descrição da peça</th>
                                            <th style="width: 130px;">Quantidade</th>
                                            <th style="width: 48px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="partOrderItems"></tbody>
                                </table>
                            </div>
                            <div class="form-text">Digite a descrição e pressione <kbd>Enter</kbd> para adicionar outra linha.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Observações</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($old['notes'] ?? '') ?></textarea>
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
