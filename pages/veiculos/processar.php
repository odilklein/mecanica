<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: listar.php'); exit(); }
validar_csrf();

$acao = $_GET['acao'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$cliente_id = (int)($_POST['cliente_id'] ?? 0);
$placa = strtoupper(limpar($_POST['placa'] ?? ''));
$marca = limpar($_POST['marca'] ?? '');
$modelo = limpar($_POST['modelo'] ?? '');
$ano = (int)($_POST['ano'] ?? 0);
$cor = limpar($_POST['cor'] ?? '');
$km_atual = (int)($_POST['km_atual'] ?? 0);
$chassi = limpar($_POST['chassi'] ?? '');

if ($cliente_id <= 0 || empty($placa) || empty($marca) || empty($modelo)) {
    $_SESSION['mensagem'] = "Preencha todos os campos obrigatórios.";
    $_SESSION['mensagem_tipo'] = "warning";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}

if (!validar_placa($placa)) {
    $_SESSION['mensagem'] = "Placa inválida. Use o formato ABC-1234 ou ABC1D23 (Mercosul).";
    $_SESSION['mensagem_tipo'] = "warning";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}

if ($ano < 1886 || $ano > (int) date('Y') + 1) {
    $_SESSION['mensagem'] = "Ano do veículo inválido.";
    $_SESSION['mensagem_tipo'] = "warning";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}

try {
    if ($acao == 'cadastrar') {
        // Verificar se placa já existe
        $stmt = $conn->prepare("SELECT id FROM veiculos WHERE placa = :placa");
        $stmt->bindParam(':placa', $placa);
        $stmt->execute();
        if ($stmt->fetch()) {
            $_SESSION['mensagem'] = "Já existe um veículo com esta placa.";
            $_SESSION['mensagem_tipo'] = "warning";
            header("Location: cadastrar.php");
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO veiculos (cliente_id, placa, marca, modelo, ano, cor, km_atual, chassi) VALUES (:cliente_id, :placa, :marca, :modelo, :ano, :cor, :km_atual, :chassi)");
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':placa', $placa);
        $stmt->bindParam(':marca', $marca);
        $stmt->bindParam(':modelo', $modelo);
        $stmt->bindParam(':ano', $ano);
        $stmt->bindParam(':cor', $cor);
        $stmt->bindParam(':km_atual', $km_atual);
        $stmt->bindParam(':chassi', $chassi);
        $stmt->execute();

        $_SESSION['mensagem'] = "Veículo cadastrado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'veiculo', (int) $conn->lastInsertId(), 'Veículo cadastrado', ['placa' => $placa, 'cliente_id' => $cliente_id]);
    } elseif ($acao == 'editar') {
        $stmt = $conn->prepare("UPDATE veiculos SET cliente_id = :cliente_id, placa = :placa, marca = :marca, modelo = :modelo, ano = :ano, cor = :cor, km_atual = :km_atual, chassi = :chassi WHERE id = :id");
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':placa', $placa);
        $stmt->bindParam(':marca', $marca);
        $stmt->bindParam(':modelo', $modelo);
        $stmt->bindParam(':ano', $ano);
        $stmt->bindParam(':cor', $cor);
        $stmt->bindParam(':km_atual', $km_atual);
        $stmt->bindParam(':chassi', $chassi);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensagem'] = "Veículo atualizado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'veiculo', $id, 'Veículo atualizado', ['placa' => $placa, 'cliente_id' => $cliente_id]);
    }

    header("Location: listar.php");
    exit();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['mensagem'] = "Não foi possível salvar o veículo.";
    $_SESSION['mensagem_tipo'] = "danger";
    if ($acao == 'editar') {
        header("Location: editar.php?id=" . $id);
    } else {
        header("Location: cadastrar.php");
    }
    exit();
}
?>
