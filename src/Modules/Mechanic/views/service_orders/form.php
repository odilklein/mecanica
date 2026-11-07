<?php
$labels = $resource->labels();
$path = $resource->path();
$isEdit = $record !== null;
$action = $isEdit ? $path . '/' . (int) $record['id'] : $path;
$opts = $selectOptions ?? [];

$select = static function (string $name, string $label, string $icon, array $options, $value, string $col, bool $required = false): string {
    $html = '<div class="' . e($col) . '">';
    $html .= '<label class="form-label" for="' . e($name) . '">' . e($label) . ($required ? ' *' : '') . '</label>';
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
        <h1><i class="icon-base ti tabler-clipboard-list"></i><?= e($title) ?></h1>
        <span class="page-subtitle"><?= $isEdit ? 'Atualize os dados' : 'Preencha os dados' ?> da ordem de serviço</span>
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
                        <i class="icon-base ti tabler-clipboard-list"></i>
                        <div>
                            <h3 class="panel-title"><?= e($isEdit ? 'Editar OS' : 'Nova OS') ?></h3>
                            <p class="panel-subtitle">Os campos com * são obrigatórios</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?= $select('client_id', 'Cliente', 'user', $opts['client_id'] ?? [], $old['client_id'] ?? '', 'col-md-6', true) ?>
                        <?= $select('vehicle_id', 'Veículo', 'car', $opts['vehicle_id'] ?? [], $old['vehicle_id'] ?? '', 'col-md-6') ?>

                        <?= $select('priority', 'Prioridade', 'flag', $opts['priority'] ?? [], $old['priority'] ?? 'normal', 'col-md-4', true) ?>
                        <?= $select('status', 'Status', 'activity', $opts['status'] ?? [], $old['status'] ?? 'received', 'col-md-4', true) ?>
                        <div class="col-md-4">
                            <label class="form-label" for="entry_date">Entrada</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-calendar"></i></span>
                                <input type="date" class="form-control" id="entry_date" name="entry_date" value="<?= e($old['entry_date'] ?? date('Y-m-d')) ?>">
                            </div>
                        </div>

                        <?= $select('lead_mechanic_id', 'Responsável', 'user-check', $opts['lead_mechanic_id'] ?? [], $old['lead_mechanic_id'] ?? '', 'col-md-6') ?>

                        <div class="col-12">
                            <label class="form-label" for="initial_complaint">Queixa inicial</label>
                            <div class="input-group input-group-merge input-group-merge-textarea">
                                <span class="input-group-text"><i class="icon-base ti tabler-message-2"></i></span>
                                <textarea class="form-control" id="initial_complaint" name="initial_complaint" rows="3"><?= e($old['initial_complaint'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="diagnosis">Diagnóstico</label>
                            <div class="input-group input-group-merge input-group-merge-textarea">
                                <span class="input-group-text"><i class="icon-base ti tabler-stethoscope"></i></span>
                                <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"><?= e($old['diagnosis'] ?? '') ?></textarea>
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
