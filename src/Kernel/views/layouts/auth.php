<!DOCTYPE html>
<html lang="pt-BR" dir="ltr" data-skin="default" data-bs-theme="light" data-assets-path="/assets/" data-template="vertical-menu-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="<?= e(setting('theme_color', '#b8270f')) ?>">
    <script>
        (function () {
            try {
                var pref = localStorage.getItem('mecanica-theme') || 'system';
                var resolved = pref === 'system'
                    ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : pref;
                document.documentElement.setAttribute('data-bs-theme', resolved);
                document.documentElement.setAttribute('data-theme-pref', pref);
                var meta = document.querySelector('meta[name="theme-color"]');
                if (meta) {
                    meta.dataset.lightColor = meta.dataset.lightColor || meta.getAttribute('content') || '#b8270f';
                    meta.setAttribute('content', resolved === 'dark' ? '#1f1f1f' : meta.dataset.lightColor);
                }
            } catch (e) {}
        })();
    </script>
    <title>Login | <?= e(setting('seo_title', 'Mecânica')) ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon/favicon-32x32.png">
    <link rel="shortcut icon" href="/assets/img/favicon/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/vendor/fonts/iconify-icons.css">
    <link rel="stylesheet" href="/assets/vendor/css/core.css">
    <link rel="stylesheet" href="/assets/css/demo.css">
    <link rel="stylesheet" href="/css/mecanica.css">
    <link rel="stylesheet" href="<?= e(asset('/css/auth.css')) ?>">

    <?= theme_css_inline(setting('theme_color', '#b8270f')) ?>

    <script src="/assets/vendor/js/helpers.js"></script>
    <script src="<?= e(asset('/assets/js/config.js')) ?>"></script>
</head>
<body class="auth-page">
    <div class="auth-shell">
        <aside class="auth-showcase">
            <div class="auth-showcase-content">
                <a class="auth-brand" href="/">
                    <span class="brand-icon"><i class="icon-base ti tabler-tool"></i></span>
                    <span><?= e(setting('company_name', 'Mecânica') ?: 'Mecânica') ?></span>
                </a>

                <div class="auth-showcase-copy">
                    <span class="auth-eyebrow">Gestão da oficina</span>
                    <h1>Do primeiro contato à entrega do veículo.</h1>
                    <p>Clientes, veículos, orçamentos e ordens de serviço em um único fluxo — com histórico completo de cada atendimento.</p>
                </div>

                <ul class="auth-features">
                    <li>
                        <span class="feat-icon"><i class="icon-base ti tabler-clipboard-text"></i></span>
                        <span><strong>Ordens de serviço</strong>Acompanhe cada etapa, da queixa à entrega.</span>
                    </li>
                    <li>
                        <span class="feat-icon"><i class="icon-base ti tabler-receipt-2"></i></span>
                        <span><strong>Orçamentos e peças</strong>Valores, aprovação do cliente e pedidos.</span>
                    </li>
                    <li>
                        <span class="feat-icon"><i class="icon-base ti tabler-shield-check"></i></span>
                        <span><strong>Acesso sob controle</strong>Permissões por perfil e registro de auditoria.</span>
                    </li>
                </ul>

                <div class="auth-showcase-foot">
                    Sistema de gestão para oficinas mecânicas
                </div>
            </div>
        </aside>

        <main class="auth-panel">
            <div class="card auth-card">
                <div class="card-body">
                    <?= $content ?? '' ?>
                </div>
            </div>
            <p class="auth-copyright">
                &copy; <?= date('Y') ?> <?= e(setting('company_name', 'Mecânica') ?: 'Mecânica') ?> — todos os direitos reservados.
            </p>
        </main>
    </div>

    <script src="/assets/vendor/libs/jquery/jquery.js"></script>
    <script src="/assets/vendor/libs/popper/popper.js"></script>
    <script src="/assets/vendor/js/bootstrap.js"></script>
    <script src="/assets/js/main.js"></script>
    <script>
        (function () {
            var toggle = document.getElementById('togglePassword');
            var input = document.getElementById('password');
            if (!toggle || !input) return;

            toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';

                var icon = toggle.querySelector('i');
                if (icon) {
                    icon.className = 'icon-base ti ' + (show ? 'tabler-eye' : 'tabler-eye-off');
                }

                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                toggle.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
                input.focus({ preventScroll: true });
            });
        })();
    </script>
</body>
</html>
