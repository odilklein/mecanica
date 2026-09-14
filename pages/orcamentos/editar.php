<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM orcamentos WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$orcamento = $stmt->fetch();

if (!$orcamento || $orcamento['status'] != 'aberto') {
    $_SESSION['mensagem'] = "Orçamento não encontrado ou não pode mais ser editado (apenas orçamentos abertos podem ser editados).";
    $_SESSION['mensagem_tipo'] = "warning";
    header("Location: listar.php");
    exit();
}

// Buscar itens existentes
$stmt = $conn->prepare("SELECT oi.* FROM orcamento_itens oi WHERE oi.orcamento_id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$itens_existentes = $stmt->fetchAll();

$pecas = $conn->query("SELECT id, nome, preco_venda, quantidade FROM pecas ORDER BY nome")->fetchAll();
$servicos = $conn->query("SELECT id, nome, preco_mao_obra FROM servicos WHERE ativo = 1 ORDER BY nome")->fetchAll();

$titulo_pagina = "Editar Orçamento #" . str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
$scripts_extra = '<script>window.OrcamentoEditorConfig = {pecas: ' . json_encode($pecas) . ', servicos: ' . json_encode($servicos) . ', itens: ' . json_encode($itens_existentes) . '};</script><script src="' . $caminho_base . '/js/orcamento-editor.js"></script>';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="pencil" class="text-primary me-2"></i>Editar Orçamento #<?php echo str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT); ?></h1>
        <span class="page-subtitle">Atualize o orçamento</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="processar.php?acao=editar" id="formOrcamento">
            <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
            <input type="hidden" name="id" value="<?php echo $id; ?>">

            <div class="card mb-3">
                <div class="card-header"><i data-lucide="user" class="me-2 text-primary"></i>Dados do Cliente</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cliente_id" class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required onchange="if(typeof carregarVeiculos==='function'){carregarVeiculos(this.value, 'veiculo_id', null);}">
                                <option value="">-- Selecione o cliente --</option>
                                <?php foreach ($conn->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll() as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $orcamento['cliente_id'] ? 'selected' : ''; ?>><?php echo limpar($c['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="veiculo_id" class="form-label">Veículo</label>
                            <select class="form-select" id="veiculo_id" name="veiculo_id">
                                <option value="">-- Selecione --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="data_validade" class="form-label">Validade do Orçamento</label>
                            <input type="date" class="form-control" id="data_validade" name="data_validade" value="<?php echo $orcamento['data_validade']; ?>">
                        </div>
                        <div class="col-12">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="2"><?php echo limpar($orcamento['observacoes']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i data-lucide="list" class="me-2 text-primary"></i>Itens do Orçamento</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="OrcamentoEditor.adicionarItem()">
                        <i data-lucide="plus" class="me-1"></i>Adicionar Item
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width:20%">Tipo</th>
                                    <th>Item</th>
                                    <th style="width:90px">Qtd</th>
                                    <th style="width:150px">Preço</th>
                                    <th style="width:130px">Subtotal</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="corpoItens"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card sticky-top" style="top: 80px;">
            <div class="card-header"><i data-lucide="calculator" class="me-2 text-primary"></i>Resumo</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Peças:</span><strong id="totalPecas">R$ 0,00</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Mão de Obra:</span><strong id="totalServicos">R$ 0,00</strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span class="h5 mb-0">TOTAL:</span>
                    <strong class="h4 mb-0 text-primary" id="totalGeral">R$ 0,00</strong>
                </div>
                <button type="submit" form="formOrcamento" class="btn btn-success btn-lg w-100 btn-icon">
                    <i data-lucide="save"></i> Atualizar Orçamento
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clienteId = document.getElementById('cliente_id').value;
    if (clienteId && typeof carregarVeiculos === 'function') {
        carregarVeiculos(clienteId, 'veiculo_id', '<?php echo $orcamento['veiculo_id']; ?>');
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>