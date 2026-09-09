<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: listar.php");
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

try {
    $stmt = $conn->prepare("DELETE FROM agendamentos WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $_SESSION['mensagem'] = "Agendamento excluído com sucesso!";
    $_SESSION['mensagem_tipo'] = "success";
} catch (PDOException $e) {
    $_SESSION['mensagem'] = "Erro ao excluir agendamento: " . $e->getMessage();
    $_SESSION['mensagem_tipo'] = "danger";
}

header("Location: listar.php");
exit();
?>
