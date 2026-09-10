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

$pecas = $conn->query("SELECT * FROM pecas WHERE quantidade > 0 ORDER BY nome")->fetchAll();
$servicos = $conn->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY nome")->fetchAll();

$titulo_pagina = "Editar Orçamento #" . str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
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
                            <select class="form-select" id="cliente_id" name="cliente_id" required onchange="carregarVeiculos(this.value, 'veiculo_id', null)">
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
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="adicionarItem()">
                        <i data-lucide="plus" class="me-1"></i>Adicionar Item
                    </button>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th style="width: 20%">Tipo</th>
                                <th>Item</th>
                                <th style="width: 90px">Qtd</th>
                                <th style="width: 130px">Preço</th>
                                <th style="width: 130px">Subtotal</th>
                                <th style="width: 40px"></th>
                            </tr>
                        </thead>
                        <tbody id="corpoItens">
                        </tbody>
                    </table>
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
                <button type="submit" form="formOrcamento" class="btn btn-success btn-lg w-100 btn-icon" onclick="return validarOrcamento()">
                    <i data-lucide="save"></i> Atualizar Orçamento
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const pecas = <?php echo json_encode($pecas); ?>;
const servicos = <?php echo json_encode($servicos); ?>;

// Dados dos itens existentes
const itensExistentes = <?php echo json_encode($itens_existentes); ?>;

let contadorItem = 0;

function adicionarItem(pecaIdSelecionada = '', servicoIdSelecionado = '', tipo = 'peca', qtdTxt = '1') {
    contadorItem++;
    const id = contadorItem;
    const tipoHtml = `
        <select class="form-select form-select-sm tipo-item" id="tipo_${id}" onchange="mudarTipo(${id})">
            <option value="peca" ${tipo === 'peca' ? 'selected' : ''}>Peça</option>
            <option value="servico" ${tipo === 'servico' ? 'selected' : ''}>Serviço</option>
        </select>
    `;

    let selectPeca = '<select class="form-select form-select-sm item-select" id="item_peca_' + id + '" onchange="atualizarPreco(' + id + ')">';
    selectPeca += '<option value="">-- Selecione a peça --</option>';
    pecas.forEach(p => {
        selectPeca += `<option value="${p.id}" data-preco="${p.preco_venda}" data-nome="${p.nome.replace(/'/g, "\\'")}" ${pecaIdSelecionada === p.id.toString() ? 'selected' : ''}>${p.nome} (em estoque: ${p.quantidade})</option>`;
    });
    selectPeca += '</select>';

    let selectServico = '<select class="form-select form-select-sm item-select" id="item_servico_' + id + '" onchange="atualizarPreco(' + id + ')">';
    selectServico += '<option value="">-- Selecione o serviço --</option>';
    servicos.forEach(s => {
        selectServico += `<option value="${s.id}" data-preco="${s.preco_mao_obra}" data-nome="${s.nome.replace(/'/g, "\\'")}" ${servicoIdSelecionado === s.id.toString() ? 'selected' : ''}>${s.nome}</option>`;
    });
    selectServico += '</select>';

    const row = document.createElement('tr');
    row.id = 'linha_' + id;
    row.innerHTML = `
        <td>${tipoHtml}</td>
        <td id="celItem_${id}">${tipo === 'servico' ? selectServico : selectPeca}</td>
        <td><input type="number" class="form-control form-control-sm qtd-item" id="qtd_${id}" value="${qtdTxt}" min="1" onchange="atualizarSubtotal(${id})"></td>
        <td><input type="text" class="form-control form-control-sm preco-item" id="preco_${id}" value="R$ 0,00" readonly></td>
        <td><strong class="subtotal-item" id="subtotal_${id}">R$ 0,00</strong></td>
        <td><button type="button" class="btn btn-link text-danger p-0" onclick="removerItem(${id})"><i data-lucide="x"></i></button></td>
    `;

    document.getElementById('corpoItens').appendChild(row);
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Se há preço pré-definido (item existente), aplicar
    if (qtdTxt && qtdTxt !== '1' || pecaIdSelecionada || servicoIdSelecionado) {
        atualizarPreco(id);
        // Buscar preço correto
        const select = document.getElementById(tipo === 'peca' ? 'item_peca_' + id : 'item_servico_' + id);
        const preco = select.options[select.selectedIndex].dataset.preco || '0';
        document.getElementById('preco_' + id).value = 'R$ ' + parseFloat(preco).toFixed(2).replace('.', ',');
        atualizarSubtotal(id);
    }
}

