<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/fipe.php';

exigir_papel(['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit();
}
validar_csrf();

set_time_limit(0);

try {
    $resumo = fipe_sincronizar($conn);
    $_SESSION['mensagem'] = sprintf(
        'Catálogo FIPE sincronizado: %d marcas, %d modelos-base e %d versões.',
        $resumo['marcas'],
        $resumo['bases'],
        $resumo['modelos']
    );
    $_SESSION['mensagem_tipo'] = 'success';
} catch (Throwable $e) {
    error_log('Sync FIPE (web): ' . $e->getMessage());
    $_SESSION['mensagem'] = 'Não foi possível sincronizar o catálogo FIPE. Tente novamente ou rode scripts/sync_fipe.php no terminal.';
    $_SESSION['mensagem_tipo'] = 'danger';
}

header('Location: listar.php');
exit();
