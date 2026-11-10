<?php
$labels = $resource->labels();
$path = $resource->path();
$isEdit = $record !== null;
$action = $isEdit ? $path . '/' . (int) $record['id'] : $path;
$opts = $selectOptions ?? [];

$select = static function (string $name, string $label, string $icon, array $options, $value, string $col, array $attrs = []): string {
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . $k . '="' . e($v) . '"';
    }
    $html = '<div class="' . e($col) . '">';
    $html .= '<label class="form-label" for="' . e($name) . '">' . e($label) . '</label>';
    $html .= '<select class="select2 form-select" id="' . e($name) . '" name="' . e($name) . '" data-placeholder="Selecione..."' . $extra . '>';
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
        <h1><i class="icon-base ti tabler-car"></i><?= e($title) ?></h1>
        <span class="page-subtitle"><?= $isEdit ? 'Atualize os dados' : 'Preencha os dados' ?> do veículo</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="<?= e($action) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                    <?php if (!empty($errors['_form'])): ?>
                    <div class="alert alert-danger" role="alert"><?= e($errors['_form']) ?></div>
                    <?php endif; ?>

                    <div class="panel-header">
                        <i class="icon-base ti tabler-car"></i>
                        <div>
                            <h3 class="panel-title"><?= e($isEdit ? 'Editar Veículo' : 'Novo Veículo') ?></h3>
                            <p class="panel-subtitle">Escolha a marca, o modelo e o ano para carregar o valor FIPE</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?= $select('client_id', 'Cliente *', 'user', $opts['client_id'] ?? [], $old['client_id'] ?? '', 'col-md-8') ?>
                        <div class="col-md-4">
                            <label class="form-label" for="plate">Placa *</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-license"></i></span>
                                <input type="text" class="form-control <?= isset($errors['plate']) ? 'is-invalid' : '' ?>" id="plate" name="plate" value="<?= e($old['plate'] ?? '') ?>" maxlength="10" data-mask="plate" placeholder="ABC-1234">
                            </div>
                            <?php if (isset($errors['plate'])): ?><div class="invalid-feedback d-block"><?= e($errors['plate']) ?></div><?php endif; ?>
                        </div>

                        <?= $select('fipe_brand_code', 'Marca *', 'building-factory-2', $opts['fipe_brand_code'] ?? [], $old['fipe_brand_code'] ?? '', 'col-md-6', ['data-fipe-brand' => '1']) ?>
                        <?= $select('fipe_model_code', 'Modelo *', 'car', $opts['fipe_model_code'] ?? [], $old['fipe_model_code'] ?? '', 'col-md-6', ['data-fipe-model' => '1']) ?>

                        <?= $select('fipe_year_code', 'Ano *', 'calendar-event', $opts['fipe_year_code'] ?? [], $old['fipe_year_code'] ?? '', 'col-md-4', ['data-fipe-year' => '1']) ?>

                        <div class="col-md-4">
                            <label class="form-label" for="fipe_value_display">Valor FIPE</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-currency-real"></i></span>
                                <input type="text" class="form-control" id="fipe_value_display" value="<?= !empty($old['fipe_value']) ? e(number_format((float) $old['fipe_value'], 2, ',', '.')) : '' ?>" readonly placeholder="—">
                            </div>
                            <input type="hidden" id="fipe_value" name="fipe_value" value="<?= e($old['fipe_value'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="color">Cor</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-palette"></i></span>
                                <input type="text" class="form-control" id="color" name="color" value="<?= e($old['color'] ?? '') ?>" maxlength="30" placeholder="Ex.: Prata">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= ((string) ($old['is_active'] ?? '1') !== '0') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active"><i class="icon-base ti tabler-toggle-right me-1"></i>Veículo ativo</label>
                            </div>
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
