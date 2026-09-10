<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/empresa.php';

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

$erro = isset($_GET['erro']) ? $_GET['erro'] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#bd1804">
    <title>Login | <?php echo EMPRESA_NOME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../css/style.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="auth-bg">
    <div class="auth-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-lg border-0 auth-card">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <div class="auth-icon">
                                    <i data-lucide="wrench"></i>
                                </div>
                                <h2 class="fw-bold mt-4" style="font-family: var(--font-main);"><?php echo EMPRESA_NOME; ?></h2>
                                <p class="text-muted" style="font-size:0.85rem;"><?php echo EMPRESA_SLOGAN; ?></p>
                            </div>

                            <?php if ($erro) : ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i data-lucide="circle-alert" class="me-2"></i><?php echo htmlspecialchars($erro); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="login_processar.php" id="formLogin">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i data-lucide="mail"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="seu@email.com" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="senha" class="form-label">Senha</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i data-lucide="lock"></i></span>
                                        <input type="password" class="form-control" id="senha" name="senha" placeholder="Digite sua senha" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleSenha">
                                            <i data-lucide="eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg fw-bold" style="background-color:#bd1804; border-color:#bd1804;">
                                        <i data-lucide="log-in" class="me-2"></i>Entrar
                                    </button>
                                </div>
                            </form>

                            <hr style="border-color: var(--border-color);">

                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size:0.75rem;"><?php echo EMPRESA_ENDERECO; ?> · <?php echo EMPRESA_CIDADE; ?></small>
                                <small class="text-muted d-block" style="font-size:0.75rem;"><?php echo EMPRESA_TELEFONE; ?> · <?php echo EMPRESA_EMAIL; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();

        document.getElementById('toggleSenha').addEventListener('click', function() {
            const senha = document.getElementById('senha');
            const icon = document.getElementById('toggleSenha').querySelector('svg');
            if (senha.type === 'password') {
                senha.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                senha.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        });
    </script>
</body>
</html>