<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$stmt = $conn->prepare('SELECT nome, email, tipo, cpf, telefone FROM usuarios WHERE id = :id');
$stmt->execute([':id' => $_SESSION['usuario_id']]);
$usuario = $stmt->fetch();
if (!$usuario) { http_response_code(404); exit('Usuário não encontrado.'); }

$titulo_pagina = 'Meus dados';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-user-cog text-primary me-2"></i>Meus dados</h1>
        <span class="page-subtitle">Atualize suas informações de acesso</span>
    </div>
    <a href="../../index.php" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i>Voltar</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="perfil_processar.php">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                    <div class="panel-header">
                        <i class="icon-base ti tabler-user"></i>
                        <div>
                            <h3 class="panel-title">Informações pessoais</h3>
                            <p class="panel-subtitle">Estes dados são usados no seu acesso ao sistema</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-with-icon" for="nome"><i class="icon-base ti tabler-user"></i> Nome *</label>
                            <input class="form-control" id="nome" name="nome" maxlength="100" value="<?php echo esc($usuario['nome']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-with-icon" for="email"><i class="icon-base ti tabler-mail"></i> E-mail *</label>
                            <input class="form-control" type="email" id="email" name="email" maxlength="120" value="<?php echo esc($usuario['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Perfil</label>
                            <input class="form-control" value="<?php echo esc(ucfirst(str_replace('_', ' ', $usuario['tipo']))); ?>" disabled>
                        </div>
                        <div class="col-12">
                            <hr style="border-color: var(--border-color);">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-with-icon" for="senha"><i class="icon-base ti tabler-lock"></i> Nova senha</label>
                            <input class="form-control" type="password" id="senha" name="senha" minlength="8" placeholder="Deixe em branco para manter">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-with-icon" for="senha_confirmacao"><i class="icon-base ti tabler-shield-check"></i> Confirmar nova senha</label>
                            <input class="form-control" type="password" id="senha_confirmacao" name="senha_confirmacao" placeholder="Repita a nova senha">
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                            <a href="../../index.php" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i>Salvar alterações</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>