<?php
$labels = $resource->labels();
$path = $resource->path();
$isEdit = $record !== null;
$action = $isEdit ? $path . '/' . (int) $record['id'] : $path;
$opts = $selectOptions ?? [];

$select = static function (string $name, string $label, string $icon, array $options, $value, string $col, bool $required = false, bool $allowEmpty = true): string {
    $priorityClass = $name === 'priority' ? ' select2-priority' : '';
    $html = '<div class="' . e($col) . '">';
    $html .= '<label class="form-label" for="' . e($name) . '">' . e($label) . ($required ? ' *' : '') . '</label>';
    $html .= '<select class="select2 form-select' . $priorityClass . '" id="' . e($name) . '" name="' . e($name) . '" data-placeholder="Selecione...">';
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
        <h1>
            <i class="icon-base ti tabler-clipboard-list"></i><?= e($title) ?><?= $isEdit && !empty($record['number'])
                ? ' #' . str_pad((string) $record['number'], 4, '0', STR_PAD_LEFT)
                : '' ?>
        </h1>
        <span class="page-subtitle"><?= $isEdit ? 'Atualize os dados' : 'Preencha os dados' ?> da ordem de serviço</span>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12">
        <form method="POST" action="<?= e($action) ?>" id="formSO">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <?php if (!empty($errors['_form'])): ?>
            <div class="alert alert-danger" role="alert"><?= e($errors['_form']) ?></div>
            <?php endif; ?>

            <!-- Cliente e veículo -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-user"></i>
                        <div>
                            <h3 class="panel-title">Cliente e veículo</h3>
                            <p class="panel-subtitle">Vincule o atendimento ao cliente e ao veículo</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <?= $select('client_id', 'Cliente', 'user', $opts['client_id'] ?? [], $old['client_id'] ?? '', 'col-md-6', true) ?>
                        <?= $select('vehicle_id', 'Veículo', 'car', $opts['vehicle_id'] ?? [], $old['vehicle_id'] ?? '', 'col-md-6') ?>
                        <div class="col-12">
                            <div class="form-text text-warning d-none" id="vehicleEmptyHint">
                                Nenhum veículo cadastrado para este cliente.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Atendimento -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-activity"></i>
                        <div>
                            <h3 class="panel-title">Atendimento</h3>
                            <p class="panel-subtitle">Prioridade, etapa atual e responsável</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <?= $select('priority', 'Prioridade', 'flag', $opts['priority'] ?? [], $old['priority'] ?? 'normal', 'col-md-4', true) ?>
                        <?php if ($isEdit && count($opts['status'] ?? []) <= 1): ?>
                        <div class="col-md-4">
                            <label class="form-label" for="statusReadonly">Status</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-activity"></i></span>
                                <input type="text" class="form-control" id="statusReadonly"
                                       value="<?= e((string) (array_values($opts['status'] ?? [])[0] ?? '—')) ?>" readonly>
                            </div>
                            <input type="hidden" name="status" value="<?= e((string) (array_key_first($opts['status'] ?? []) ?: ($old['status'] ?? 'received'))) ?>">
                        </div>
                        <?php else: ?>
                        <?= $select('status', 'Status', 'activity', $opts['status'] ?? [], $old['status'] ?? 'received', 'col-md-4', true, false) ?>
                        <?php endif; ?>
                        <div class="col-12">
                            <div class="form-text">
                                Fluxo: Recebida → Em análise → Aguardando aprovação/peça → Em execução → Pronta para entrega.
                                “Entregue” é registrada na página da OS.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="entry_date">Entrada</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-calendar"></i></span>
                                <input type="date" class="form-control" id="entry_date" name="entry_date" value="<?= e($old['entry_date'] ?? date('Y-m-d')) ?>">
                            </div>
                        </div>
                        <?= $select('lead_mechanic_id', 'Mecânico responsável', 'user-check', $opts['lead_mechanic_id'] ?? [], $old['lead_mechanic_id'] ?? '', 'col-md-6') ?>
                    </div>
                </div>
            </div>

            <!-- Descrição -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-message-2"></i>
                        <div>
                            <h3 class="panel-title">Descrição do serviço</h3>
                            <p class="panel-subtitle">Queixa inicial e diagnóstico técnico</p>
                        </div>
                    </div>
                    <div class="row g-3">
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
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= e($path) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php
$vehicleData = [];
foreach (($opts['vehicle_id'] ?? []) as $vehicleId => $vehicleLabel) {
    $vehicleData[] = [
        'id' => (int) $vehicleId,
        'label' => (string) $vehicleLabel,
        'client_id' => (int) ($opts['vehicle_clients'][$vehicleId] ?? 0),
    ];
}
?>
<script>
window.SO_VEHICLES = <?= json_encode($vehicleData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
