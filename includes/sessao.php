<?php
// =============================================
// INICIO DA SESSÃO + PROTEÇÃO DE AUTENTICAÇÃO
// Deve ser incluído no topo de todas as páginas
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calcular caminho base relativo até a raiz do projeto para redirecionamento
$pagina_dir_fs = str_replace('\\', '/', realpath(dirname($_SERVER['SCRIPT_FILENAME'])));
$raiz_fs = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$dirs_pagina = explode('/', trim($pagina_dir_fs, '/'));
$dirs_raiz = explode('/', trim($raiz_fs, '/'));
$comum = 0;
while ($comum < count($dirs_pagina) && $comum < count($dirs_raiz) && $dirs_pagina[$comum] === $dirs_raiz[$comum]) {
    $comum++;
}
$caminho_base = str_repeat('../', max(0, count($dirs_pagina) - $comum));

// Verificar autenticação (exceto páginas de login/logout/processar login)
$script_atual = basename($_SERVER['PHP_SELF'] ?? '');
$is_auth_page = in_array($script_atual, ['login.php', 'login_processar.php', 'logout.php']);

if (!$is_auth_page && !isset($_SESSION['usuario_id'])) {
    header("Location: " . $caminho_base . "pages/auth/login.php");
    exit();
}
?>