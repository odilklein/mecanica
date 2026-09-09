<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$titulo_pagina = "Novo Orçamento";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$clientes = $conn->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();
$servicos = $conn->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY nome")->fetchAll();
$pecas = $conn->query("SELECT * FROM pecas WHERE quantidade > 0 ORDER BY nome")->fetchAll();
$os_id = (int) ($_GET['os_id'] ?? 0);
$cliente_preselecionado = (int) ($_GET['cliente_id'] ?? 0);
$veiculo_preselecionado = (int) ($_GET['veiculo_id'] ?? 0);
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="file-text" class="text-primary me-2"></i>Novo Orçamento</h1>
        <span class="page-subtitle">Crie um orçamento em 3 passos: cliente, itens e revisão</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<form method="POST" action="processar.php?acao=cadastrar" id="formOrcamento">
    <input type="hidden" name="os_id" value="<?php echo $os_id; ?>">
    <div class="row">
        <div class="col-lg-8">
            <!-- Progresso do wizard -->
            <div class="wizard-progress">
                <div class="wizard-step active" id="wz1">
                    <span class="step-num"><i data-lucide="user"></i></span>
                    <span class="step-label">Cliente e Veículo</span>
                </div>
                <div class="wizard-step" id="wz2">
                    <span class="step-num"><i data-lucide="list"></i></span>
                    <span class="step-label">Itens do Orçamento</span>
                </div>
                <div class="wizard-step" id="wz3">
                    <span class="step-num"><i data-lucide="check"></i></span>
                    <span class="step-label">Revisão</span>
                </div>
            </div>

            <!-- ETAPA 1: Cliente e Veículo -->
            <div class="card wizard-panel active" id="painel1">
                <div class="card-body">
                    <div class="panel-header">
                        <i data-lucide="user-cog"></i>
                        <div>
                            <p class="panel-title">Identifique o cliente e o veículo</p>
                            <p class="panel-subtitle">Selecione o cliente para carregar os veículos cadastrados.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cliente_id" class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required onchange="carregarVeiculos(this.value, 'veiculo_id', null)">
                                <option value="">-- Selecione o cliente --</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $cliente_preselecionado === (int) $c['id'] ? 'selected' : ''; ?>><?php echo limpar($c['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="veiculo_id" class="form-label">Veículo</label>
                            <select class="form-select" id="veiculo_id" name="veiculo_id">
                                <option value="">Selecione o cliente primeiro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="data_validade" class="form-label">Validade do Orçamento</label>
                            <input type="date" class="form-control" id="data_validade" name="data_validade" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>">
                        </div>
                        <div class="col-12">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="2" placeholder="Observações gerais do orçamento"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ETAPA 2: Itens (serviços e peças) -->
            <div class="card wizard-panel" id="painel2">
                <div class="card-body">
                    <div class="panel-header">
                        <i data-lucide="hammer"></i>
                        <div>
                            <p class="panel-title">Selecione os serviços</p>
                            <p class="panel-subtitle">Marque um ou mais serviços. A quantidade padrão é 1.</p>
                        </div>
                    </div>

                    <?php if (empty($servicos)): ?>
                        <div class="empty-state">
                            <i data-lucide="wrench"></i>
                            <h4>Nenhum serviço cadastrado</h4>
                            <p>Cadastre serviços para incluir mão de obra no orçamento.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3 mb-4">
                            <?php foreach ($servicos as $s): ?>
                                <div class="col-md-6">
                                    <div class="select-card selecionavel" data-tipo="servico" data-id="<?php echo $s['id']; ?>" data-preco="<?php echo $s['preco_mao_obra']; ?>" onclick="toggleCard(this)">
                                        <div class="check-badge"><i data-lucide="check"></i></div>
                                        <div class="select-card-title"><?php echo limpar($s['nome']); ?></div>
                                        <div class="select-card-desc"><?php echo formatar_moeda($s['preco_mao_obra']); ?> por serviço<?php if (!empty($s['tempo_estimado'])): ?> · ~<?php echo limpar($s['tempo_estimado']); ?>h<?php endif; ?></div>
                                        <div class="qtd-wrap" onclick="event.stopPropagation()">
                                            <label>Qtd.</label>
                                            <input type="number" class="form-control form-control-sm qtd-select" value="1" min="1" onchange="atualizarTotais()">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="panel-header mt-4">
                            <i data-lucide="package"></i>
                            <div>
                                <p class="panel-title">Selecione as peças</p>
                                <p class="panel-subtitle">Peças disponíveis em estoque. A quantidade não pode exceder o estoque.</p>
                            </div>
                        </div>

                        <?php if (empty($pecas)): ?>
                            <div class="empty-state">
                                <i data-lucide="box"></i>
                                <h4>Nenhuma peça em estoque</h4>
                                <p>Cadastre peças para incluí-las no orçamento.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($pecas as $p): ?>
                                    <div class="col-md-6">
                                        <div class="select-card selecionavel" data-tipo="peca" data-id="<?php echo $p['id']; ?>" data-preco="<?php echo $p['preco_venda']; ?>" onclick="toggleCard(this)">
                                            <div class="check-badge"><i data-lucide="check"></i></div>
                                            <div class="select-card-title"><?php echo limpar($p['nome']); ?></div>
                                            <div class="select-card-desc"><?php echo formatar_moeda($p['preco_venda']); ?> · Estoque: <?php echo (int)$p['quantidade']; ?></div>
                                            <div class="qtd-wrap" onclick="event.stopPropagation()">
                                                <label>Qtd.</label>
                                                <input type="number" class="form-control form-control-sm qtd-select" value="1" min="1" max="<?php echo (int)$p['quantidade']; ?>" onchange="atualizarTotais()">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="d-flex align-items-center gap-2 mt-4 text-muted">
                        <i data-lucide="info" class="icon-sm"></i>
                        <small>Os itens selecionados aparecem na revisão, na próxima etapa.</small>
                    </div>
                </div>
            </div>

            <!-- ETAPA 3: Revisão -->
            <div class="card wizard-panel" id="painel3">
                <div class="card-body">
                    <div class="panel-header">
                        <i data-lucide="clipboard-check"></i>
                        <div>
                            <p class="panel-title">Revise o orçamento</p>
                            <p class="panel-subtitle">Confira os itens e valores antes de salvar.</p>
                        </div>
                    </div>

                    <div id="resumoCliente" class="mb-4"></div>

                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h6 class="mb-0">Itens</h6>
                        <button type="button" class="btn btn-sm btn-link" onclick="irParaEtapa(2)">Editar itens</button>
                    </div>
                    <div class="item-lista mb-4" id="listaItens"></div>

                    <div class="totals-resumo">
                        <div class="d-flex justify-content-between py-1">
                            <span>Peças:</span>
                            <strong id="totalPecas">R$ 0,00</strong>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Mão de Obra:</span>
                            <strong id="totalServicos">R$ 0,00</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 grand-total">
                            <span class="h5 mb-0">TOTAL:</span>
                            <strong class="h4 mb-0 text-primary" id="totalGeral">R$ 0,00</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lateral -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 80px;">
                <div class="card-header"><i data-lucide="calculator" class="me-2 text-primary"></i>Resumo</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Itens selecionados:</span>
                        <strong id="contadorItens">0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Peças:</span>
                        <strong id="totalPecasLateral">R$ 0,00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Mão de Obra:</span>
                        <strong id="totalServicosLateral">R$ 0,00</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="h5 mb-0">TOTAL:</span>
                        <strong class="h4 mb-0 text-primary" id="totalGeralLateral">R$ 0,00</strong>
                    </div>
                    <button type="button" id="btnProximo" class="btn btn-primary btn-lg w-100 btn-icon" onclick="irParaEtapa(2)">
                        <i data-lucide="arrow-right"></i> Próximo
                    </button>
                    <button type="button" id="btnVoltar" class="btn btn-outline-secondary btn-lg w-100 btn-icon mt-2" style="display:none" onclick="irParaEtapa(1)">
                        <i data-lucide="arrow-left"></i> Voltar
                    </button>
                    <button type="submit" id="btnSalvar" class="btn btn-success btn-lg w-100 btn-icon mt-2" style="display:none" onclick="return validarOrcamento()">
                        <i data-lucide="save"></i> Salvar Orçamento
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const fmtMoeda = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

// ============ WIZARD NAVEGAÇÃO ============
let etapaAtual = 1;

function setEtapa(n) {
    etapaAtual = n;
    for (let i = 1; i <= 3; i++) {
        document.getElementById('painel' + i).classList.toggle('active', i === n);
        document.getElementById('wz' + i).classList.toggle('active', i === n);
        document.getElementById('wz' + i).classList.toggle('completed', i < n);
    }
    document.getElementById('btnProximo').style.display = n === 3 ? 'none' : '';
    document.getElementById('btnVoltar').style.display = n === 1 ? 'none' : '';
    document.getElementById('btnSalvar').style.display = n === 3 ? '' : 'none';

    if (n === 3) renderResumo();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function irParaEtapa(n) {
    if (n > etapaAtual) {
        if (!validarEtapa(etapaAtual)) return;
    }
    setEtapa(n);
}

function validarEtapa(n) {
    if (n === 1) {
        if (!document.getElementById('cliente_id').value) {
            alert('Selecione o cliente para continuar.');
            return false;
        }
    } else if (n === 2) {
        if (contarSelecionados() === 0) {
            alert('Selecione pelo menos um serviço ou peça.');
            return false;
        }
    }
    return true;
}

// ============ SELEÇÃO DE ITENS (CARDS) ============
function toggleCard(card) {
    card.classList.toggle('selected');
    atualizarTotais();
}

function getCards() {
    return document.querySelectorAll('.select-card.selecionavel');
}

function cardsSelecionados() {
    return Array.from(getCards()).filter(c => c.classList.contains('selected'));
}

function contarSelecionados() {
    return cardsSelecionados().length;
}

function atualizarTotais() {
    const cards = cardsSelecionados();
    let totalPecas = 0, totalServicos = 0;

    document.getElementById('contadorItens').textContent = cards.length;

    cards.forEach(c => {
        const qtd = parseInt(c.querySelector('.qtd-select').value) || 1;
        const preco = parseFloat(c.dataset.preco) || 0;
        const subtotal = qtd * preco;
        if (c.dataset.tipo === 'peca') totalPecas += subtotal;
        else totalServicos += subtotal;
    });

    document.getElementById('totalPecas').textContent = fmtMoeda(totalPecas);
    document.getElementById('totalServicos').textContent = fmtMoeda(totalServicos);
    document.getElementById('totalGeral').textContent = fmtMoeda(totalPecas + totalServicos);

    document.getElementById('totalPecasLateral').textContent = fmtMoeda(totalPecas);
    document.getElementById('totalServicosLateral').textContent = fmtMoeda(totalServicos);
    document.getElementById('totalGeralLateral').textContent = fmtMoeda(totalPecas + totalServicos);
}

// ============ REVISÃO ============
function renderResumo() {
    const clienteSel = document.getElementById('cliente_id');
    const veiculoSel = document.getElementById('veiculo_id');
    const clienteTexto = clienteSel.options[clienteSel.selectedIndex] ? clienteSel.options[clienteSel.selectedIndex].text : '';
    const veiculoTexto = veiculoSel.value ? veiculoSel.options[veiculoSel.selectedIndex].text : 'Veículo não informado';

    document.getElementById('resumoCliente').innerHTML = `
        <div class="item-linha">
            <div class="item-tipo"><i data-lucide="user"></i></div>
            <div>
                <div class="item-nome">${clienteTexto}</div>
                <div class="item-preco">${veiculoTexto}</div>
            </div>
        </div>
    `;

    const lista = document.getElementById('listaItens');
    lista.innerHTML = '';
    const cards = cardsSelecionados();

    if (cards.length === 0) {
        lista.innerHTML = '<p class="text-muted mb-0">Nenhum item selecionado.</p>';
    }

    cards.forEach(c => {
        const nome = c.querySelector('.select-card-title').textContent.trim();
        const qtd = parseInt(c.querySelector('.qtd-select').value) || 1;
        const preco = parseFloat(c.dataset.preco) || 0;
        const subtotal = qtd * preco;
        const ehServico = c.dataset.tipo === 'servico';

        const linha = document.createElement('div');
        linha.className = 'item-linha';
        linha.innerHTML = `
            <div class="item-tipo">${ehServico ? '<i data-lucide="wrench"></i>' : '<i data-lucide="package"></i>'}</div>
            <div>
                <div class="item-nome">${nome}${ehServico ? ' (serviço)' : ''}</div>
                <div class="item-preco">${qtd} × ${fmtMoeda(preco)}</div>
            </div>
            <div class="item-preco" style="margin-left: auto">${fmtMoeda(subtotal)}</div>
            <button type="button" class="btn-remove" title="Remover" onclick="removerSelecao(this)"><i data-lucide="trash-2"></i></button>
        `;
        lista.appendChild(linha);
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
    atualizarTotais();
}

function removerSelecao(btn) {
    const lista = document.getElementById('listaItens');
    const linha = btn.closest('.item-linha');
    const idx = Array.from(linha.parentElement.children).indexOf(linha);

    const cards = cardsSelecionados();
    if (idx < cards.length) cards[idx].classList.remove('selected');

    linha.remove();
    atualizarTotais();
    renderResumo();
}

// ============ SUBMISSÃO ============
function validarOrcamento() {
    if (!validarEtapa(2)) {
        setEtapa(2);
        return false;
    }
    if (!document.getElementById('cliente_id').value) {
        alert('Selecione o cliente.');
        setEtapa(1);
        return false;
    }

    document.querySelectorAll('input[name="itens[]"]').forEach(el => el.remove());

    const form = document.getElementById('formOrcamento');
    let totalPecas = 0, totalServicos = 0;

    cardsSelecionados().forEach(c => {
        const tipo = c.dataset.tipo;
        const qtd = parseInt(c.querySelector('.qtd-select').value) || 1;
        const preco = parseFloat(c.dataset.preco) || 0;
        const subtotal = qtd * preco;
        if (tipo === 'peca') totalPecas += subtotal;
        else totalServicos += subtotal;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'itens[]';
        hidden.value = JSON.stringify({
            tipo: tipo,
            item_id: c.dataset.id,
            quantidade: qtd,
            preco: preco,
            subtotal: subtotal
        });
        form.appendChild(hidden);
    });

    const totalGeral = totalPecas + totalServicos;
    const h1 = document.createElement('input'); h1.type = 'hidden'; h1.name = 'total_pecas'; h1.value = totalPecas; form.appendChild(h1);
    const h2 = document.createElement('input'); h2.type = 'hidden'; h2.name = 'total_servicos'; h2.value = totalServicos; form.appendChild(h2);
    const h3 = document.createElement('input'); h3.type = 'hidden'; h3.name = 'total_geral'; h3.value = totalGeral; form.appendChild(h3);

    return true;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        const alvo = e.target;
        if (alvo.tagName === 'INPUT' || alvo.tagName === 'SELECT') {
            e.preventDefault();
            irParaEtapa(etapaAtual + 1);
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>