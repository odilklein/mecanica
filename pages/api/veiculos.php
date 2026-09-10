<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
if ($clienteId <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT id, marca, modelo, placa, ano, cor, km_atual FROM veiculos WHERE cliente_id = :cliente_id ORDER BY marca, modelo");
$stmt->bindParam(':cliente_id', $clienteId);
$stmt->execute();

$veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($veiculos);