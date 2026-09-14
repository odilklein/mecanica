<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';
exigir_papel(['admin']);

$arquivo = basename($_GET['arquivo'] ?? '');
$caminho = __DIR__ . '/../../uploads/backups/' . $arquivo;
if ($arquivo === '' || !is_file($caminho) || strncmp($arquivo, 'backup_', 7) !== 0 || substr($arquivo, -4) !== '.sql') {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'backup_baixar', 'banco', null, 'Backup baixado', ['arquivo' => $arquivo]);

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Content-Length: ' . filesize($caminho));
readfile($caminho);
exit();