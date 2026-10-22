// =============================================
// AUTO MECÂNICA - UTILITÁRIOS DE DOMÍNIO
// (formatadores, wizard, listas dinâmicas, filtros)
// A navegação/tema/notificações vivem em js/shell.js.
// =============================================

document.addEventListener('DOMContentLoaded', function() {
    initResponsiveFilters();
    initTableFilters();
    initDynamicLists();
    initArrastarOrdenar();
    initSuggestionToggles();
    initSelectCards();
    initColorPicker();
});

// =============================================
// FORMATAR CPF: 123.456.789-00
// =============================================
function formatarCPF(campo) {
    let valor = campo.value.replace(/\D/g, '');
    if (valor.length > 11) valor = valor.substring(0, 11);

    if (valor.length > 9) {
        valor = valor.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
    } else if (valor.length > 6) {
        valor = valor.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
    } else if (valor.length > 3) {
        valor = valor.replace(/(\d{3})(\d{1,3})/, '$1.$2');
    }
    campo.value = valor;
}

// =============================================
// FORMATAR TELEFONE: (11) 99999-9999
// =============================================
function formatarTelefone(campo) {
    let valor = campo.value.replace(/\D/g, '');
    if (valor.length > 11) valor = valor.substring(0, 11);

    if (valor.length > 10) {
        valor = valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (valor.length > 6) {
        valor = valor.replace(/(\d{2})(\d{4})(\d{1,4})/, '($1) $2-$3');
    } else if (valor.length > 2) {
        valor = valor.replace(/(\d{2})(\d{1,4})/, '($1) $2');
    }
    campo.value = valor;
}

// =============================================
// FORMATAR CEP: 00000-000
// =============================================
function formatarCep(campo) {
    var valor = campo.value.replace(/\D/g, '');
    if (valor.length > 8) valor = valor.substring(0, 8);
    if (valor.length > 5) {
        valor = valor.replace(/(\d{5})(\d{1,3})/, '$1-$2');
    }
    campo.value = valor;
}

// =============================================
// FORMATAR PLACA: ABC-1234
// =============================================
function formatarPlaca(campo) {
    let valor = campo.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (valor.length > 7) valor = valor.substring(0, 7);
    if (valor.length > 3) {
        valor = valor.substring(0, 3) + '-' + valor.substring(3);
    }
    campo.value = valor;
}

// =============================================
// FORMATAR CURRENCY: R$ 1.234,56
// =============================================
function formatarMoedaInput(campo) {
    let valor = campo.value.replace(/[^\d]/g, '');
    if (!valor) {
        campo.value = '';
        return;
    }
    valor = (parseInt(valor) / 100).toFixed(2);
    campo.value = 'R$ ' + valor.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Converter para número ao enviar formulário
function moedaParaNumero(campo) {
    let valor = campo.value.replace(/[^\d]/g, '');
    campo.value = valor ? (parseInt(valor) / 100) : 0;
    return true;
}

// =============================================
// CONFIRMAR EXCLUSÃO (uso nativo, sem libs)
// =============================================
function confirmarExclusao(formId, mensagem) {
    let texto = mensagem || 'Esta ação não pode ser desfeita!';
    if (confirm(texto)) {
        document.getElementById(formId).submit();
    }
    return false;
}

// =============================================
// FILTROS RESPONSIVOS (colapsáveis no mobile)
// =============================================
function initResponsiveFilters() {
    document.querySelectorAll('.filter-bar').forEach(function (bar) {
        if (bar.dataset.filtroPronto === '1') return;
        bar.dataset.filtroPronto = '1';

        const body = document.createElement('div');
        body.className = 'filter-body';
        while (bar.firstChild) {
            body.appendChild(bar.firstChild);
        }

        const id = 'filtros' + Math.random().toString(36).slice(2, 8);
        body.id = id;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'filter-toggle';
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', id);
        btn.innerHTML =
            '<i class="icon-base ti tabler-adjustments-horizontal"></i>' +
            '<span>Filtros</span>' +
            '<span class="filter-toggle-badge" hidden>0</span>' +
            '<span class="filter-toggle-chevron"><i class="icon-base ti tabler-chevron-down"></i></span>';

        bar.appendChild(btn);
        bar.appendChild(body);

        const badge = btn.querySelector('.filter-toggle-badge');

        function contarAtivos() {
            let n = 0;
            body.querySelectorAll('input, select').forEach(function (el) {
                if (el.type === 'hidden') return;
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.checked) n++;
                    return;
                }
                if ((el.value || '').trim() !== '') n++;
            });
            return n;
        }

        function atualizarBadge() {
            const n = contarAtivos();
            badge.textContent = String(n);
            badge.hidden = n === 0;
        }

        btn.addEventListener('click', function () {
            const aberto = bar.classList.toggle('aberto');
            btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });

        body.addEventListener('input', atualizarBadge);
        body.addEventListener('change', atualizarBadge);
        atualizarBadge();
    });
}

