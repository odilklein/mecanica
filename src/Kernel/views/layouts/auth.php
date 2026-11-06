<!DOCTYPE html>
<html lang="pt-BR" dir="ltr" data-skin="default" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#b8270f">
    <title><?= e($title ?? 'Login') ?> | Mecânica</title>
    <link rel="icon" href="/assets/img/favicon/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/vendor/fonts/iconify-icons.css">
    <link rel="stylesheet" href="/assets/vendor/css/core.css">
    <link rel="stylesheet" href="/assets/css/demo.css">
    <link rel="stylesheet" href="/assets/vendor/css/pages/page-auth.css">

    <script src="/assets/vendor/js/helpers.js"></script>
</head>
<body>
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner py-6">
                <div class="card">
                    <div class="card-body">
                        <?= $content ?? '' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/vendor/libs/jquery/jquery.js"></script>
    <script src="/assets/vendor/libs/popper/popper.js"></script>
    <script src="/assets/vendor/js/bootstrap.js"></script>
    <script src="/assets/js/main.js"></script>
    <script>
        document.getElementById('togglePassword')?.addEventListener('click', function () {
            var input = document.getElementById('password');
            var icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'icon-base ti tabler-eye';
            } else {
                input.type = 'password';
                icon.className = 'icon-base ti tabler-eye-off';
            }
        });
    </script>
</body>
</html>
