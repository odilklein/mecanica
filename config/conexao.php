<?php
// =============================================
// CONEXÃO COM O BANCO DE DADOS
// =============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mecanica');

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Função para escapar dados e evitar XSS
function limpar($dado) {
    return htmlspecialchars(trim($dado), ENT_QUOTES, 'UTF-8');
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