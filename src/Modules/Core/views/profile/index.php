<?php $profile = $profile ?? []; ?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-user-cog"></i>Meus dados</h1>
        <span class="page-subtitle">Atualize suas informações de acesso</span>
    </div>
    <div class="page-header-actions">
        <a href="/" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="/profile">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                    <div class="panel-header">
                        <i class="icon-base ti tabler-user"></i>
                        <div>
                            <h3 class="panel-title">Informações pessoais</h3>
                            <p class="panel-subtitle">Estes dados são usados no seu acesso ao sistema</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Nome *</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-user"></i></span>
                                <input type="text" class="form-control" id="name" name="name" value="<?= e($profile['name'] ?? '') ?>" maxlength="100" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">E-mail *</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-mail"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?= e($profile['email'] ?? '') ?>" maxlength="120" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="phone">Telefone</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-phone"></i></span>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= e($profile['phone'] ?? '') ?>" maxlength="20" data-mask="phone" placeholder="(11) 98888-7777">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Perfil</label>
                            <input type="text" class="form-control" value="<?= e(roleLabel($profile['role'] ?? null)) ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="job_title">Cargo</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-briefcase"></i></span>
                                <input type="text" class="form-control" id="job_title" name="job_title" value="<?= e($profile['job_title'] ?? '') ?>" disabled>
                            </div>
                        </div>

                        <div class="col-12"><hr></div>

                        <div class="col-md-4">
                            <label class="form-label" for="current_password">Senha atual</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-key"></i></span>
                                <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Obrigatória para trocar" autocomplete="current-password">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password">Nova senha</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Deixe em branco para manter" minlength="8" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_confirmation">Confirmar nova senha</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="icon-base ti tabler-shield-check"></i></span>
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Repita a nova senha" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                            <a href="/" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i> Salvar alterações</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
