<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/sessao.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit();
}

try {
    if (isset($_GET['cliente_id'])) {
        $stmt = $conn->prepare("SELECT id, placa, marca, modelo, ano, cor FROM veiculos WHERE cliente_id = :cliente_id ORDER BY marca, modelo");
        $stmt->bindParam(':cliente_id', $_GET['cliente_id']);
        $stmt->execute();
        echo json_encode($stmt->fetchAll());
        exit();
    }

    if (isset($_GET['placa'])) {
        $stmt = $conn->prepare("SELECT v.*, c.nome as cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id WHERE v.placa = :placa");
        $stmt->bindParam(':placa', strtoupper($_GET['placa']));
        $stmt->execute();
        echo json_encode($stmt->fetch());
        exit();
    }

    $stmt = $conn->query("SELECT v.*, c.nome as cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id ORDER BY v.marca, v.modelo");
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível carregar os veículos.']);
}
?>
