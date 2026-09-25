// =============================================
// FORMULÁRIO DE VEÍCULO - CATALOGAÇÃO FIPE
// Cascata Marca -> Modelo-base -> Versão + Valor FIPE.
// Usa Tom Select quando disponível (busca por digitação).
// =============================================
(function () {
    'use strict';

    var cfg = window.VeiculoFipeConfig || {};
    var base = window.CAMINHO_BASE || '';

    var selMarca = document.getElementById('marca_id');
    if (!selMarca) {
        return;
    }
    var selBase = document.getElementById('modelo_base_id');
    var selVersao = document.getElementById('modelo_id');
    var inAno = document.getElementById('ano');
    var campoValor = document.getElementById('valorFipe');
    var hiddenMarca = document.getElementById('marca_nome');
    var hiddenModelo = document.getElementById('modelo_nome');

    var instancias = {};

    function iniciarTomSelect(sel) {
        if (!sel || !window.TomSelect) {
            return;
        }
        instancias[sel.id] = new TomSelect(sel, {
            allowEmptyOption: true,
            maxItems: 1,
            placeholder: sel.getAttribute('data-placeholder') || 'Selecione',
            sortField: { field: 'text', direction: 'asc' }
        });
    }

    function definirOpcoes(sel, itens, placeholder) {
        var texto = function (item) {
            return item.nome_base || item.nome_versao || item.nome_exibicao || '';
        };

        if (instancias[sel.id]) {
            var ts = instancias[sel.id];
            ts.clear(true);
            ts.clearOptions();
            ts.addOption({ value: '', text: placeholder });
            itens.forEach(function (item) {
                ts.addOption({ value: String(item.id), text: texto(item) });
            });
            ts.refreshOptions(false);
            ts.setValue('', true);
        } else {
            sel.innerHTML = '';
            var vazia = document.createElement('option');
            vazia.value = '';
            vazia.textContent = placeholder;
            sel.appendChild(vazia);
            itens.forEach(function (item) {
                var opt = document.createElement('option');
                opt.value = String(item.id);
                opt.textContent = texto(item);
                sel.appendChild(opt);
            });
        }
        sel.disabled = false;
    }

    function selecionar(sel, valor) {
        if (!valor) {
            return;
        }
        if (instancias[sel.id]) {
            instancias[sel.id].setValue(String(valor), true);
        } else {
            sel.value = String(valor);
        }
    }

    function aoMudar(sel, handler) {
        if (!sel) {
            return;
        }
        if (instancias[sel.id]) {
            instancias[sel.id].on('change', handler);
        } else {
            sel.addEventListener('change', handler);
        }
    }

    function textoSelecionado(sel) {
        if (!sel.value) {
            return '';
        }
        if (instancias[sel.id]) {
            return instancias[sel.id].getItem(sel.value).textContent || '';
        }
        var opt = sel.options[sel.selectedIndex];
        return opt ? opt.textContent : '';
    }

    function carregarBases(marcaId, selecionado) {
        definirOpcoes(selBase, [], 'Carregando modelos...');
        definirOpcoes(selVersao, [], 'Selecione o modelo primeiro');
        selVersao.disabled = true;
        if (!marcaId) {
            definirOpcoes(selBase, [], 'Selecione a marca primeiro');
            selBase.disabled = true;
            return;
        }
        fetch(base + '/api/fipe.php?recurso=modelos_base&marca_id=' + encodeURIComponent(marcaId))
            .then(function (r) { return r.json(); })
            .then(function (itens) {
                definirOpcoes(selBase, itens, 'Selecione o modelo');
                if (selecionado) {
                    selecionar(selBase, selecionado);
                    carregarVersoes(selecionado, cfg.modeloId);
                } else {
                    atualizarValor();
                }
            })
            .catch(function () { definirOpcoes(selBase, [], 'Erro ao carregar modelos'); });
    }

    function carregarVersoes(baseId, selecionado) {
        definirOpcoes(selVersao, [], 'Carregando versões...');
        if (!baseId) {
            definirOpcoes(selVersao, [], 'Selecione o modelo primeiro');
            selVersao.disabled = true;
            atualizarValor();
            return;
        }
        fetch(base + '/api/fipe.php?recurso=versoes&base_id=' + encodeURIComponent(baseId))
            .then(function (r) { return r.json(); })
            .then(function (itens) {
                definirOpcoes(selVersao, itens, 'Versão (opcional)');
                if (selecionado) {
                    selecionar(selVersao, selecionado);
                }
                atualizarValor();
            })
            .catch(function () { definirOpcoes(selVersao, [], 'Erro ao carregar versões'); });
    }

    function atualizarValor() {
        if (!campoValor) {
            return;
        }
        var modeloId = parseInt(selVersao.value || '0', 10);
        var ano = parseInt((inAno && inAno.value) || '0', 10);
        if (!modeloId || !ano) {
            campoValor.value = cfg.valorAtualFormatado || '';
            return;
        }
        campoValor.value = 'Consultando FIPE...';
        fetch(base + '/api/fipe.php?recurso=valor&modelo_id=' + modeloId + '&ano=' + ano)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.valor_formatado) {
                    campoValor.value = d.valor_formatado + (d.mes_referencia ? ' — ' + d.mes_referencia : '');
                } else {
                    campoValor.value = 'Sem referência para o ano informado';
                }
            })
            .catch(function () { campoValor.value = 'Não foi possível consultar'; });
    }

    iniciarTomSelect(selMarca);
    iniciarTomSelect(selBase);
    iniciarTomSelect(selVersao);

    aoMudar(selMarca, function () {
        if (hiddenMarca) {
            hiddenMarca.value = textoSelecionado(selMarca);
        }
        carregarBases(selMarca.value, null);
    });

    aoMudar(selBase, function () {
        if (hiddenModelo) {
            hiddenModelo.value = textoSelecionado(selBase);
        }
        carregarVersoes(selBase.value, null);
    });

    aoMudar(selVersao, atualizarValor);
    if (inAno) {
        inAno.addEventListener('input', atualizarValor);
        inAno.addEventListener('change', atualizarValor);
    }

    // Estado inicial (edição: pré-seleciona marca -> modelo -> versão)
    if (cfg.marcaId) {
        selecionar(selMarca, cfg.marcaId);
        carregarBases(cfg.marcaId, cfg.baseId || null);
    } else {
        definirOpcoes(selBase, [], 'Selecione a marca primeiro');
        definirOpcoes(selVersao, [], 'Selecione o modelo primeiro');
        selBase.disabled = true;
        selVersao.disabled = true;
    }
})();
