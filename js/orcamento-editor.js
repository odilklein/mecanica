// =============================================
// EDITOR DE ITENS DO ORÇAMENTO (manual)
// Itens de peça e mão de obra digitados livremente.
// Configuração global antes de incluir este arquivo:
//   window.OrcamentoEditorConfig = { itens: [...] }
// =============================================

window.OrcamentoEditor = (function () {
    const config = window.OrcamentoEditorConfig || {};
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
            <td style="width:40px"><button type="button" class="btn btn-link text-danger p-0 btn-remover" title="Remover"><i data-lucide="x"></i></button></td>
        `;

        document.getElementById('corpoItens').appendChild(linha);

        if (opts.descricao) linha.querySelector('.descricao-item').value = opts.descricao;
        if (opts.preco != null) linha.querySelector('.preco-item').value = fmtMoeda(opts.preco);
        if (opts.quantidade) linha.querySelector('.qtd-item').value = opts.quantidade;

        vincularEventos(linha, id);
        atualizarSubtotal(id);
        if (typeof lucide !== 'undefined') lucide.createIcons();
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

    function itensParaSubmit() {
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

            itens.push(JSON.stringify({
                tipo: tipo,
                descricao: descricao,
                quantidade: quantidade,
                preco: preco
            }));
        });
        if (erros.length) { alert(erros[0]); return null; }
        return itens;
    }

    function init() {
        document.querySelectorAll('input[name="itens[]"]').forEach(el => el.remove());
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
        itensParaSubmit: itensParaSubmit,
        calcularTotais: calcularTotais
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    window.OrcamentoEditor.init();

    const form = document.getElementById('formOrcamento');
    if (form) {
        form.addEventListener('submit', function (e) {
            document.querySelectorAll('input[name="itens[]"]').forEach(el => el.remove());
            const itens = window.OrcamentoEditor.itensParaSubmit();
            if (itens === null) {
                e.preventDefault();
                return;
            }
            itens.forEach(v => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'itens[]';
                input.value = v;
                form.appendChild(input);
            });
        });
    }
});
