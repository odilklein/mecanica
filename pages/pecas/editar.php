<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM pecas WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$peca = $stmt->fetch();

if (!$peca) {
    $_SESSION['mensagem'] = "Peça não encontrada.";
    $_SESSION['mensagem_tipo'] = "danger";
    header("Location: listar.php");
    exit();
}

$titulo_pagina = "Editar Peça";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="pencil" class="text-primary me-2"></i>Editar Peça</h1>
        <span class="page-subtitle"><?php echo esc($peca['nome']); ?></span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=editar">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo $peca['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="codigo" class="form-label">Código *</label>
                            <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo esc($peca['codigo']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nome" class="form-label">Nome da Peça *</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo esc($peca['nome']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="categoria" class="form-label">Categoria</label>
                            <select class="form-select" id="categoria" name="categoria">
                                <option value="">Selecione...</option>
                                <?php
                                $categorias = ['Filtros','Freios','Motor','Suspensão','Elétrica','Lubrificantes','Pneus','Outros'];
                                foreach ($categorias as $cat):
                                    $selected = ($peca['categoria'] == $cat) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $cat; ?>" <?php echo $selected; ?>><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="2"><?php echo esc($peca['descricao']); ?></textarea>
                        </div>
                        <div class="col-md-3">
                            <label for="quantidade" class="form-label">Quantidade</label>
                            <input type="number" class="form-control" id="quantidade" name="quantidade" min="0" value="<?php echo $peca['quantidade']; ?>" readonly>
                            <small class="text-muted">Use "Entrada/Saída" para alterar</small>
                        </div>
                        <div class="col-md-3">
                            <label for="estoque_minimo" class="form-label">Estoque Mínimo *</label>
                            <input type="number" class="form-control" id="estoque_minimo" name="estoque_minimo" min="0" value="<?php echo $peca['estoque_minimo']; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="preco_compra" class="form-label">Preço de Compra</label>
                            <input type="text" class="form-control" id="preco_compra" name="preco_compra" value="R$ <?php echo number_format($peca['preco_compra'], 2, ',', '.'); ?>" oninput="formatarMoedaInput(this)">
                        </div>
                        <div class="col-md-3">
                            <label for="preco_venda" class="form-label">Preço de Venda *</label>
                            <input type="text" class="form-control" id="preco_venda" name="preco_venda" value="R$ <?php echo number_format($peca['preco_venda'], 2, ',', '.'); ?>" oninput="formatarMoedaInput(this)" required>
                        </div>
                        <div class="col-md-6">
                            <label for="fornecedor" class="form-label">Fornecedor</label>
                            <input type="text" class="form-control" id="fornecedor" name="fornecedor" value="<?php echo esc($peca['fornecedor']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="localizacao" class="form-label">Localização (prateleira)</label>
                            <input type="text" class="form-control" id="localizacao" name="localizacao" value="<?php echo esc($peca['localizacao']); ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i data-lucide="save"></i> Atualizar Peça
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Remover formatação R$ para envio
    document.querySelector('form').addEventListener('submit', function() {
        moedaParaNumero(document.getElementById('preco_compra'));
        moedaParaNumero(document.getElementById('preco_venda'));
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