function mudarTipo(id) {
    const tipo = document.getElementById('tipo_' + id).value;
    const celItem = document.getElementById('celItem_' + id);

    let options = '';
    if (tipo === 'peca') {
        options = '<select class="form-select form-select-sm item-select" id="item_peca_' + id + '" onchange="atualizarPreco(' + id + ')">';
        options += '<option value="">-- Selecione a peça --</option>';
        pecas.forEach(p => {
            options += `<option value="${p.id}" data-preco="${p.preco_venda}" data-nome="${p.nome.replace(/'/g, "\\'")}">${p.nome} (em estoque: ${p.quantidade})</option>`;
        });
        options += '</select>';
    } else {
        options = '<select class="form-select form-select-sm item-select" id="item_servico_' + id + '" onchange="atualizarPreco(' + id + ')">';
        options += '<option value="">-- Selecione o serviço --</option>';
        servicos.forEach(s => {
            options += `<option value="${s.id}" data-preco="${s.preco_mao_obra}" data-nome="${s.nome.replace(/'/g, "\\'")}">${s.nome}</option>`;
        });
        options += '</select>';
    }

    celItem.innerHTML = options;
    if (typeof lucide !== 'undefined') lucide.createIcons();
    atualizarPreco(id);
}

function atualizarPreco(id) {
    const tipo = document.getElementById('tipo_' + id).value;
    const selectId = tipo === 'peca' ? 'item_peca_' + id : 'item_servico_' + id;
    const select = document.getElementById(selectId);
    const preco = select.options[select.selectedIndex].dataset.preco || '0';
    document.getElementById('preco_' + id).value = 'R$ ' + parseFloat(preco).toFixed(2).replace('.', ',');

    if (tipo === 'servico') {
        document.getElementById('qtd_' + id).value = 1;
        document.getElementById('qtd_' + id).readOnly = true;
    } else {
        document.getElementById('qtd_' + id).readOnly = false;
    }

    atualizarSubtotal(id);
}

function atualizarSubtotal(id) {
    const tipo = document.getElementById('tipo_' + id).value;
    const qtd = parseInt(document.getElementById('qtd_' + id).value) || 0;
    const precoTxt = document.getElementById('preco_' + id).value.replace('R$ ', '').replace('.', '').replace(',', '.');
    const preco = parseFloat(precoTxt) || 0;
    const subtotal = qtd * preco;
    document.getElementById('subtotal_' + id).textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    calcularTotais();
}

function removerItem(id) {
    document.getElementById('linha_' + id).remove();
    calcularTotais();
}

function calcularTotais() {
    let totalPecas = 0, totalServicos = 0;
    document.querySelectorAll('#corpoItens tr').forEach(row => {
        const id = row.id.replace('linha_', '');
        const tipo = document.getElementById('tipo_' + id).value;
        const subtotalTxt = document.getElementById('subtotal_' + id).textContent.replace('R$ ', '').replace('.', '').replace(',', '.');
        const subtotal = parseFloat(subtotalTxt) || 0;
        if (tipo === 'peca') totalPecas += subtotal;
        else totalServicos += subtotal;
    });

    const fmt = v => 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    document.getElementById('totalPecas').textContent = fmt(totalPecas);
    document.getElementById('totalServicos').textContent = fmt(totalServicos);
    document.getElementById('totalGeral').textContent = fmt(totalPecas + totalServicos);

    let hiddenPecas = document.getElementById('hidden_total_pecas');
    let hiddenServicos = document.getElementById('hidden_total_servicos');
    let hiddenGeral = document.getElementById('hidden_total_geral');
    if (!hiddenPecas) {
        const form = document.getElementById('formOrcamento');
        hiddenPecas = document.createElement('input');
        hiddenPecas.type = 'hidden'; hiddenPecas.name = 'total_pecas'; hiddenPecas.id = 'hidden_total_pecas';
        form.appendChild(hiddenPecas);
        hiddenServicos = document.createElement('input');
        hiddenServicos.type = 'hidden'; hiddenServicos.name = 'total_servicos'; hiddenServicos.id = 'hidden_total_servicos';
        form.appendChild(hiddenServicos);
        hiddenGeral = document.createElement('input');
        hiddenGeral.type = 'hidden'; hiddenGeral.name = 'total_geral'; hiddenGeral.id = 'hidden_total_geral';
        form.appendChild(hiddenGeral);
    }
    hiddenPecas.value = totalPecas;
    hiddenServicos.value = totalServicos;
    hiddenGeral.value = totalPecas + totalServicos;
}

