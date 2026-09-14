<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
validar_csrf();

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: listar.php");
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

try {
    $stmt = $conn->prepare("DELETE FROM veiculos WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'excluir', 'veiculo', $id, 'Veículo excluído');

    $_SESSION['mensagem'] = "Veículo excluído com sucesso!";
    $_SESSION['mensagem_tipo'] = "success";
} catch (PDOException $e) {
    $_SESSION['mensagem'] = "Erro ao excluir veículo: " . $e->getMessage();
    $_SESSION['mensagem_tipo'] = "danger";
}

header("Location: listar.php");
exit();
?>
