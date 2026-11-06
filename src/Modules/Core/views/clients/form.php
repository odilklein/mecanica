<?php $this->layout('core::layouts/app') ?>

<div class="page-header">
    <div>
        <h1 class="mb-1"><?= e($title) ?></h1>
    </div>
    <a href="/clients" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left me-1"></i>Voltar</a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="<?= $client ? '/clients/' . (int) $client['id'] : '/clients' ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Nome *</label>
                            <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" maxlength="120">
                            <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cpf">CPF</label>
                            <input class="form-control <?= isset($errors['cpf']) ? 'is-invalid' : '' ?>" id="cpf" name="cpf" value="<?= e($old['cpf'] ?? '') ?>" placeholder="000.000.000-00" maxlength="14">
                            <?php if (isset($errors['cpf'])): ?><div class="invalid-feedback"><?= e($errors['cpf']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="phone">Telefone</label>
                            <input class="form-control" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" placeholder="(00) 00000-0000" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" maxlength="120">
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label" for="address">Endereço</label>
                            <input class="form-control" id="address" name="address" value="<?= e($old['address'] ?? '') ?>" maxlength="255">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="city">Cidade</label>
                            <input class="form-control" id="city" name="city" value="<?= e($old['city'] ?? '') ?>" maxlength="80">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Observações</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= e($old['notes'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= ($old['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Ativo</label>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                            <a href="/clients" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy me-1"></i>Salvar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
