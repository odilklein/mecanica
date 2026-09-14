<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/notificacoes.php';
exigir_papel(['admin', 'secretaria']);

$acao = $_GET['acao'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$codigo = strtoupper(limpar($_POST['codigo'] ?? ''));
$nome = limpar($_POST['nome'] ?? '');
$descricao = limpar($_POST['descricao'] ?? '');
$categoria = limpar($_POST['categoria'] ?? '');
$quantidade = (int)($_POST['quantidade'] ?? 0);
$estoque_minimo = (int)($_POST['estoque_minimo'] ?? 5);

// Converter preços de R$ para decimal
$preco_compra = str_replace(['R$', '.', ','], ['', '', '.'], limpar($_POST['preco_compra'] ?? '0'));
$preco_venda = str_replace(['R$', '.', ','], ['', '', '.'], limpar($_POST['preco_venda'] ?? '0'));

$fornecedor = limpar($_POST['fornecedor'] ?? '');
$localizacao = limpar($_POST['localizacao'] ?? '');

if (empty($codigo) || empty($nome)) {
    $_SESSION['mensagem'] = "Código e nome da peça são obrigatórios.";
    $_SESSION['mensagem_tipo'] = "warning";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}

try {
    if ($acao == 'cadastrar') {
        // Verificar código duplicado
        $stmt = $conn->prepare("SELECT id FROM pecas WHERE codigo = :codigo");
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        if ($stmt->fetch()) {
            $_SESSION['mensagem'] = "Já existe uma peça com este código.";
            $_SESSION['mensagem_tipo'] = "warning";
            header("Location: cadastrar.php");
            exit();
        }

        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO pecas (codigo, nome, descricao, categoria, quantidade, estoque_minimo, preco_compra, preco_venda, fornecedor, localizacao) VALUES (:codigo, :nome, :descricao, :categoria, :quantidade, :estoque_minimo, :preco_compra, :preco_venda, :fornecedor, :localizacao)");
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':categoria', $categoria);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':estoque_minimo', $estoque_minimo);
        $stmt->bindParam(':preco_compra', $preco_compra);
        $stmt->bindParam(':preco_venda', $preco_venda);
        $stmt->bindParam(':fornecedor', $fornecedor);
        $stmt->bindParam(':localizacao', $localizacao);
        $stmt->execute();

        $novo_id = $conn->lastInsertId();

        // Registrar movimentação inicial se quantidade > 0
        if ($quantidade > 0) {
            $stmt = $conn->prepare("INSERT INTO movimentacoes_estoque (peca_id, tipo, quantidade, motivo) VALUES (:peca_id, 'entrada', :quantidade, 'Cadastro inicial de estoque')");
            $stmt->bindParam(':peca_id', $novo_id);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->execute();
        }

        $conn->commit();
        $_SESSION['mensagem'] = "Peça cadastrada com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";

        if ($quantidade < $estoque_minimo) {
            notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], null, 'estoque', 'A peça "' . $nome . '" está com estoque abaixo do mínimo (' . $quantidade . ' un. / mínimo ' . $estoque_minimo . ').');
        }
    } elseif ($acao == 'editar') {
        $stmt = $conn->prepare("UPDATE pecas SET codigo = :codigo, nome = :nome, descricao = :descricao, categoria = :categoria, estoque_minimo = :estoque_minimo, preco_compra = :preco_compra, preco_venda = :preco_venda, fornecedor = :fornecedor, localizacao = :localizacao WHERE id = :id");
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':categoria', $categoria);
        $stmt->bindParam(':estoque_minimo', $estoque_minimo);
        $stmt->bindParam(':preco_compra', $preco_compra);
        $stmt->bindParam(':preco_venda', $preco_venda);
        $stmt->bindParam(':fornecedor', $fornecedor);
        $stmt->bindParam(':localizacao', $localizacao);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensagem'] = "Peça atualizada com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
    }

    header("Location: listar.php");
    exit();
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $_SESSION['mensagem'] = "Erro ao processar: " . $e->getMessage();
    $_SESSION['mensagem_tipo'] = "danger";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}
?>
