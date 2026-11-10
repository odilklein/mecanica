<div class="app-brand justify-content-center mb-6">
    <a href="/" class="app-brand-link gap-2">
        <span class="app-brand-logo demo">
            <span class="brand-icon"><i class="icon-base ti tabler-tool"></i></span>
        </span>
        <span class="app-brand-text demo menu-text fw-bold">Mecânica</span>
    </a>
</div>

<h4 class="mb-1">Bem-vindo! 👋</h4>
<p class="mb-6">Entre na sua conta para acessar o sistema.</p>

<?php if (!empty($error)): ?>
<div class="alert alert-danger alert-dismissible" role="alert">
    <?= e($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
</div>
<?php endif; ?>

<form method="POST" action="/login">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="mb-6 form-control-validation">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="seu@email.com" autofocus required>
    </div>

    <div class="mb-6 form-password-toggle form-control-validation">
        <label class="form-label" for="password">Senha</label>
        <div class="input-group input-group-merge">
            <input type="password" id="password" class="form-control" name="password" placeholder="············" required>
            <span class="input-group-text cursor-pointer" id="togglePassword"><i class="icon-base ti tabler-eye-off"></i></span>
        </div>
    </div>

    <?php if (!empty($turnstileEnabled)): ?>
    <div class="mb-6">
        <div class="cf-turnstile" data-sitekey="<?= e($turnstileSiteKey ?? '') ?>" data-action="login"></div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    </div>
    <?php endif; ?>

    <div class="mb-6">
        <button class="btn btn-primary d-grid w-100" type="submit">Entrar</button>
    </div>
</form>