function buscarTabela(buscaId, tabelaId) {
    const input = document.getElementById(buscaId);
    const tabela = document.getElementById(tabelaId);
    const linhas = tabela.querySelectorAll('tbody tr');

    input.addEventListener('keyup', function() {
        const termo = this.value.toLowerCase();
        linhas.forEach(function(linha) {
            const texto = linha.textContent.toLowerCase();
            linha.style.display = texto.indexOf(termo) > -1 ? '' : 'none';
        });
    });
}

// =============================================
// CARREGAR VEÍCULOS DE UM CLIENTE E PREENCHER SELECT
// =============================================
function carregarVeiculos(clienteId, selectId, servicoId) {
    if (!clienteId) {
        document.getElementById(selectId).innerHTML = '<option value="">Selecione um cliente primeiro</option>';
        return;
    }

    fetch((window.CAMINHO_BASE || '') + '/api/veiculos.php?cliente_id=' + encodeURIComponent(clienteId))
        .then(res => res.json())
        .then(data => {
            let options = '<option value="">-- Selecione o veículo --</option>';
            data.forEach(v => {
                options += `<option value="${v.id}">${escaparHtml(v.marca)} ${escaparHtml(v.modelo)} - ${escaparHtml(v.placa)}</option>`;
            });
            document.getElementById(selectId).innerHTML = options;

            if (servicoId) {
                document.getElementById(selectId).value = servicoId;
            }
        })
        .catch(err => console.error('Erro ao carregar veículos:', err));
}

