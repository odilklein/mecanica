<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../includes/fipe.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit();
}

$recurso = $_GET['recurso'] ?? '';

try {
    switch ($recurso) {
        case 'marcas':
            $stmt = $conn->query("SELECT id, nome_exibicao FROM fipe_marcas ORDER BY nome_exibicao");
            echo json_encode($stmt->fetchAll());
            exit();

        case 'modelos_base':
            $marcaId = (int) ($_GET['marca_id'] ?? 0);
            if ($marcaId <= 0) {
                echo json_encode([]);
                exit();
            }
            $stmt = $conn->prepare("SELECT id, nome_base FROM fipe_modelos_base WHERE marca_id = :m ORDER BY nome_base");
            $stmt->execute([':m' => $marcaId]);
            echo json_encode($stmt->fetchAll());
            exit();

        case 'versoes':
            $baseId = (int) ($_GET['base_id'] ?? 0);
            if ($baseId <= 0) {
                echo json_encode([]);
                exit();
            }
            $stmt = $conn->prepare("SELECT id, nome_versao, fipe_codigo FROM fipe_modelos WHERE base_id = :b ORDER BY nome_versao");
            $stmt->execute([':b' => $baseId]);
            echo json_encode($stmt->fetchAll());
            exit();

        case 'valor':
            $modeloId = (int) ($_GET['modelo_id'] ?? 0);
            $ano = (int) ($_GET['ano'] ?? 0);
            $dados = fipe_valor_por_modelo_ano($conn, $modeloId, $ano);
            if (!$dados) {
                echo json_encode(['valor' => null]);
                exit();
            }
            echo json_encode([
                'valor' => $dados['valor'],
                'valor_formatado' => formatar_moeda($dados['valor']),
                'mes_referencia' => $dados['mes_referencia'],
                'combustivel' => $dados['combustivel'],
            ]);
            exit();

        default:
            http_response_code(400);
            echo json_encode(['erro' => 'Recurso inválido']);
            exit();
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível consultar a FIPE.']);
}
