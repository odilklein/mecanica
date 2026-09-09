<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$acao = $_GET['acao'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$cliente_id = (int)($_POST['cliente_id'] ?? 0);
$veiculo_id = (int)($_POST['veiculo_id'] ?? 0);
$servico_id = (int)($_POST['servico_id'] ?? 0);
$data_agendamento = $_POST['data_agendamento'] ?? '';
$hora = $_POST['hora'] ?? '';
$status = $_POST['status'] ?? 'agendado';
$observacoes = limpar($_POST['observacoes'] ?? '');

try {
    if ($acao == 'cadastrar') {
        if ($cliente_id <= 0 || $servico_id <= 0 || empty($data_agendamento) || empty($hora)) {
            $_SESSION['mensagem'] = "Preencha todos os campos obrigatórios.";
            $_SESSION['mensagem_tipo'] = "warning";
            header("Location: cadastrar.php");
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO agendamentos (cliente_id, veiculo_id, servico_id, data_agendamento, hora, status, observacoes) VALUES (:cliente_id, :veiculo_id, :servico_id, :data_agendamento, :hora, :status, :observacoes)");
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':veiculo_id', $veiculo_id);
        $stmt->bindParam(':servico_id', $servico_id);
        $stmt->bindParam(':data_agendamento', $data_agendamento);
        $stmt->bindParam(':hora', $hora);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->execute();

        $_SESSION['mensagem'] = "Agendamento cadastrado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
    } elseif ($acao == 'editar') {
        $stmt = $conn->prepare("UPDATE agendamentos SET cliente_id = :cliente_id, veiculo_id = :veiculo_id, servico_id = :servico_id, data_agendamento = :data_agendamento, hora = :hora, status = :status, observacoes = :observacoes WHERE id = :id");
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':veiculo_id', $veiculo_id);
        $stmt->bindParam(':servico_id', $servico_id);
        $stmt->bindParam(':data_agendamento', $data_agendamento);
        $stmt->bindParam(':hora', $hora);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensagem'] = "Agendamento atualizado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
    } elseif ($acao == 'mudar_status') {
        $novo_status = $_POST['status'] ?? 'concluido';
        $stmt = $conn->prepare("UPDATE agendamentos SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $novo_status);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensagem'] = "Status do agendamento atualizado para '" . ucfirst($novo_status) . "'!";
        $_SESSION['mensagem_tipo'] = "success";
    }

    header("Location: listar.php");
    exit();
} catch (PDOException $e) {
    $_SESSION['mensagem'] = "Erro ao processar: " . $e->getMessage();
    $_SESSION['mensagem_tipo'] = "danger";
    if ($acao == 'editar') {
        header("Location: editar.php?id=" . $id);
    } else {
        header("Location: cadastrar.php");
    }
    exit();
}
?>