// Escapa texto antes de inserir via innerHTML
function escaparHtml(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// =============================================
// WIZARD STEP-BY-STEP
// =============================================
// =============================================
// REVISÃO (RESUMO ANTES DE CRIAR)
// =============================================
function renderReview() {
    function cap(id) {
        var el = document.getElementById(id);
        if (!el) { return ''; }
        var valor = (el.value || '').trim();
        if (el.tagName === 'SELECT' && valor !== '') {
            var opt = el.options[el.selectedIndex];
            return opt ? opt.textContent.trim() : valor;
        }
        return valor;
    }
    var prefixo = 'Não informado';

    var nome = cap('nome');
    var telefone = cap('telefone');
    var cCliente = document.getElementById('reviewCliente');
    if (cCliente) {
        cCliente.innerHTML = (nome ? escapeHtml(nome) : prefixo) + (telefone ? ' · ' + escapeHtml(telefone) : '');
    }

    var marcaModelo = [cap('marca_id'), cap('modelo_base_id'), cap('modelo_id')].filter(Boolean).join(' ');
    var placa = cap('placa');
    var cVeiculo = document.getElementById('reviewVeiculo');
    if (cVeiculo) {
        cVeiculo.innerHTML = (marcaModelo ? escapeHtml(marcaModelo) : prefixo) + (placa ? ' · <b>' + escapeHtml(placa) + '</b>' : '');
    }

    var prioridade = cap('prioridade') || 'normal';
    var rotulos = { urgente: 'Urgente', alta: 'Alta', normal: 'Normal', baixa: 'Baixa' };
    var queixa = cap('queixa_inicial') || '—';
    var obs = cap('observacoes_internas');
    var cServico = document.getElementById('reviewServico');
    if (cServico) {
        var html = '<b>' + escapeHtml(rotulos[prioridade] || prioridade) + '</b> · ' + escapeHtml(queixa);
        if (obs) html += '<br><small>Obs.: ' + escapeHtml(obs) + '</small>';
        cServico.innerHTML = html;
    }

    var funcs = [];
    document.querySelectorAll('.select-card-employee').forEach(function(card) {
        var cb = card.querySelector('input[type="checkbox"]');
        if (cb && cb.checked) {
            var nm = card.querySelector('.emp-name');
            if (nm) funcs.push(nm.textContent.trim());
        }
    });

    var tarefas = [];
    document.querySelectorAll('[data-dynamic-list-tarefas] .dynamic-list-item .item-texto').forEach(function (t) {
        var v = (t.value || '').trim();
        if (v) { tarefas.push(v); }
    });
    var pecas = [];
    document.querySelectorAll('[data-dynamic-list-pecas] .dynamic-list-item').forEach(function (linha) {
        var desc = linha.querySelector('.item-texto');
        var qtd = linha.querySelector('.item-qtd');
        var d = desc ? (desc.value || '').trim() : '';
        if (d) { pecas.push(d + ' (' + ((qtd && qtd.value) || '1') + 'x)'); }
    });

    var cEquipe = document.getElementById('reviewEquipe');
    if (cEquipe) {
        var eh = funcs.length
            ? '<b>' + funcs.length + ' funcionário' + (funcs.length > 1 ? 's' : '') + ':</b> ' + escapeHtml(funcs.join(', '))
            : '<em>Nenhum funcionário selecionado</em>';
        eh += '<br><b>Tarefas:</b> ' + (tarefas.length ? escapeHtml(tarefas.slice(0, 4).join(' | ')) + (tarefas.length > 4 ? ' …' : '') : '—');
        eh += '<br><b>Peças:</b> ' + (pecas.length ? escapeHtml(pecas.slice(0, 4).join(' | ')) + (pecas.length > 4 ? ' …' : '') : '—');
        cEquipe.innerHTML = eh;
    }
}
window.renderReview = renderReview;


// =============================================
// LISTAS DINÂMICAS (TAREFAS, PEÇAS) + SUGESTÕES
// =============================================
function initDynamicLists() {
    document.querySelectorAll('[data-dynamic-list]').forEach(function (list) {
        var input = list.querySelector('.dynamic-list-input .form-control');
        var addBtn = list.querySelector('.btn-add');
        var container = list.querySelector('.dynamic-list-items');
        var hiddenInput = list.querySelector('input[type="hidden"]');

        if (!addBtn || !container) return;

        // Listas simples (tarefas) aceitam só texto; as de peças usam
        // "descrição | quantidade" com campos editáveis.
        var comQuantidade = !list.hasAttribute('data-lista-simples');

        function itemNode(item) {
            var linha = document.createElement('div');
            linha.className = 'dynamic-list-item';

            if (comQuantidade) {
                linha.innerHTML = '<i class="icon-base ti tabler-grip-vertical item-grip"></i>'
                    + '<input type="text" class="form-control form-control-sm item-texto" maxlength="200" placeholder="Descrição da peça">'
                    + '<input type="number" class="form-control form-control-sm item-qtd" min="1" value="1" style="max-width:88px" aria-label="Quantidade">'
                    + '<button type="button" class="item-remove" title="Remover"><i class="icon-base ti tabler-x"></i></button>';
                linha.querySelector('.item-texto').value = item.descricao || '';
                if (item.quantidade) {
                    linha.querySelector('.item-qtd').value = item.quantidade;
                }
            } else {
                linha.innerHTML = '<i class="icon-base ti tabler-grip-vertical item-grip"></i>'
                    + '<input type="text" class="form-control form-control-sm item-texto" maxlength="255" placeholder="Descrição da tarefa">'
                    + '<button type="button" class="item-remove" title="Remover"><i class="icon-base ti tabler-x"></i></button>';
                linha.querySelector('.item-texto').value = item.descricao || '';
            }
            return linha;
        }

        function lerItens() {
            var itens = [];
            container.querySelectorAll('.dynamic-list-item').forEach(function (linha) {
                var campo = linha.querySelector('.item-texto');
                var descricao = ((campo && campo.value) || '').trim();
                if (!descricao) return;
                if (comQuantidade) {
                    var qtd = parseInt(linha.querySelector('.item-qtd').value, 10) || 1;
                    itens.push({ descricao: descricao, quantidade: qtd });
                } else {
                    itens.push({ descricao: descricao });
                }
            });
            return itens;
        }

        function syncList() {
            if (!hiddenInput) return;
            var itens = lerItens();
            hiddenInput.value = comQuantidade
                ? itens.map(function (i) { return i.descricao + ' | ' + i.quantidade; }).join('\n')
                : itens.map(function (i) { return i.descricao; }).join('\n');
            sincronizarSugestoes(itens);
        }

        // Sugestões usadas somem; ao remover o item correspondente, voltam.
        function marcaDe(chip) {
            // O chip pode vir como "Descrição | quantidade" (peças).
            var bruto = (chip.getAttribute('data-sugestao') || chip.textContent || '').trim();
            return bruto.split('|')[0].trim().toLowerCase();
        }

        function marcaDeItem(item) {
            return String(item.descricao || '').trim().toLowerCase();
        }

        function marcarSugestao(chip, usada) {
            if (usada) {
                chip.classList.add('suggestion-chip-used');
                chip.hidden = true;
            } else {
                chip.classList.remove('suggestion-chip-used');
                chip.hidden = false;
            }
            var bloco = chip.closest('.suggestions');
            if (bloco) {
                var visiveis = bloco.querySelectorAll('.suggestion-chip:not([hidden])').length;
                bloco.classList.toggle('sem-sugestoes', visiveis === 0);
            }
        }

        function sincronizarSugestoes(itens) {
            var existentes = itens.map(marcaDeItem);
            list.querySelectorAll('.suggestion-chip').forEach(function (chip) {
                var usada = existentes.indexOf(marcaDe(chip)) !== -1;
                marcarSugestao(chip, usada);
            });
        }

        function addItem(descricao, quantidade) {
            descricao = String(descricao || '').trim();
            if (!descricao) return;

            // Impede duplicidade (ignorando caixa).
            var jaExiste = lerItens().some(function (i) {
                return i.descricao.toLowerCase() === descricao.toLowerCase();
            });
            if (jaExiste) {
                mostrarToast('Item já adicionado: ' + descricao, 'warning');
                return;
            }

            var linha = itemNode({ descricao: descricao, quantidade: quantidade });
            if (container.hasAttribute('data-ordenavel')) {
                linha.setAttribute('draggable', 'true');
            }
            container.appendChild(linha);

            linha.querySelectorAll('input').forEach(function (campo) {
                campo.addEventListener('input', syncList);
            });
            linha.querySelector('.item-remove').addEventListener('click', function () {
                linha.remove();
                syncList();
            });
            syncList();
            if (input) { input.value = ''; input.focus(); }
        }

        if (addBtn) addBtn.addEventListener('click', function () {
            addItem(input ? input.value : '');
        });

        if (input) input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addItem(input.value);
            }
        });

        // Sugestões: some após o uso para não permitir duplicatas.
        list.querySelectorAll('.suggestion-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var bruto = chip.textContent.trim();
                var partes = bruto.split('|');
                var descricao = (partes[0] || '').trim();
                var quantidade = partes.length > 1 ? (parseInt(partes[1].trim(), 10) || 1) : 1;

                addItem(descricao, quantidade);

                // A sugestão sai da lista, mas volta se o item for removido.
                marcarSugestao(chip, true);
            });
        });

        if (hiddenInput && hiddenInput.value.trim()) {
            hiddenInput.value.trim().split('\n').forEach(function (line) {
                var partes = line.split('|');
                var descricao = (partes[0] || '').trim();
                var quantidade = partes.length > 1 ? (parseInt(partes[1].trim(), 10) || 1) : 1;
                if (descricao) { addItem(descricao, quantidade); }
            });
        }
    });
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// =============================================
// ARRASTAR PARA REORDENAR (listas dinâmicas)
// Usa a API nativa de drag-and-drop do navegador,
// sem dependências externas.
// =============================================
function initArrastarOrdenar() {
    document.querySelectorAll('.dynamic-list-items[data-ordenavel]').forEach(function (container) {
        if (container.dataset.arrastarPronto === '1') { return; }
        container.dataset.arrastarPronto = '1';

        var itemArrastando = null;

        container.addEventListener('dragstart', function (e) {
            var item = e.target.closest('.dynamic-list-item');
            if (!item) { return; }
            itemArrastando = item;
            item.classList.add('item-arrastando');
            e.dataTransfer.effectAllowed = 'move';
            // Firefox exige dataTransfer para iniciar o arraste.
            try { e.dataTransfer.setData('text/plain', ''); } catch (err) { /* noop */ }
        });

        container.addEventListener('dragover', function (e) {
            if (!itemArrastando) { return; }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            var alvo = e.target.closest('.dynamic-list-item');
            if (!alvo || alvo === itemArrastando) { return; }

            container.querySelectorAll('.item-alvo').forEach(function (n) { n.classList.remove('item-alvo'); });
            alvo.classList.add('item-alvo');

            var caixa = alvo.getBoundingClientRect();
            var depois = (e.clientY - caixa.top) > (caixa.height / 2);
            container.insertBefore(itemArrastando, depois ? alvo.nextSibling : alvo);
        });

        container.addEventListener('drop', function (e) {
            e.preventDefault();
            container.querySelectorAll('.item-alvo').forEach(function (n) { n.classList.remove('item-alvo'); });
            // A ordem mudou: sincroniza o campo oculto.
            var lista = container.closest('[data-dynamic-list]');
            if (lista && typeof sincronizarLista === 'function') {
                sincronizarLista(lista);
            }
        });

        container.addEventListener('dragend', function () {
            if (itemArrastando) { itemArrastando.classList.remove('item-arrastando'); }
            container.querySelectorAll('.item-alvo').forEach(function (n) { n.classList.remove('item-alvo'); });
            itemArrastando = null;
        });
    });
}

