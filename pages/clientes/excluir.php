<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';
require_once __DIR__ . '/../../includes/respostas.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: listar.php");
    exit();
}
validar_csrf();

$id = (int) ($_POST['id'] ?? 0);

try {
    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'excluir', 'cliente', $id, 'Cliente excluído');

    redirecionar_com_mensagem('listar.php', 'Cliente excluído com sucesso!');
} catch (PDOException $e) {
    redirecionar_com_mensagem('listar.php', mensagem_de_erro($e, 'Não foi possível excluir o cliente.'), 'danger');
}
