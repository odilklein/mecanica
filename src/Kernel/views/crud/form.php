<?php
$labels = $resource->labels();
$path = $resource->path();
$isEdit = $record !== null;
$action = $isEdit ? $path . '/' . (int) $record['id'] : $path;
?>
<div class="page-header">
    <div>
        <h1 class="mb-1"><?= e($title) ?></h1>
    </div>
    <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left me-1"></i>Voltar</a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="<?= e($action) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                    <?php if (!empty($errors['_form'])): ?>
                    <div class="alert alert-danger" role="alert"><?= e($errors['_form']) ?></div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <?php foreach ($resource->fields() as $field): ?>
                        <?php
                            $name = $field['name'];
                            $type = $field['type'] ?? 'text';
                            $value = $old[$name] ?? ($field['default'] ?? '');
                            $error = $errors[$name] ?? null;
                            $colClass = $field['col'] ?? ($type === 'textarea' ? 'col-12' : 'col-md-6');
                        ?>

                        <?php if ($type === 'boolean'): ?>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="<?= e($name) ?>" name="<?= e($name) ?>" value="1" <?= ($value ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= e($name) ?>"><?= e($field['label']) ?></label>
                            </div>
                        </div>

                        <?php elseif ($type === 'textarea'): ?>
                        <div class="col-12">
                            <label class="form-label" for="<?= e($name) ?>"><?= e($field['label']) ?></label>
                            <textarea class="form-control <?= $error ? 'is-invalid' : '' ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" rows="3"><?= e($value) ?></textarea>
                            <?php if ($error): ?><div class="invalid-feedback"><?= e($error) ?></div><?php endif; ?>
                        </div>

                        <?php else: ?>
                        <div class="<?= e($colClass) ?>">
                            <label class="form-label" for="<?= e($name) ?>"><?= e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?></label>
                            <input type="<?= e($type) ?>" class="form-control <?= $error ? 'is-invalid' : '' ?>"
                                   id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"
                                   <?= !empty($field['max']) ? 'maxlength="' . (int) $field['max'] . '"' : '' ?>
                                   <?= !empty($field['mask']) ? 'data-mask="' . e($field['mask']) . '"' : '' ?>>
                            <?php if ($error): ?><div class="invalid-feedback"><?= e($error) ?></div><?php endif; ?>
                            <?php if (!empty($field['help'])): ?><div class="form-text"><?= e($field['help']) ?></div><?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php endforeach; ?>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                            <a href="<?= e($path) ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy me-1"></i>Salvar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