// Sincroniza o campo oculto de uma lista (usado após reordenar).
function sincronizarLista(lista) {
    var container = lista.querySelector('.dynamic-list-items');
    var hiddenInput = lista.querySelector('input[type="hidden"]');
    if (!container || !hiddenInput) { return; }

    var comQuantidade = !lista.hasAttribute('data-lista-simples');
    var itens = [];
    container.querySelectorAll('.dynamic-list-item').forEach(function (linha) {
        var campo = linha.querySelector('.item-texto');
        var descricao = ((campo && campo.value) || '').trim();
        if (!descricao) { return; }
        if (comQuantidade) {
            var qtd = parseInt(linha.querySelector('.item-qtd').value, 10) || 1;
            itens.push(descricao + ' | ' + qtd);
        } else {
            itens.push(descricao);
        }
    });
    hiddenInput.value = itens.join('\n');
}
function initSuggestionToggles() {
    document.querySelectorAll('.suggestions-toggle').forEach(function(btn) {
        var chips = btn.closest('.suggestions').querySelector('.suggestion-chips');
        if (!chips) return;
        var icon = btn.querySelector('.icon-base');

        function setIcons(open) {
            if (icon) icon.className = 'icon-base ti ' + (open ? 'tabler-chevron-up' : 'tabler-dots');
        }

        btn.addEventListener('click', function() {
            var open = chips.hidden;
            chips.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            setIcons(open);
        });

        setIcons(false);
    });
}

