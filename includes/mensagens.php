<?php if (isset($_SESSION['mensagem'])) : ?>
    <?php $tipo = $_SESSION['mensagem_tipo'] ?? 'success'; ?>
    <div class="alert alert-<?php echo $tipo; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['mensagem']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php unset($_SESSION['mensagem'], $_SESSION['mensagem_tipo']); ?>
<?php endif; ?>