function validarOrcamento() {
    const itens = document.querySelectorAll('#corpoItens tr');
    if (itens.length === 0) {
        alert('Adicione pelo menos um item ao orçamento.');
        return false;
    }

    for (const row of itens) {
        const id = row.id.replace('linha_', '');
        const tipo = document.getElementById('tipo_' + id).value;
        const selectId = tipo === 'peca' ? 'item_peca_' + id : 'item_servico_' + id;
        const select = document.getElementById(selectId);
        if (!select.value) {
            alert('Selecione todos os itens do orçamento.');
            return false;
        }
        if (tipo === 'peca') {
            const qtd = parseInt(document.getElementById('qtd_' + id).value) || 0;
            const p = pecas.find(p => p.id === parseInt(select.value));
            if (p && qtd > p.quantidade) {
                alert('Estoque insuficiente de "' + p.nome + '". Disponível: ' + p.quantidade + ' unidade(s).');
                return false;
            }
        }
    }

    definirItensHidden();
    return true;
}

function definirItensHidden() {
    document.querySelectorAll('input[name="itens[]"]').forEach(el => el.remove());

    document.querySelectorAll('#corpoItens tr').forEach(row => {
        const id = row.id.replace('linha_', '');
        const tipo = document.getElementById('tipo_' + id).value;
        const selectId = tipo === 'peca' ? 'item_peca_' + id : 'item_servico_' + id;
        const select = document.getElementById(selectId);
        const qtd = document.getElementById('qtd_' + id).value;
        const precoTxt = document.getElementById('preco_' + id).value.replace('R$ ', '').replace('.', '').replace(',', '.');
        const subtotalTxt = document.getElementById('subtotal_' + id).textContent.replace('R$ ', '').replace('.', '').replace(',', '.');

        const form = document.getElementById('formOrcamento');
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'itens[]';
        hidden.value = JSON.stringify({
            tipo: tipo,
            item_id: select.value,
            quantidade: parseInt(qtd) || 1,
            preco: parseFloat(precoTxt) || 0,
            subtotal: parseFloat(subtotalTxt) || 0
        });
        form.appendChild(hidden);
    });
}

// Carregar veículos do cliente selecionado
document.addEventListener('DOMContentLoaded', function() {
    const clienteId = document.getElementById('cliente_id').value;
    if (clienteId) {
        carregarVeiculos(clienteId, 'veiculo_id', '<?php echo $orcamento['veiculo_id']; ?>');
    }
});

// Adicionar itens existentes
document.addEventListener('DOMContentLoaded', function() {
    if (itensExistentes.length > 0) {
        itensExistentes.forEach(item => {
            if (item.tipo === 'peca') {
                adicionarItem(item.peca_id.toString(), '', 'peca', item.quantidade.toString());
                // Set preço corretamente
                const lastId = contadorItem;
                setTimeout(function() {
                    document.getElementById('preco_' + lastId).value = 'R$ ' + parseFloat(item.preco_unitario).toFixed(2).replace('.', ',');
                    atualizarSubtotal(lastId);
                }, 10);
            } else {
                adicionarItem('', item.servico_id.toString(), 'servico', '1');
            }
        });
    } else {
        adicionarItem();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
