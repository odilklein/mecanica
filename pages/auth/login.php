<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/empresa.php';
require_once __DIR__ . '/../../config/turnstile.php';

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

$erro = isset($_GET['erro']) ? $_GET['erro'] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR" dir="ltr" data-skin="default" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#b8270f">
    <title>Login | <?php echo EMPRESA_NOME; ?></title>

    <link rel="icon" href="data:,">

    <!-- Tema aplicado antes da renderização (evita flash). Padrão: claro. -->
    <script>
        (function () {
            try {
                var p = localStorage.getItem('mecanica-theme');
                if (p !== 'dark' && p !== 'system') { p = 'light'; }
                var t = (p === 'system')
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : p;
                document.documentElement.setAttribute('data-bs-theme', t);
                document.documentElement.style.colorScheme = t;
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../../assets/vendor/fonts/iconify-icons.css">
    <link rel="stylesheet" href="../../assets/vendor/css/core.css">
    <link rel="stylesheet" href="../../assets/css/demo.css">
    <link rel="stylesheet" href="../../assets/vendor/css/pages/page-auth.css">
    <link rel="stylesheet" href="../../css/mecanica.css?v=<?php echo @filemtime(__DIR__ . '/../../css/mecanica.css') ?: time(); ?>">

    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body>
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner py-6">
                <div class="card">
                    <div class="card-body">
                        <!-- Logo -->
                        <div class="app-brand justify-content-center mb-6">
                            <a href="../../index.php" class="app-brand-link gap-2">
                                <span class="app-brand-logo">
                                    <span class="brand-icon"><i class="icon-base ti tabler-tool"></i></span>
                                </span>
                                <span class="app-brand-text text-heading fw-bold"><?php echo htmlspecialchars(EMPRESA_NOME, ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        </div>
                        <!-- /Logo -->

                        <h4 class="mb-1">Bem-vindo! 👋</h4>
                        <p class="mb-6">Entre na sua conta para acessar o sistema.</p>

                        <?php if ($erro) : ?>
                            <div class="alert alert-danger alert-dismissible" role="alert">
                                <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endif; ?>

                        <form id="formLogin" method="POST" action="login_processar.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="mb-6 form-control-validation">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="seu@email.com" autofocus required>
                            </div>

                            <div class="mb-6 form-password-toggle form-control-validation">
                                <label class="form-label" for="senha">Senha</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" id="senha" class="form-control" name="senha" placeholder="············" aria-describedby="senha" required>
                                    <span class="input-group-text cursor-pointer" id="toggleSenha"><i class="icon-base ti tabler-eye-off"></i></span>
                                </div>
                            </div>

                            <?php if (turnstile_configurado()): ?>
                            <div class="mb-6">
                                <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(turnstile_site_key(), ENT_QUOTES, 'UTF-8'); ?>" data-action="login" data-theme="auto"></div>
                            </div>
                            <?php endif; ?>

                            <div class="mb-6">
                                <button class="btn btn-primary d-grid w-100" type="submit">Entrar</button>
                            </div>
                        </form>

                        <div class="divider my-6">
                            <div class="divider-text"><?php echo htmlspecialchars(EMPRESA_SLOGAN, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <p class="text-center mb-0">
                            <small class="text-body-secondary d-block"><?php echo htmlspecialchars(EMPRESA_ENDERECO, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(EMPRESA_CIDADE, ENT_QUOTES, 'UTF-8'); ?></small>
                            <small class="text-body-secondary d-block"><?php echo htmlspecialchars(EMPRESA_TELEFONE, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(EMPRESA_EMAIL, ENT_QUOTES, 'UTF-8'); ?></small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script>
        document.getElementById('toggleSenha').addEventListener('click', function () {
            var senha = document.getElementById('senha');
            var icon = this.querySelector('i');
            if (senha.type === 'password') {
                senha.type = 'text';
                icon.className = 'icon-base ti tabler-eye';
            } else {
                senha.type = 'password';
                icon.className = 'icon-base ti tabler-eye-off';
            }
        });
    </script>
</body>
</html>
