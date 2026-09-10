<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria']);

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: listar.php");
    exit();
}

$peca_id = (int)($_POST['peca_id'] ?? 0);
$tipo = $_POST['tipo'] ?? 'entrada';
$quantidade = (int)($_POST['quantidade'] ?? 0);
$motivo = limpar($_POST['motivo'] ?? '');

if ($peca_id <= 0 || $quantidade <= 0) {
    $_SESSION['mensagem'] = "Selecione a peça e informe a quantidade.";
    $_SESSION['mensagem_tipo'] = "warning";
    header("Location: entrada.php");
    exit();
}

try {
    $conn->beginTransaction();

    // Consulta quantidade atual
    $stmt = $conn->prepare("SELECT quantidade FROM pecas WHERE id = :id FOR UPDATE");
    $stmt->bindParam(':id', $peca_id);
    $stmt->execute();
    $peca = $stmt->fetch();

    if (!$peca) {
        throw new Exception("Peça não encontrada.");
    }

    $nova_quantidade = 0;
    switch ($tipo) {
        case 'entrada':
            $nova_quantidade = $peca['quantidade'] + $quantidade;
            break;
        case 'saida':
            if ($quantidade > $peca['quantidade']) {
                throw new Exception("Estoque insuficiente para a saída de " . $quantidade . " unidades.");
            }
            $nova_quantidade = $peca['quantidade'] - $quantidade;
            break;
        case 'ajuste':
            $nova_quantidade = $quantidade;
            $quantidade_ajuste = $nova_quantidade - $peca['quantidade'];
            $motivo = $motivo . " (Ajuste: " . ($quantidade_ajuste >= 0 ? '+' : '') . $quantidade_ajuste . " unidades)";
            break;
    }

    // Atualizar estoque
    $stmt = $conn->prepare("UPDATE pecas SET quantidade = :quantidade WHERE id = :id");
    $stmt->bindParam(':quantidade', $nova_quantidade);
    $stmt->bindParam(':id', $peca_id);
    $stmt->execute();

    // Registrar movimentação
    $stmt = $conn->prepare("INSERT INTO movimentacoes_estoque (peca_id, tipo, quantidade, motivo) VALUES (:peca_id, :tipo, :quantidade, :motivo)");
    $stmt->bindParam(':peca_id', $peca_id);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->bindParam(':quantidade', $quantidade);
    $stmt->bindParam(':motivo', $motivo);
    $stmt->execute();

    $conn->commit();

    $_SESSION['mensagem'] = "Estoque atualizado com sucesso! Nova quantidade: " . $nova_quantidade . ".";
    $_SESSION['mensagem_tipo'] = "success";
    header("Location: entrada.php");
    exit();
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['mensagem'] = "Erro: " . $e->getMessage();
    $_SESSION['mensagem_tipo'] = "danger";
    header("Location: entrada.php");
    exit();
}
?>
