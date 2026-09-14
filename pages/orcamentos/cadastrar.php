<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
$clientes = $conn->query('SELECT id, nome FROM clientes ORDER BY nome')->fetchAll();
$pecas = $conn->query("SELECT id, nome, preco_venda, quantidade FROM pecas ORDER BY nome")->fetchAll();
$servicos = $conn->query("SELECT id, nome, preco_mao_obra FROM servicos WHERE ativo = 1 ORDER BY nome")->fetchAll();
$os_id = (int) ($_GET['os_id'] ?? 0);
$cliente_preselecionado = (int) ($_GET['cliente_id'] ?? 0);
$veiculo_preselecionado = (int) ($_GET['veiculo_id'] ?? 0);
$titulo_pagina = 'Novo orçamento';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
$scripts_extra = '<script>window.OrcamentoEditorConfig = {pecas: ' . json_encode($pecas) . ', servicos: ' . json_encode($servicos) . ', itens: []};</script><script src="' . $caminho_base . '/js/orcamento-editor.js"></script>';
if ($veiculo_preselecionado > 0) {
    $scripts_extra .= '<script>document.addEventListener("DOMContentLoaded", function(){ if (typeof carregarVeiculos === "function" && document.getElementById("cliente_id").value) { carregarVeiculos(document.getElementById("cliente_id").value, "veiculo_id", ' . $veiculo_preselecionado . '); } });</script>';
}
?>
<div class="page-header">
    <div>
        <h1><i data-lucide="file-plus-2" class="text-primary me-2"></i>Novo orçamento</h1>
        <span class="page-subtitle">Monte o documento com peças e serviços do catálogo ou itens avulsos</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon action-button"><i data-lucide="arrow-left"></i>Voltar</a>
</div>

<form method="POST" action="processar.php?acao=cadastrar" id="formOrcamento">
<input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
<input type="hidden" name="os_id" value="<?php echo $os_id; ?>">
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i data-lucide="user-round" class="me-2"></i>Cliente e veículo</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i data-lucide="user" class="form-label-icon"></i>Cliente *</label>
                        <select class="form-select" id="cliente_id" name="cliente_id" required
                                onchange="if(typeof carregarVeiculos==='function'){carregarVeiculos(this.value,'veiculo_id',null);}">
                            <option value="">Selecione</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo (int) $cliente['id']; ?>" <?php echo $cliente_preselecionado === (int) $cliente['id'] ? 'selected' : ''; ?>><?php echo esc($cliente['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i data-lucide="car-front" class="form-label-icon"></i>Veículo</label>
                        <select class="form-select" id="veiculo_id" name="veiculo_id">
                            <option value="">Selecione o cliente</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i data-lucide="calendar-clock" class="form-label-icon"></i>Validade</label>
                        <input class="form-control" type="date" name="data_validade" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i data-lucide="notebook-pen" class="form-label-icon"></i>Observações</label>
                        <textarea class="form-control" name="observacoes" rows="2" maxlength="4000"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i data-lucide="list" class="me-2"></i>Itens do orçamento</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="OrcamentoEditor.adicionarItem()">
                    <i data-lucide="plus" class="me-1"></i>Adicionar item
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
                <button type="submit" class="btn btn-success btn-lg w-100 btn-icon">
                    <i data-lucide="save"></i> Criar Orçamento
                </button>
            </div>
        </div>
    </div>
</div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>