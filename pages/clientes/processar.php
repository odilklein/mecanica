<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: listar.php'); exit(); }
validar_csrf();

// Recebe dados do formulário
$acao = $_GET['acao'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$nome = limpar($_POST['nome'] ?? '');
$cpf = limpar($_POST['cpf'] ?? '');
$telefone = limpar($_POST['telefone'] ?? '');
$email = limpar($_POST['email'] ?? '');
$endereco = limpar($_POST['endereco'] ?? '');
$cidade = limpar($_POST['cidade'] ?? '');
$observacoes = limpar($_POST['observacoes'] ?? '');

// Validações
if (empty($nome)) {
    $_SESSION['mensagem'] = "O nome do cliente é obrigatório.";
    $_SESSION['mensagem_tipo'] = "warning";
    header("Location: cadastrar.php");
    exit();
}

try {
    if ($acao == 'cadastrar') {
        // Verificar se CPF já existe
        if (!empty($cpf)) {
            $stmt = $conn->prepare("SELECT id FROM clientes WHERE cpf = :cpf");
            $stmt->bindParam(':cpf', $cpf);
            $stmt->execute();
            if ($stmt->fetch()) {
                $_SESSION['mensagem'] = "Já existe um cliente com este CPF.";
                $_SESSION['mensagem_tipo'] = "warning";
                header("Location: cadastrar.php");
                exit();
            }
        }

        $stmt = $conn->prepare("INSERT INTO clientes (nome, cpf, telefone, email, endereco, cidade, observacoes) VALUES (:nome, :cpf, :telefone, :email, :endereco, :cidade, :observacoes)");
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':cpf', $cpf);
        $stmt->bindParam(':telefone', $telefone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':endereco', $endereco);
        $stmt->bindParam(':cidade', $cidade);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->execute();

        $_SESSION['mensagem'] = "Cliente cadastrado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
    } elseif ($acao == 'editar') {
        $stmt = $conn->prepare("UPDATE clientes SET nome = :nome, cpf = :cpf, telefone = :telefone, email = :email, endereco = :endereco, cidade = :cidade, observacoes = :observacoes WHERE id = :id");
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':cpf', $cpf);
        $stmt->bindParam(':telefone', $telefone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':endereco', $endereco);
        $stmt->bindParam(':cidade', $cidade);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensagem'] = "Cliente atualizado com sucesso!";
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
