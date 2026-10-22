// =============================================
// FORMULÁRIO DE VEÍCULO - CATALOGAÇÃO FIPE
// Cascata Marca -> Modelo-base -> Versão -> Ano + Valor FIPE.
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
    var selAno = document.getElementById('ano');
    var campoValor = document.getElementById('valorFipe');
    var boxFipe = document.getElementById('fipeBox');
    var textoFipe = document.getElementById('valorFipeTexto');
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

    function travar(sel, travado) {
        if (!sel) {
            return;
        }
        if (instancias[sel.id]) {
            if (travado) {
                instancias[sel.id].disable();
            } else {
                instancias[sel.id].enable();
            }
        }
        sel.disabled = travado;
    }

    function definirOpcoesAnos(sel, itens, placeholder) {
        if (instancias[sel.id]) {
            var ts = instancias[sel.id];
            ts.clear(true);
            ts.clearOptions();
            ts.addOption({ value: '', text: placeholder });
            itens.forEach(function (item) {
                ts.addOption({ value: String(item.ano), text: String(item.ano) });
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
                opt.value = String(item.ano);
                opt.textContent = String(item.ano);
                sel.appendChild(opt);
            });
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

    function carregarBases(marcaId, selecionado, anoSelecionado) {
        definirOpcoes(selBase, [], 'Carregando modelos...');
        definirOpcoes(selVersao, [], 'Selecione o modelo primeiro');
        travar(selVersao, true);
        reiniciarAnos(anoSelecionado);
        if (!marcaId) {
            definirOpcoes(selBase, [], 'Selecione a marca primeiro');
            travar(selBase, true);
            return;
        }
        travar(selBase, false);
        fetch(base + '/api/fipe.php?recurso=modelos_base&marca_id=' + encodeURIComponent(marcaId))
            .then(function (r) { return r.json(); })
            .then(function (itens) {
                definirOpcoes(selBase, itens, 'Selecione o modelo');
                if (selecionado) {
                    selecionar(selBase, selecionado);
                    carregarVersoes(selecionado, cfg.modeloId, anoSelecionado);
                } else {
                    atualizarValor();
                }
            })
            .catch(function () { definirOpcoes(selBase, [], 'Erro ao carregar modelos'); });
    }

    function carregarVersoes(baseId, selecionado, anoSelecionado) {
        definirOpcoes(selVersao, [], 'Carregando versões...');
        if (!baseId) {
            definirOpcoes(selVersao, [], 'Selecione o modelo primeiro');
            travar(selVersao, true);
            reiniciarAnos(anoSelecionado);
            atualizarValor();
            return;
        }
        travar(selVersao, false);
        fetch(base + '/api/fipe.php?recurso=versoes&base_id=' + encodeURIComponent(baseId))
            .then(function (r) { return r.json(); })
            .then(function (itens) {
                definirOpcoes(selVersao, itens, 'Versão (opcional)');
                if (selecionado) {
                    selecionar(selVersao, selecionado);
                }
                carregarAnos(selVersao.value, anoSelecionado);
            })
            .catch(function () { definirOpcoes(selVersao, [], 'Erro ao carregar versões'); });
    }

    function reiniciarAnos(anoSelecionado) {
        var alvo = parseInt(anoSelecionado || '0', 10) || 0;
        if (alvo) {
            definirOpcoesAnos(selAno, [{ ano: alvo }], 'Selecione o ano');
            selecionar(selAno, alvo);
            travar(selAno, false);
        } else {
            definirOpcoesAnos(selAno, [], 'Selecione a versão primeiro');
            travar(selAno, true);
        }
        atualizarValor();
    }

    function carregarAnos(modeloId, anoSelecionado) {
        var alvo = parseInt(anoSelecionado || '0', 10) || 0;

        if (!modeloId) {
            definirOpcoesAnos(selAno, alvo ? [{ ano: alvo }] : [], 'Selecione a versão primeiro');
            selecionar(selAno, alvo);
            travar(selAno, !alvo);
            atualizarValor();
            return;
        }

        definirOpcoesAnos(selAno, [], 'Carregando anos...');
        travar(selAno, true);
        fetch(base + '/api/fipe.php?recurso=anos&modelo_id=' + encodeURIComponent(modeloId))
            .then(function (r) { return r.json(); })
            .then(function (itens) {
                itens = Array.isArray(itens) ? itens : [];
                if (alvo && !itens.some(function (i) { return Number(i.ano) === alvo; })) {
                    itens.push({ ano: alvo });
                }
                definirOpcoesAnos(selAno, itens, 'Selecione o ano');
                selecionar(selAno, alvo);
                travar(selAno, false);
                atualizarValor();
            })
            .catch(function () { definirOpcoesAnos(selAno, [], 'Erro ao carregar anos'); });
    }

    function mostrarValor(texto) {
        if (campoValor) {
            campoValor.value = texto;
        }
        if (textoFipe) {
            textoFipe.textContent = texto || '—';
        }
        if (boxFipe) {
            if (texto) { boxFipe.classList.remove('d-none'); }
            else { boxFipe.classList.add('d-none'); }
        }
    }

    function atualizarValor() {
        if (!campoValor && !textoFipe) {
            return;
        }
        var modeloId = parseInt(selVersao.value || '0', 10);
        var ano = parseInt((selAno && selAno.value) || '0', 10);
        if (!modeloId || !ano) {
            mostrarValor(cfg.valorAtualFormatado || '');
            return;
        }
        mostrarValor('Consultando FIPE...');
        fetch(base + '/api/fipe.php?recurso=valor&modelo_id=' + modeloId + '&ano=' + ano)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.valor_formatado) {
                    mostrarValor(d.valor_formatado + (d.mes_referencia ? ' — ' + d.mes_referencia : ''));
                } else {
                    mostrarValor('Sem referência para o ano informado');
                }
            })
            .catch(function () { mostrarValor('Não foi possível consultar'); });
    }

    iniciarTomSelect(selMarca);
    iniciarTomSelect(selBase);
    iniciarTomSelect(selVersao);
    iniciarTomSelect(selAno);

    aoMudar(selMarca, function () {
        if (hiddenMarca) {
            hiddenMarca.value = textoSelecionado(selMarca);
        }
        carregarBases(selMarca.value, null, 0);
    });

    aoMudar(selBase, function () {
        if (hiddenModelo) {
            hiddenModelo.value = textoSelecionado(selBase);
        }
        carregarVersoes(selBase.value, null, 0);
    });

    aoMudar(selVersao, function () {
        carregarAnos(selVersao.value, 0);
    });

    aoMudar(selAno, atualizarValor);

    // Estado inicial (edição: pré-seleciona marca -> modelo -> versão -> ano)
    if (cfg.marcaId) {
        selecionar(selMarca, cfg.marcaId);
        carregarBases(cfg.marcaId, cfg.baseId || null, cfg.anoAtual || 0);
    } else {
        definirOpcoes(selBase, [], 'Selecione a marca primeiro');
        definirOpcoes(selVersao, [], 'Selecione o modelo primeiro');
        definirOpcoesAnos(selAno, [], 'Selecione a versão primeiro');
        travar(selBase, true);
        travar(selVersao, true);
        travar(selAno, true);
    }

    // API exposta às páginas que embutem o formulário em modal.
    // aplicar(item) repovoa a cascata para o registro em edição.
    window.VeiculoFipe = {
        aplicar: function (item) {
            item = item || {};
            var marcaId = parseInt(item.marca_id || 0, 10);
            var baseId = parseInt(item.modelo_base_id || 0, 10);
            var modeloId = parseInt(item.modelo_id || 0, 10);
            var ano = parseInt(item.ano || 0, 10);
            cfg.marcaId = marcaId;
            cfg.baseId = baseId;
            cfg.modeloId = modeloId;
            cfg.anoAtual = ano;
            cfg.valorAtualFormatado = item.valor_fipe ? '' : '';

            if (marcaId) {
                selecionar(selMarca, marcaId);
                carregarBases(marcaId, baseId || null, ano || 0);
            } else {
                if (instancias[selMarca.id]) { instancias[selMarca.id].setValue('', true); }
                else { selMarca.value = ''; }
                definirOpcoes(selBase, [], 'Selecione a marca primeiro');
                definirOpcoes(selVersao, [], 'Selecione o modelo primeiro');
                definirOpcoesAnos(selAno, [], 'Selecione a versão primeiro');
                travar(selBase, true);
                travar(selVersao, true);
                travar(selAno, true);
                mostrarValor('');
            }
        }
    };
})();