// =============================================
// SELECT CARDS (FUNCIONÁRIOS DESIGNADOS)
// =============================================
function initSelectCards() {
    document.querySelectorAll('.select-card-employee').forEach(function(card) {
        var checkbox = card.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        function sync() {
            var selected = checkbox.checked;
            card.classList.toggle('selected', selected);
            card.setAttribute('aria-pressed', selected ? 'true' : 'false');
        }

        function toggle() {
            checkbox.checked = !checkbox.checked;
            sync();
        }

        card.addEventListener('click', toggle);
        checkbox.addEventListener('change', sync);
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });
        sync();
    });
}

// =============================================
// FILTROS E BUSCA AVANÇADA EM LISTAGENS (tabelas)
// =============================================
function initTableFilters() {
    document.querySelectorAll('input[data-filtro-busca]').forEach(input => {
        const tabelaId = input.getAttribute('data-filtro-busca');
        const tabela = document.getElementById(tabelaId);
        if (!tabela) return;

        const selects = document.querySelectorAll(
            `[data-filtro-tabela="${tabelaId}"]`
        );
        const totalEl = document.querySelector(`[data-filtro-total="${tabelaId}"]`);
        const vazioEl = document.querySelector(`[data-filtro-vazio="${tabelaId}"]`);

        function temFiltroSelecao() {
            return Array.from(selects).some(sel => sel.value);
        }

        function aplicar() {
            const termo = input.value.trim().toLowerCase();
            let visiveis = 0;

            Array.from(tabela.querySelectorAll('tbody tr')).forEach(tr => {
                if (tr.classList.contains('row-filtro-vazio')) {
                    tr.style.display = (visiveis === 0 && (termo || temFiltroSelecao())) ? '' : 'none';
                    return;
                }

                if (tr.classList.contains('row-empty')) {
                    tr.style.display = (visiveis === 0 && !(termo || temFiltroSelecao())) ? '' : 'none';
                    return;
                }

                let mostrar = true;

                if (termo) {
                    mostrar = (tr.textContent || '').toLowerCase().indexOf(termo) > -1;
                }

                if (mostrar) {
                    selects.forEach(sel => {
                        if (!mostrar || !sel.value) return;

                        const dataRange = sel.getAttribute('data-filtro-data-range');
                        if (dataRange) {
                            mostrar = filtroDataRange(tr, sel.getAttribute('data-filtro-coluna'), sel.value, dataRange);
                        } else {
                            const substring = sel.hasAttribute('data-filtro-substring');
                            const td = encontrarCelula(tr, sel);
                            if (!td) return;
                            const comparar = (td.getAttribute('data-filtro-normalizado') || td.textContent || '').trim().toLowerCase();
                            mostrar = substring
                                ? comparar.indexOf(sel.value.toLowerCase()) > -1
                                : comparar === sel.value.toLowerCase();
                        }
                    });
                }

                if (mostrar) {
                    tr.style.display = '';
                    visiveis++;
                } else {
                    tr.style.display = 'none';
                }
            });

            if (totalEl) totalEl.textContent = String(visiveis);
            if (vazioEl) vazioEl.style.display = (visiveis === 0 && (termo || temFiltroSelecao())) ? '' : 'none';
        }

        input.addEventListener('input', aplicar);
        selects.forEach(sel => sel.addEventListener('change', aplicar));

        aplicar();
    });
}

