<?php
$path = $resource->path();
$isEdit = $record !== null;
$action = $isEdit ? $path . '/' . (int) $record['id'] : $path;
$opts = $selectOptions ?? [];

$select = static function (string $name, string $label, string $icon, array $options, $value, string $col): string {
    $html = '<div class="' . e($col) . '"><label class="form-label" for="' . e($name) . '">' . e($label) . '</label>';
    $html .= '<div class="input-group input-group-merge"><span class="input-group-text"><i class="icon-base ti tabler-' . e($icon) . '"></i></span>';
    $html .= '<select class="form-select" id="' . e($name) . '" name="' . e($name) . '"><option value="">Selecione...</option>';
    foreach ($options as $ov => $ol) {
        $selected = ((string) $value === (string) $ov) ? ' selected' : '';
        $html .= '<option value="' . e((string) $ov) . '"' . $selected . '>' . e($ol) . '</option>';
    }
    $html .= '</select></div></div>';
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
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="<?= e($action) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-truck"></i>
                        <div><h3 class="panel-title"><?= $isEdit ? 'Editar pedido' : 'Novo pedido' ?></h3><p class="panel-subtitle">Informe a OS e os itens</p></div>
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
                            <label class="form-label" for="items_text">Itens</label>
                            <textarea class="form-control" id="items_text" name="items_text" rows="5" placeholder="Óleo do motor | 1&#10;Filtro de óleo | 1&#10;Pastilha de freio | 2"><?= e($itemsText ?? '') ?></textarea>
                            <div class="form-text">Um item por linha, no formato <code>descrição | quantidade</code>.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Observações</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($old['notes'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                            <a href="<?= e($path) ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i> Salvar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
