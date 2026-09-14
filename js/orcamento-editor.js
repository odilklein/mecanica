// =============================================
// EDITOR DE ITENS ORÇAMENTO (compartilhado: cadastrar.php e editar.php)
// Suporta itens do catálogo (peças/serviços) e itens avulsos (texto livre).
// Configuração global antes de incluir este arquivo:
//   window.OrcamentoEditorConfig = { pecas: [...], servicos: [...], itens: [...] }
// =============================================

window.OrcamentoEditor = (function () {
    const config = window.OrcamentoEditorConfig || {};
    const pecas = config.pecas || [];
    const servicos = config.servicos || [];
    let contadorItem = 0;

    function fmtMoeda(v) {
        return 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function parsePreco(txt) {
        return parseFloat(String(txt).replace('R$ ', '').replace(/\./g, '').replace(',', '.')) || 0;
    }

    function montarSelectItem(tipo, idSelecionado, descricaoAvulsa) {
        const lista = tipo === 'peca' ? pecas : servicos;
        const campoPreco = tipo === 'peca' ? 'preco_venda' : 'preco_mao_obra';
        let html = '<select class="form-select form-select-sm item-select">';
        html += '<option value="">-- Selecione --</option>';
        lista.forEach(item => {
            const sel = String(item.id) === String(idSelecionado) ? ' selected' : '';
            html += `<option value="${item.id}" data-preco="${item[campoPreco]}" ${sel}>${item.nome}${tipo === 'peca' ? ' (estoque: ' + item.quantidade + ')' : ''}</option>`;
        });
        const avulsoSel = idSelecionado === 'avulso' ? ' selected' : '';
        html += '<option value="avulso"' + avulsoSel + '>✎ Item avulso (digitar descrição)</option>';
        html += '</select>';
        const avulsoVisivel = (idSelecionado === 'avulso') || (descricaoAvulsa != null && String(descricaoAvulsa) !== '');
        html += `<input type="text" class="form-control form-control-sm descricao-avulsa mt-1" maxlength="200" placeholder="Descrição do item avulso" ${avulsoVisivel ? '' : 'hidden'}>`;
        return html;
    }

    function vincularEventos(linha, id) {
        linha.querySelector('.tipo-item').addEventListener('change', () => {
            const select = linha.querySelector('.item-select');
            const eraAvulso = select.value === 'avulso';
            const avulsoDesc = eraAvulso || !select.value ? linha.querySelector('.descricao-avulsa').value : '';
            const idSel = eraAvulso ? 'avulso' : select.value;
            const novoTipo = linha.querySelector('.tipo-item').value;
            const celItem = linha.querySelector('.cel-item');
            celItem.innerHTML = montarSelectItem(novoTipo, idSel, avulsoDesc);
            vincularEventos(linha, id);
            atualizarPreco(id);
        });

        linha.querySelector('.item-select').addEventListener('change', () => {
            const avulso = linha.querySelector('.item-select').value === 'avulso';
            linha.querySelector('.descricao-avulsa').hidden = !avulso;
            atualizarPreco(id);
        });

        linha.querySelector('.descricao-avulsa').addEventListener('input', () => atualizarSubtotal(id));
        linha.querySelector('.qtd-item').addEventListener('input', () => atualizarSubtotal(id));
        linha.querySelector('.preco-item').addEventListener('input', () => atualizarSubtotal(id));

        linha.querySelector('.btn-remover').addEventListener('click', () => {
            linha.remove();
            calcularTotais();
        });
    }

    function adicionarItem(opts) {
        opts = opts || {};
        const tipo = opts.tipo || 'peca';
        const idSelecionado = opts.item_id != null ? opts.item_id : '';
        const descricao = opts.descricao != null ? String(opts.descricao) : '';
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
            <td class="cel-item">${montarSelectItem(tipo, idSelecionado, descricao)}</td>
            <td style="width:90px"><input type="number" class="form-control form-control-sm qtd-item" value="${opts.quantidade || 1}" min="1"></td>
            <td style="width:150px"><input type="text" class="form-control form-control-sm preco-item" value="R$ 0,00"></td>
            <td><strong class="subtotal-item">R$ 0,00</strong></td>
            <td style="width:40px"><button type="button" class="btn btn-link text-danger p-0 btn-remover" title="Remover"><i data-lucide="x"></i></button></td>
        `;

        document.getElementById('corpoItens').appendChild(linha);

        if (idSelecionado === 'avulso' && descricao) {
            linha.querySelector('.descricao-avulsa').value = descricao;
        }
        if (opts.preco != null) linha.querySelector('.preco-item').value = fmtMoeda(opts.preco);
        if (opts.quantidade) linha.querySelector('.qtd-item').value = opts.quantidade;

        vincularEventos(linha, id);
        atualizarPreco(id);
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return id;
    }

    function atualizarPreco(id) {
        const linha = document.getElementById('linha_' + id);
        if (!linha) return;
        const tipo = linha.querySelector('.tipo-item').value;
        const itemSelect = linha.querySelector('.item-select');
        const precoInput = linha.querySelector('.preco-item');
        const descricaoAvulsa = linha.querySelector('.descricao-avulsa');
        const avulso = itemSelect.value === 'avulso';

        if (avulso) {
            precoInput.readOnly = false;
            descricaoAvulsa.hidden = false;
        } else if (itemSelect.value) {
            const opt = itemSelect.options[itemSelect.selectedIndex];
            precoInput.value = fmtMoeda(opt.getAttribute('data-preco'));
            precoInput.readOnly = true;
            descricaoAvulsa.hidden = true;
        } else {
            precoInput.readOnly = false;
            descricaoAvulsa.hidden = true;
        }

        const qtdInput = linha.querySelector('.qtd-item');
        qtdInput.readOnly = (tipo === 'servico' && itemSelect.value && !avulso);
        if (qtdInput.readOnly) qtdInput.value = 1;

        atualizarSubtotal(id);
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
            const itemSelect = linha.querySelector('.item-select');
            const avulso = itemSelect.value === 'avulso';
            const descricaoAvulsa = linha.querySelector('.descricao-avulsa').value.trim();
            const quantidade = parseInt(linha.querySelector('.qtd-item').value) || 0;
            const preco = parsePreco(linha.querySelector('.preco-item').value);

            if (!avulso && !itemSelect.value) { erros.push('Selecione um item ou marque como avulso.'); return; }
            if (avulso && !descricaoAvulsa) { erros.push('Itens avulsos precisam de descrição.'); return; }
            if (quantidade <= 0) { erros.push('Quantidade deve ser maior que zero.'); return; }
            if (preco < 0) { erros.push('Preço inválido.'); return; }

            itens.push(JSON.stringify({
                tipo: tipo,
                item_id: avulso ? 0 : itemSelect.value,
                descricao: avulso ? descricaoAvulsa : (itemSelect.options[itemSelect.selectedIndex]?.textContent || ''),
                quantidade: quantidade,
                preco: preco,
                subtotal: quantidade * preco
            }));
        });
        if (erros.length) { alert(erros[0]); return null; }
        return itens;
    }

    function init() {
        document.querySelectorAll('input[name="itens[]"]').forEach(el => el.remove());
        const existentes = config.itens && config.itens.length ? config.itens : [];
        existentes.forEach(item => {
            const idSel = item.tipo === 'peca' ? item.peca_id : item.servico_id;
            adicionarItem({
                tipo: item.tipo || 'peca',
                item_id: idSel || 'avulso',
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