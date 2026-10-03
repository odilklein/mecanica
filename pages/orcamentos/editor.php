<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$id = (int) ($_GET['id'] ?? 0);
$os_id = (int) ($_GET['os_id'] ?? 0);
$cliente_preselecionado = (int) ($_GET['cliente_id'] ?? 0);
$veiculo_preselecionado = (int) ($_GET['veiculo_id'] ?? 0);

$orcamento = null;
$itens_existentes = [];
if ($id > 0) {
    $st = $conn->prepare('SELECT * FROM orcamentos WHERE id = :id');
    $st->execute([':id' => $id]);
    $orcamento = $st->fetch();
    if (!$orcamento || $orcamento['status'] !== 'aberto') {
        $_SESSION['mensagem'] = 'Orçamento não encontrado ou não pode mais ser editado (apenas orçamentos abertos).';
        $_SESSION['mensagem_tipo'] = 'warning';
        header('Location: listar.php');
        exit();
    }
    $st = $conn->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = :id ORDER BY id');
    $st->execute([':id' => $id]);
    $itens_existentes = $st->fetchAll();
    $cliente_preselecionado = (int) $orcamento['cliente_id'];
    $veiculo_preselecionado = (int) $orcamento['veiculo_id'];
}

$clientes = $conn->query('SELECT id, nome FROM clientes ORDER BY nome')->fetchAll();

$titulo_pagina = $id > 0
    ? 'Editar Orçamento #' . str_pad((string) $orcamento['numero'], 4, '0', STR_PAD_LEFT)
    : 'Novo orçamento';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$editor_config = [
    'apiUrl' => $caminho_base . '/api/orcamentos.php',
    'id' => $id,
    'osId' => $orcamento['os_id'] ?? $os_id,
    'veiculoPreselecionado' => $veiculo_preselecionado,
    'itens' => $itens_existentes,
    'dataValidadePadrao' => date('Y-m-d', strtotime('+15 days')),
];
$scripts_extra  = '<script>window.OrcamentoEditorConfig = ' . json_encode($editor_config, JSON_UNESCAPED_UNICODE) . ';</script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/orcamento-editor.js"></script>';
?>

<div class="page-header">
    <div>
        <h1><?php echo $id > 0 ? 'Editar orçamento #' . str_pad((string) $orcamento['numero'], 4, '0', STR_PAD_LEFT) : 'Novo orçamento'; ?></h1>
        <span class="page-subtitle">Monte o documento com itens de peça e mão de obra</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i>Voltar</a>
</div>

<form id="formOrcamento">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="icon-base ti tabler-user me-2"></i>Cliente e veículo</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required
                                    onchange="if(typeof carregarVeiculos==='function'){carregarVeiculos(this.value,'veiculo_id',null);}">
                                <option value="">Selecione</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo (int) $cliente['id']; ?>" <?php echo $cliente_preselecionado === (int) $cliente['id'] ? 'selected' : ''; ?>><?php echo esc($cliente['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Veículo</label>
                            <select class="form-select" id="veiculo_id" name="veiculo_id">
                                <option value="">Selecione o cliente</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Validade</label>
                            <input class="form-control" type="date" name="data_validade"
                                   value="<?php echo esc($orcamento['data_validade'] ?? date('Y-m-d', strtotime('+15 days'))); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" name="observacoes" rows="2" maxlength="4000"><?php echo esc($orcamento['observacoes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="icon-base ti tabler-list me-2"></i>Itens do orçamento</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="OrcamentoEditor.adicionarItem()">
                        <i class="icon-base ti tabler-plus me-1"></i>Adicionar item
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
                <div class="card-header"><i class="icon-base ti tabler-calculator me-2 text-primary"></i>Resumo</div>
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
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="icon-base ti tabler-device-floppy"></i> <?php echo $id > 0 ? 'Atualizar orçamento' : 'Criar orçamento'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
