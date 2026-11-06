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
    $html .= '<div class="input-group input-group-merge">';
    $html .= '<span class="input-group-text"><i class="icon-base ti tabler-' . e($icon) . '"></i></span>';
    $html .= '<select class="form-select" id="' . e($name) . '" name="' . e($name) . '"' . $extra . '>';
    $html .= '<option value="">Selecione...</option>';
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
        <h1><i class="icon-base ti tabler-car"></i><?= e($title) ?></h1>
        <span class="page-subtitle"><?= $isEdit ? 'Atualize os dados' : 'Preencha os dados' ?> do veículo</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
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
                            <p class="panel-subtitle">Os campos com * são obrigatórios</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?= $select('client_id', 'Cliente *', 'user', $opts['client_id'] ?? [], $old['client_id'] ?? '', 'col-md-6') ?>

                        <div class="col-md-3">
                            <label class="form-label" for="plate">Placa *</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-license"></i></span>
                                <input type="text" class="form-control <?= isset($errors['plate']) ? 'is-invalid' : '' ?>" id="plate" name="plate" value="<?= e($old['plate'] ?? '') ?>" maxlength="10" data-mask="plate">
                            </div>
                            <?php if (isset($errors['plate'])): ?><div class="invalid-feedback d-block"><?= e($errors['plate']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="year">Ano</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-calendar"></i></span>
                                <input type="number" class="form-control" id="year" name="year" value="<?= e($old['year'] ?? '') ?>" min="1950" max="2030">
                            </div>
                        </div>

                        <?= $select('fipe_brand_code', 'Marca (FIPE)', 'building-factory-2', $opts['fipe_brand_code'] ?? [], $old['fipe_brand_code'] ?? '', 'col-md-6', ['data-fipe-brand' => '1']) ?>
                        <?= $select('fipe_model_code', 'Modelo (FIPE)', 'car', $opts['fipe_model_code'] ?? [], $old['fipe_model_code'] ?? '', 'col-md-6', ['data-fipe-model' => '1']) ?>
                        <?= $select('fipe_year_code', 'Ano (FIPE)', 'calendar-event', $opts['fipe_year_code'] ?? [], $old['fipe_year_code'] ?? '', 'col-md-3', ['data-fipe-year' => '1']) ?>

                        <div class="col-md-3">
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
                                <input type="text" class="form-control" id="color" name="color" value="<?= e($old['color'] ?? '') ?>" maxlength="30">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="current_km">KM atual</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-gauge"></i></span>
                                <input type="number" class="form-control" id="current_km" name="current_km" value="<?= e($old['current_km'] ?? '') ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="chassis">Chassi</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-fingerprint"></i></span>
                                <input type="text" class="form-control" id="chassis" name="chassis" value="<?= e($old['chassis'] ?? '') ?>" maxlength="30">
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Observações</label>
                            <div class="input-group input-group-merge input-group-merge-textarea">
                                <span class="input-group-text"><i class="icon-base ti tabler-notes"></i></span>
                                <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($old['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= ($old['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active"><i class="icon-base ti tabler-toggle-left me-1"></i>Ativo</label>
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

<script src="/js/fipe.js"></script>