function encontrarCelula(tr, sel) {
    const coluna = sel.getAttribute('data-filtro-coluna') || sel.getAttribute('data-filtro-substring');
    if (!coluna) return null;
    return tr.querySelector(`td[data-label="${coluna}"]`);
}

function filtroDataRange(tr, coluna, valor, tipo) {
    const td = tr.querySelector(`td[data-label="${coluna}"]`);
    if (!td) return false;
    const normalizado = td.getAttribute('data-filtro-normalizado') || '';
    if (!normalizado) return true;
    const hoje = new Date();
    hoje.setHours(0,0,0,0);
    const data = new Date(normalizado + 'T00:00:00');
    if (isNaN(data.getTime())) return true;
    if (valor === 'hoje') {
        return data.getTime() === hoje.getTime();
    } else if (valor === 'ultimos7') {
        const sete = new Date(hoje); sete.setDate(sete.getDate() - 7);
        return data >= sete;
    } else if (valor === 'ultimos30') {
        const trinta = new Date(hoje); trinta.setDate(trinta.getDate() - 30);
        return data >= trinta;
    } else if (valor === 'proximos7') {
        const fim = new Date(hoje); fim.setDate(fim.getDate() + 7);
        return data >= hoje && data <= fim;
    }
    return true;
}

// =============================================
// SELETOR DE COR DE VEÍCULO (paleta + picker)
// =============================================
function initColorPicker() {
    const corInput = document.getElementById('cor');
    const palette = document.getElementById('corPalette');
    if (!corInput || !palette) return;
    const picker = document.getElementById('corPicker');

    const swatches = Array.from(palette.querySelectorAll('.color-swatch[data-cor]'));

    function marcarAtivo(button) {
        swatches.forEach(function (b) { b.classList.toggle('active', b === button); });
        if (picker) picker.setAttribute('aria-pressed', button ? 'true' : 'false');
    }

    swatches.forEach(function (button) {
        button.addEventListener('click', function () {
            corInput.value = button.getAttribute('data-cor');
            if (picker) picker.value = button.getAttribute('data-hex');
            marcarAtivo(button);
        });
    });

    if (picker) {
        picker.addEventListener('input', function () {
            corInput.value = picker.value.toUpperCase();
            marcarAtivo(null);
        });
    }

    const atual = (corInput.value || '').trim();
    if (atual) {
        const normalizado = atual.toLowerCase();
        const achou = swatches.find(function (b) {
            return b.getAttribute('data-cor').toLowerCase() === normalizado;
        });
        if (achou) {
            marcarAtivo(achou);
            if (picker) picker.value = achou.getAttribute('data-hex');
        } else if (picker && /^#([0-9a-f]{6}|[0-9a-f]{3})$/i.test(atual)) {
            picker.value = atual.toUpperCase();
        } else if (picker && cor_hex_para_picker(corInput)) {
            picker.value = cor_hex_para_picker(corInput);
        }
    }
}

function cor_hex_para_picker(campo) {
    const valor = (campo.value || '').trim();
    const m = valor.match(/^#([0-9a-fA-F]{6})$/);
    if (!m) return null;
    return '#' + m[1].toLowerCase();
}

// Limpa a barra de filtros de uma listagem e reaplica
function limparFiltros(tabelaId) {
    document.querySelectorAll(`input[data-filtro-busca="${tabelaId}"]`).forEach(i => i.value = '');
    document.querySelectorAll(`[data-filtro-tabela="${tabelaId}"]`).forEach(s => s.value = '');
    document.querySelectorAll(`input[data-filtro-busca="${tabelaId}"]`).forEach(i => i.dispatchEvent(new Event('input')));
}
