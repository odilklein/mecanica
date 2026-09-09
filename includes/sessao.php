<?php
// =============================================
// INICIO DA SESSÃO + PROTEÇÃO DE AUTENTICAÇÃO
// Deve ser incluído no topo de todas as páginas
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax'
    ]);
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function validar_csrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Requisição inválida.');
    }
}

function exigir_papel($papeis) {
    $papeis = (array) $papeis;
    if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], $papeis, true)) {
        http_response_code(403);
        exit('Acesso não autorizado.');
    }
}

function tem_papel($papeis) {
    return isset($_SESSION['usuario_tipo']) && in_array($_SESSION['usuario_tipo'], (array) $papeis, true);
}
?>