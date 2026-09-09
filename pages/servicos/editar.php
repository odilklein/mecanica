<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM servicos WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$servico = $stmt->fetch();

if (!$servico) {
    $_SESSION['mensagem'] = "Serviço não encontrado.";
    $_SESSION['mensagem_tipo'] = "danger";
    header("Location: listar.php");
    exit();
}

$titulo_pagina = "Editar Serviço";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-edit text-primary me-2"></i>Editar Serviço</h1>
        <span class="page-subtitle"><?php echo limpar($servico['nome']); ?></span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=editar">
                    <input type="hidden" name="id" value="<?php echo $servico['id']; ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nome" class="form-label">Nome do Serviço *</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo limpar($servico['nome']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo limpar($servico['descricao']); ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="preco_mao_obra" class="form-label">Preço Mão de Obra *</label>
                            <input type="text" class="form-control" id="preco_mao_obra" name="preco_mao_obra" value="R$ <?php echo number_format($servico['preco_mao_obra'], 2, ',', '.'); ?>" oninput="formatarMoedaInput(this)" required>
                        </div>
                        <div class="col-md-4">
                            <label for="tempo_estimado" class="form-label">Tempo Estimado (min)</label>
                            <input type="number" class="form-control" id="tempo_estimado" name="tempo_estimado" min="0" value="<?php echo $servico['tempo_estimado']; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="ativo" class="form-label">Status</label>
                            <select class="form-select" id="ativo" name="ativo">
                                <option value="1" <?php echo $servico['ativo'] == 1 ? 'selected' : ''; ?>>Ativo</option>
                                <option value="0" <?php echo $servico['ativo'] == 0 ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="fas fa-save"></i> Atualizar Serviço
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelector('form').addEventListener('submit', function() {
        moedaParaNumero(document.getElementById('preco_mao_obra'));
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
