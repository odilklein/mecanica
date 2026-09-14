<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit();
}
validar_csrf();

$stmt = $conn->prepare('UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = :uid AND lida_em IS NULL');
$stmt->execute([':uid' => (int) $_SESSION['usuario_id']]);

$_SESSION['mensagem'] = 'Todas as notificações foram marcadas como lidas.';
$_SESSION['mensagem_tipo'] = 'success';
header('Location: listar.php');
exit();