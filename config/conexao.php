<?php
// =============================================
// CONEXÃO COM O BANCO DE DADOS
// =============================================

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'mecanica';

try {
    $conn = new PDO("mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    die("Não foi possível conectar ao banco de dados.");
}

// Função para escapar dados e evitar XSS
function limpar($dado) {
    return trim((string) $dado);
}

function esc($dado) {
    return htmlspecialchars((string) $dado, ENT_QUOTES, 'UTF-8');
}

// Função para formatar moeda
function formatar_moeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para verificar se usuário está logado
function verificar_sessao() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: ../pages/auth/login.php");
        exit();
    }
}
?>