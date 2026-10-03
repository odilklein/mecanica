// =============================================
// EDITOR DE ITENS DO ORÇAMENTO
// Itens de peça e mão de obra digitados livremente.
// Persiste via API JSON (api/orcamentos.php).
//
// Configuração global antes de incluir este arquivo:
//   window.OrcamentoEditorConfig = {
//     apiUrl, id, osId, veiculoPreselecionado, itens
//   }
// =============================================

window.OrcamentoEditor = (function () {
    const config = window.OrcamentoEditorConfig || {};
    const apiUrl = config.apiUrl || ((window.CAMINHO_BASE || '') + '/api/orcamentos.php');
    let contadorItem = 0;

    function fmtMoeda(v) {
        return 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function parsePreco(txt) {
        return parseFloat(String(txt).replace('R$ ', '').replace(/\./g, '').replace(',', '.')) || 0;
    }

    function adicionarItem(opts) {
        opts = opts || {};
        const tipo = opts.tipo === 'servico' ? 'servico' : 'peca';
        contadorItem++;
        const id = contadorItem;

        const linha = document.createElement('tr');
        linha.id = 'linha_' + id;
        linha.innerHTML = `
            <td>
                <select class="form-select form-select-sm tipo-item">
                    <option value="peca" ${tipo === 'peca' ? 'selected' : ''}>Peça</option>
                    <option value="servico" ${tipo === 'servico' ? 'selected' : ''}>Serviço</option>
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm descricao-item" maxlength="200" placeholder="Descrição do item"></td>
            <td style="width:90px"><input type="number" class="form-control form-control-sm qtd-item" value="${opts.quantidade || 1}" min="1"></td>
            <td style="width:150px"><input type="text" class="form-control form-control-sm preco-item" value="R$ 0,00"></td>
            <td><strong class="subtotal-item">R$ 0,00</strong></td>
            <td style="width:40px"><button type="button" class="btn btn-link text-danger p-0 btn-remover" title="Remover"><i class="icon-base ti tabler-x"></i></button></td>
        `;

        document.getElementById('corpoItens').appendChild(linha);

        if (opts.descricao) linha.querySelector('.descricao-item').value = opts.descricao;
        if (opts.preco != null) linha.querySelector('.preco-item').value = fmtMoeda(opts.preco);
        if (opts.quantidade) linha.querySelector('.qtd-item').value = opts.quantidade;

        vincularEventos(linha, id);
        atualizarSubtotal(id);
        return id;
    }

    function vincularEventos(linha, id) {
        linha.querySelector('.descricao-item').addEventListener('input', () => atualizarSubtotal(id));
        linha.querySelector('.qtd-item').addEventListener('input', () => atualizarSubtotal(id));
        linha.querySelector('.preco-item').addEventListener('input', () => atualizarSubtotal(id));
        linha.querySelector('.tipo-item').addEventListener('change', () => calcularTotais());
        linha.querySelector('.btn-remover').addEventListener('click', () => {
            linha.remove();
            calcularTotais();
        });
    }

    function atualizarSubtotal(id) {
        const linha = document.getElementById('linha_' + id);
        if (!linha) return;
        const qtd = parseInt(linha.querySelector('.qtd-item').value) || 0;
        const preco = parsePreco(linha.querySelector('.preco-item').value);
        linha.querySelector('.subtotal-item').textContent = fmtMoeda(qtd * preco);
        calcularTotais();
    }

    function calcularTotais() {
        let tPecas = 0, tServicos = 0;
        document.querySelectorAll('#corpoItens tr').forEach(linha => {
            const tipo = linha.querySelector('.tipo-item').value;
            const subtotal = parsePreco(linha.querySelector('.subtotal-item').textContent);
            if (tipo === 'peca') tPecas += subtotal;
            else tServicos += subtotal;
        });
        document.getElementById('totalPecas').textContent = fmtMoeda(tPecas);
        document.getElementById('totalServicos').textContent = fmtMoeda(tServicos);
        document.getElementById('totalGeral').textContent = fmtMoeda(tPecas + tServicos);
    }

    function coletarItens() {
        const itens = [];
        const erros = [];
        document.querySelectorAll('#corpoItens tr').forEach(linha => {
            const tipo = linha.querySelector('.tipo-item').value;
            const descricao = linha.querySelector('.descricao-item').value.trim();
            const quantidade = parseInt(linha.querySelector('.qtd-item').value) || 0;
            const preco = parsePreco(linha.querySelector('.preco-item').value);

            if (!descricao) { erros.push('Informe a descrição de todos os itens.'); return; }
            if (quantidade <= 0) { erros.push('Quantidade deve ser maior que zero.'); return; }
            if (preco < 0) { erros.push('Preço inválido.'); return; }

            itens.push({ tipo, descricao, quantidade, preco });
        });
        return { itens, erros };
    }

    function init() {
        const existentes = config.itens && config.itens.length ? config.itens : [];
        existentes.forEach(item => {
            adicionarItem({
                tipo: item.tipo || 'peca',
                descricao: item.descricao_item || '',
                quantidade: item.quantidade || 1,
                preco: item.preco_unitario
            });
        });
        if (existentes.length === 0) adicionarItem();
        calcularTotais();
    }

    return {
        init: init,
        adicionarItem: adicionarItem,
        calcularTotais: calcularTotais
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    window.OrcamentoEditor.init();

    const form = document.getElementById('formOrcamento');
    if (!form) return;

    // Carrega veículos do cliente (pré-selecionando o existente).
    const cliente = document.getElementById('cliente_id');
    if (cliente && cliente.value && typeof carregarVeiculos === 'function') {
        carregarVeiculos(cliente.value, 'veiculo_id', config_veiculo());
    }

    function config_veiculo() {
        const cfg = window.OrcamentoEditorConfig || {};
        return cfg.veiculoPreselecionado || null;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const resultado = window.OrcamentoEditor ? obterItens() : { itens: [], erros: ['Editor indisponível.'] };
        if (resultado.erros.length) {
            mostrarErro(resultado.erros[0]);
            return;
        }

        const botao = form.querySelector('[type="submit"]');
        if (botao) { botao.disabled = true; }

        const cfg = window.OrcamentoEditorConfig || {};
        const payload = {
            acao: 'salvar',
            id: cfg.id || 0,
            os_id: cfg.osId || 0,
            cliente_id: document.getElementById('cliente_id').value,
            veiculo_id: document.getElementById('veiculo_id').value,
            data_validade: form.querySelector('[name="data_validade"]').value,
            observacoes: form.querySelector('[name="observacoes"]').value,
            itens: resultado.itens
        };

        fetch(cfg.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || '',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(r => r.json().catch(() => ({ ok: false, erro: 'Resposta inválida do servidor.' })))
            .then(dados => {
                if (!dados.ok) {
                    mostrarErro(dados.erro || 'Não foi possível salvar o orçamento.');
                    return;
                }
                window.location.href = 'detalhes.php?id=' + dados.id;
            })
            .catch(() => mostrarErro('Erro de conexão ao salvar.'))
            .finally(() => { if (botao) { botao.disabled = false; } });
    });

    // Extrai itens do DOM (reutiliza a lógica do editor).
    function obterItens() {
        const itens = [];
        const erros = [];
        document.querySelectorAll('#corpoItens tr').forEach(linha => {
            const tipo = linha.querySelector('.tipo-item').value;
            const descricao = linha.querySelector('.descricao-item').value.trim();
            const quantidade = parseInt(linha.querySelector('.qtd-item').value) || 0;
            const precoTxt = linha.querySelector('.preco-item').value;
            const preco = parseFloat(String(precoTxt).replace('R$ ', '').replace(/\./g, '').replace(',', '.')) || 0;

            if (!descricao) { erros.push('Informe a descrição de todos os itens.'); return; }
            if (quantidade <= 0) { erros.push('Quantidade deve ser maior que zero.'); return; }
            if (preco < 0) { erros.push('Preço inválido.'); return; }

            itens.push({ tipo, descricao, quantidade, preco });
        });
        if (!itens.length && !erros.length) { erros.push('Adicione pelo menos um item.'); }
        return { itens, erros };
    }

    function mostrarErro(mensagem) {
        let alerta = document.getElementById('orcamentoEditorErro');
        if (!alerta) {
            alerta = document.createElement('div');
            alerta.id = 'orcamentoEditorErro';
            alerta.className = 'alert alert-danger';
            form.parentElement.insertBefore(alerta, form);
        }
        alerta.textContent = mensagem;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
