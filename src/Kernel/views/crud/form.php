<?php
$path = $resource->path();
$isEdit = $record !== null;
$action = $isEdit ? $path . '/' . (int) $record['id'] : $path;
$formTitle = $resource->formTitle($isEdit);
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-<?= e($resource->icon()) ?>"></i><?= e($formTitle) ?></h1>
        <span class="page-subtitle"><?= $isEdit ? 'Atualize os dados do cadastro' : 'Preencha os dados do cadastro' ?></span>
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
                        <i class="icon-base ti tabler-<?= e($resource->icon()) ?>"></i>
                        <div>
                            <h3 class="panel-title"><?= e($formTitle) ?></h3>
                            <p class="panel-subtitle">Os campos com * são obrigatórios</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($resource->fields() as $field): ?>
                        <?php
                            $name = $field['name'];
                            $type = $field['type'] ?? 'text';
                            $value = $old[$name] ?? ($field['default'] ?? '');
                            if ($type === 'password') {
                                $value = '';
                            }
                            $error = $errors[$name] ?? null;
                            $colClass = $field['col'] ?? ($type === 'textarea' ? 'col-12' : 'col-md-6');
                            $icon = $field['icon'] ?? 'point';
                            $required = !empty($field['required']);
                        ?>

                        <?php if ($type === 'boolean'): ?>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="<?= e($name) ?>" name="<?= e($name) ?>" value="1" <?= (($value === '' || $value === null) ? 1 : $value) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= e($name) ?>"><i class="icon-base ti tabler-<?= e($icon) ?> me-1"></i><?= e($field['label']) ?></label>
                            </div>
                        </div>

                        <?php elseif ($type === 'select'): ?>
                        <div class="<?= e($colClass) ?>">
                            <label class="form-label" for="<?= e($name) ?>"><?= e($field['label']) ?><?= $required ? ' *' : '' ?></label>
                            <select class="select2 form-select <?= $error ? 'is-invalid' : '' ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" data-placeholder="Selecione...">
                                <option value=""></option>
                                <?php foreach (($selectOptions[$name] ?? []) as $optValue => $optLabel): ?>
                                <option value="<?= e((string) $optValue) ?>" <?= ((string) $value === (string) $optValue) ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
                            <?php if (!empty($field['help'])): ?><div class="form-text"><?= e($field['help']) ?></div><?php endif; ?>
                        </div>

                        <?php elseif ($type === 'textarea'): ?>
                        <div class="<?= e($colClass) ?>">
                            <label class="form-label" for="<?= e($name) ?>"><?= e($field['label']) ?><?= $required ? ' *' : '' ?></label>
                            <div class="input-group input-group-merge input-group-merge-textarea">
                                <span class="input-group-text"><i class="icon-base ti tabler-<?= e($icon) ?>"></i></span>
                                <textarea class="form-control <?= $error ? 'is-invalid' : '' ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" rows="3"<?= !empty($field['placeholder']) ? ' placeholder="' . e($field['placeholder']) . '"' : '' ?>><?= e($value) ?></textarea>
                            </div>
                            <?php if ($error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
                            <?php if (!empty($field['help'])): ?><div class="form-text"><?= e($field['help']) ?></div><?php endif; ?>
                        </div>

                        <?php else: ?>
                        <div class="<?= e($colClass) ?>">
                            <label class="form-label" for="<?= e($name) ?>"><?= e($field['label']) ?><?= $required ? ' *' : '' ?></label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-<?= e($icon) ?>"></i></span>
                                <input type="<?= e($type) ?>" class="form-control <?= $error ? 'is-invalid' : '' ?>"
                                       id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"
                                       <?= !empty($field['placeholder']) ? 'placeholder="' . e($field['placeholder']) . '"' : '' ?>
                                       <?= ($type !== 'number' && !empty($field['max'])) ? 'maxlength="' . (int) $field['max'] . '"' : '' ?>
                                       <?= ($type === 'number' && isset($field['min'])) ? 'min="' . (int) $field['min'] . '"' : '' ?>
                                       <?= ($type === 'number' && isset($field['max'])) ? 'max="' . (int) $field['max'] . '"' : '' ?>
                                       <?= ($type === 'number' && isset($field['step'])) ? 'step="' . e($field['step']) . '"' : '' ?>
                                       <?= !empty($field['mask']) ? 'data-mask="' . e($field['mask']) . '"' : '' ?>>
                            </div>
                            <?php if ($error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
                            <?php if (!empty($field['help'])): ?><div class="form-text"><?= e($field['help']) ?></div><?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php endforeach; ?>

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
