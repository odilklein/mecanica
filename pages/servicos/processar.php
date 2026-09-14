<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

exigir_papel(['admin', 'secretaria']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit();
}
validar_csrf();

$acao = $_GET['acao'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$nome = limpar($_POST['nome'] ?? '');
$descricao = limpar($_POST['descricao'] ?? '');
$preco_mao_obra = str_replace(['R$', '.', ','], ['', '', '.'], limpar($_POST['preco_mao_obra'] ?? '0'));
$tempo_estimado = (int)($_POST['tempo_estimado'] ?? 0);
$ativo = (int)($_POST['ativo'] ?? 1);

if (empty($nome)) {
    $_SESSION['mensagem'] = "O nome do serviço é obrigatório.";
    $_SESSION['mensagem_tipo'] = "warning";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}

try {
    if ($acao == 'cadastrar') {
        $stmt = $conn->prepare("INSERT INTO servicos (nome, descricao, preco_mao_obra, tempo_estimado, ativo) VALUES (:nome, :descricao, :preco_mao_obra, :tempo_estimado, :ativo)");
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':preco_mao_obra', $preco_mao_obra);
        $stmt->bindParam(':tempo_estimado', $tempo_estimado);
        $stmt->bindParam(':ativo', $ativo);
        $stmt->execute();

        $_SESSION['mensagem'] = "Serviço cadastrado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
    } elseif ($acao == 'editar') {
        $stmt = $conn->prepare("UPDATE servicos SET nome = :nome, descricao = :descricao, preco_mao_obra = :preco_mao_obra, tempo_estimado = :tempo_estimado, ativo = :ativo WHERE id = :id");
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':preco_mao_obra', $preco_mao_obra);
        $stmt->bindParam(':tempo_estimado', $tempo_estimado);
        $stmt->bindParam(':ativo', $ativo);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensagem'] = "Serviço atualizado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
    }

    header("Location: listar.php");
    exit();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['mensagem'] = "Não foi possível salvar o serviço.";
    $_SESSION['mensagem_tipo'] = "danger";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}
?>
