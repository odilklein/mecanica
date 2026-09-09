<?php
// =============================================
// INICIO DA SESSÃO + PROTEÇÃO DE AUTENTICAÇÃO
// Deve ser incluído no topo de todas as páginas
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/base_url.php';

// Verificar autenticação (exceto páginas de login/logout/processar login)
$script_atual = basename($_SERVER['PHP_SELF'] ?? '');
$is_auth_page = in_array($script_atual, ['login.php', 'login_processar.php', 'logout.php']);

if (!$is_auth_page && !isset($_SESSION['usuario_id'])) {
    header("Location: " . $caminho_base . "/pages/auth/login.php");
    exit();
}
?>