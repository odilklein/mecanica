<?php
// =============================================
// INICIO DA SESSÃO + PROTEÇÃO DE AUTENTICAÇÃO
// Deve ser incluído no topo de todas as páginas
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calcular caminho base relativo para redirecionamento correto
$caminho_base = '';
$dir_atual = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($dir_atual !== '' && $dir_atual !== '.') {
    $caminho_base = str_repeat('../', substr_count($dir_atual, '/'));
}

// Verificar autenticação (exceto páginas de login/logout/processar login)
$script_atual = basename($_SERVER['PHP_SELF'] ?? '');
$is_auth_page = in_array($script_atual, ['login.php', 'login_processar.php', 'logout.php']);

if (!$is_auth_page && !isset($_SESSION['usuario_id'])) {
    header("Location: " . $caminho_base . "pages/auth/login.php");
    exit();
}
?>