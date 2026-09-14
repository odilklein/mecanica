<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: ../../index.php");
	exit();
}

validar_csrf();
if (isset($_SESSION['usuario_id'])) {
	registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'logout', 'usuario', (int) $_SESSION['usuario_id'], 'Logout realizado');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header("Location: login.php");
exit();
?>
