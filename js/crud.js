// =============================================
// CRUD GENÉRICO VIA AJAX
// -------------------------------------------------
// Motor reutilizável para as listagens modernas.
// Dirigido por window.CrudConfig, definido pela página:
//
//   {
//     apiUrl: '/api/veiculos.php',
//     idCampo: 'id',
//     singular: 'Veículo',
//     plural: 'veículos',
//     selecionavel: true,
//     porPagina: 15,
//     tplId: 'tplVeiculo',              // <template> da linha
//     preencherLinha: function(tr, item, api) { ... },  // opcional
//     aposCarregar: function(dados, api) { ... },       // opcional
//     aoNovo: function(api) { ... },                    // opcional (abre modal próprio)
//     aoEditar: function(id, item, api) { ... }         // opcional
//   }
//
// Sem dependências além do Bootstrap 5 (presente no layout).
// =============================================
(function () {
    'use strict';

    var cfg = window.CrudConfig || {};
    var apiUrl = cfg.apiUrl || '';
    var csrf = window.CSRF_TOKEN || '';

    if (!apiUrl) {
        // Falha de ordenação de scripts: avisa em vez de sumir em silêncio.
        console.error('[crud] window.CrudConfig.apiUrl não definido. Verifique se o bloco de configuração é impresso ANTES de js/crud.js.');
        return;
    }

    var tpl = cfg.tplId ? document.getElementById(cfg.tplId) : null;
    var selecionavel = cfg.selecionavel !== false && !!tpl;

    var el = {
        tbody: document.getElementById(cfg.tbodyId || 'crudTbody'),
        info: document.querySelector('[data-crud="info"]'),
        paginacao: document.querySelector('[data-crud="paginacao"]'),
        busca: document.querySelector('[data-crud="busca"]'),
        filtros: document.querySelectorAll('[data-crud-filtro]'),
        bulkBar: document.querySelector('[data-crud="bulk-bar"]'),
        bulkCount: document.querySelector('[data-crud="bulk-count"]'),
        checkTodos: document.querySelector('[data-crud="check-todos"]'),
        toast: document.querySelector('.crud-toast'),
        modalEl: document.querySelector('[data-crud="modal"]'),
        form: document.querySelector('[data-crud="form"]'),
        modalTitulo: document.querySelector('[data-crud="modal-titulo"]'),
        modalErro: document.querySelector('[data-crud="modal-erro"]'),
        confirmEl: document.querySelector('[data-crud="confirm"]') || document.getElementById('crudConfirmModal'),
        confirmTitulo: document.querySelector('[data-crud="confirm-titulo"]'),
        confirmMensagem: document.querySelector('[data-crud="confirm-mensagem"]'),
        confirmOk: document.querySelector('[data-crud="confirm-ok"]'),
        btnNovo: document.querySelector('[data-crud="novo"]')
    };

    if (!el.tbody || !tpl) {
        return;
    }

    var modal = el.modalEl ? new bootstrap.Modal(el.modalEl) : null;
    var confirmModal = el.confirmEl ? new bootstrap.Modal(el.confirmEl) : null;

    var estado = {
        pagina: 1,
        porPagina: cfg.porPagina || 15,
        filtros: {},
        selecionados: {},
        confirmarAcao: null
    };

    var buscaTimer = null;

    // ---------- Utilitários ----------
    function texto(node, valor) {
        if (node) {
            node.textContent = (valor === null || valor === undefined || valor === '') ? '—' : String(valor);
        }
    }

    function mostrarToast(mensagem, tipo) {
        if (!el.toast) {
            return;
        }
        el.toast.className = 'crud-toast show alert alert-' + (tipo || 'success');
        el.toast.textContent = mensagem;
        clearTimeout(el.toast._timer);
        el.toast._timer = setTimeout(function () {
            el.toast.classList.remove('show');
        }, 4000);
    }

    function idsSelecionados() {
        return Object.keys(estado.selecionados).filter(function (id) {
            return estado.selecionados[id];
        });
    }

    function requisicao(dados) {
        var fd = new FormData();
        Object.keys(dados).forEach(function (chave) {
            var valor = dados[chave];
            if (Array.isArray(valor)) {
                valor.forEach(function (item) { fd.append(chave + '[]', item); });
            } else {
                fd.append(chave, valor);
            }
        });
        return fetch(apiUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: fd
        }).then(function (r) {
            return r.json().catch(function () {
                return { ok: false, erro: 'Resposta inválida do servidor.' };
            });
        });
    }

    // ---------- Renderização ----------
    function preencherPadrao(tr, item) {
        // Preenche por convenção: [data-campo="nome"] recebe item.nome.
        tr.querySelectorAll('[data-campo]').forEach(function (node) {
            var campo = node.getAttribute('data-campo');
            texto(node, item[campo]);
        });
    }

    function renderLinhas(itens) {
        el.tbody.innerHTML = '';

        if (!itens.length) {
            var vazio = document.createElement('tr');
            vazio.className = 'row-empty';
            var td = document.createElement('td');
            td.colSpan = 99;
            td.innerHTML = cfg.emptyHtml || '';
            vazio.appendChild(td);
            el.tbody.appendChild(vazio);
            return;
        }

        itens.forEach(function (item) {
            var clone = tpl.content.cloneNode(true);
            var tr = clone.querySelector('tr');
            var id = item[cfg.idCampo || 'id'];
            tr.setAttribute('data-id', id);

            if (selecionavel) {
                var check = clone.querySelector('.row-check');
                if (check) {
                    check.checked = !!estado.selecionados[id];
                    check.setAttribute('aria-label', 'Selecionar ' + (item.nome || id));
                    if (check.checked) {
                        tr.classList.add('is-selected');
                    }
                    check.addEventListener('change', function () {
                        if (check.checked) {
                            estado.selecionados[id] = true;
                            tr.classList.add('is-selected');
                        } else {
                            delete estado.selecionados[id];
                            tr.classList.remove('is-selected');
                        }
                        atualizarBulk();
                    });
                }
            }

            preencherPadrao(tr, item);
            if (typeof cfg.preencherLinha === 'function') {
                cfg.preencherLinha(tr, item, api);
            }

            el.tbody.appendChild(clone);
        });
    }

    // Monta a sequência de páginas com reticências: 1 … 4 5 [6] 7 8 … 20
    // Mesma regra de includes/paginacao.php: janela sempre <= maximo.
    function paginasVisiveis(atual, total, maximo) {
        if (total <= 1) {
            return [1];
        }
        if (total <= maximo) {
            var todas = [];
            for (var t = 1; t <= total; t++) { todas.push(t); }
            return todas;
        }

        var meio = Math.floor((maximo - 1) / 2);
        var inicio = Math.min(Math.max(2, atual - meio), total - 1);
        var fim = Math.max(Math.min(total - 1, atual + meio), inicio);

        // Remove a extremidade interna mais distante enquanto exceder o limite.
        while (true) {
            var internos = fim - inicio + 1;
            var totalItens = internos + 2 + (inicio > 2 ? 1 : 0) + (fim < total - 1 ? 1 : 0);
            if (totalItens <= maximo || internos <= 1) {
                break;
            }
            if (Math.abs(inicio - atual) >= Math.abs(fim - atual)) {
                inicio++;
            } else {
                fim--;
            }
        }

        var lista = [1];
        if (inicio > 2) { lista.push('…'); }
        for (var p = inicio; p <= fim; p++) { lista.push(p); }
        if (fim < total - 1) { lista.push('…'); }
        lista.push(total);
        return lista;
    }

    function renderPaginacao(dados) {
        if (!el.paginacao) {
            return;
        }
        el.paginacao.innerHTML = '';
        if (dados.total_paginas <= 1) {
            return;
        }

        var ul = document.createElement('ul');
        ul.className = 'pagination mb-0';

        function item(rotulo, pagina, desabilitado, ativo) {
            var li = document.createElement('li');
            li.className = 'page-item' + (desabilitado ? ' disabled' : '') + (ativo ? ' active' : '');
            var conteudo;
            if (desabilitado || ativo) {
                conteudo = document.createElement('span');
                conteudo.className = 'page-link';
            } else {
                conteudo = document.createElement('a');
                conteudo.className = 'page-link';
                conteudo.href = '#';
                conteudo.addEventListener('click', function (e) {
                    e.preventDefault();
                    irParaPagina(pagina);
                });
            }
            conteudo.innerHTML = rotulo;
            li.appendChild(conteudo);
            ul.appendChild(li);
        }

        item('<i class="icon-base ti tabler-chevron-left"></i>', dados.pagina - 1, dados.pagina <= 1, false);
        paginasVisiveis(dados.pagina, dados.total_paginas, 7).forEach(function (p) {
            if (p === '…') {
                item('&hellip;', 0, true, false);
            } else {
                item(String(p), p, false, p === dados.pagina);
            }
        });
        item('<i class="icon-base ti tabler-chevron-right"></i>', dados.pagina + 1, dados.pagina >= dados.total_paginas, false);

        el.paginacao.appendChild(ul);
    }

    function atualizarBulk() {
        var ids = idsSelecionados();
        var n = ids.length;
        if (el.bulkCount) {
            el.bulkCount.textContent = n;
        }
        if (el.bulkBar) {
            el.bulkBar.hidden = n === 0;
        }
        if (el.checkTodos) {
            var checks = el.tbody.querySelectorAll('.row-check');
            el.checkTodos.checked = n > 0 && n === checks.length;
            el.checkTodos.indeterminate = n > 0 && n < checks.length;
        }
        if (el.bulkBar) {
            el.bulkBar.querySelectorAll('[data-crud-acao]').forEach(function (btn) {
                btn.disabled = n === 0;
            });
        }
    }

    // ---------- Carregamento ----------
    function carregar() {
        var params = new URLSearchParams({ acao: 'listar', pagina: estado.pagina, por_pagina: estado.porPagina });
        Object.keys(estado.filtros).forEach(function (chave) {
            if (estado.filtros[chave] !== '') {
                params.set(chave, estado.filtros[chave]);
            }
        });

        fetch(apiUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (!dados.ok) {
                    mostrarToast(dados.erro || 'Não foi possível carregar os dados.', 'danger');
                    return;
                }
                estado.pagina = dados.pagina;
                estado.porPagina = dados.por_pagina;
                estado.selecionados = {};
                renderLinhas(dados.itens);
                renderPaginacao(dados);

                if (el.info) {
                    var inicio = dados.total === 0 ? 0 : ((dados.pagina - 1) * dados.por_pagina) + 1;
                    var fim = Math.min(dados.pagina * dados.por_pagina, dados.total);
                    el.info.textContent = 'Mostrando ' + inicio + ' a ' + fim + ' de ' + dados.total + ' ' + (cfg.plural || 'registros');
                }
                atualizarBulk();

                if (typeof cfg.aposCarregar === 'function') {
                    cfg.aposCarregar(dados, api);
                }
            })
            .catch(function () {
                mostrarToast('Erro de conexão ao carregar.', 'danger');
            });
    }

    function irParaPagina(pagina) {
        estado.pagina = pagina;
        carregar();
    }

    // ---------- Modal de cadastro/edição ----------
    function limparFormulario() {
        if (!el.form) {
            return;
        }
        el.form.reset();
        var id = el.form.querySelector('[name="id"]');
        if (id) {
            id.value = '';
        }
        if (el.modalErro) {
            el.modalErro.hidden = true;
            el.modalErro.textContent = '';
        }
    }

    function abrirModal(titulo) {
        texto(el.modalTitulo, titulo);
        if (modal) {
            modal.show();
        }
    }

    function abrirNovo() {
        if (typeof cfg.aoNovo === 'function') {
            cfg.aoNovo(api);
            return;
        }
        limparFormulario();
        abrirModal('Novo ' + (cfg.singular || 'registro'));
        if (typeof cfg.aoPreencher === 'function') {
            cfg.aoPreencher(null, api);
        }
        setTimeout(function () {
            var primeiro = el.form && el.form.querySelector('input:not([type="hidden"]):not([readonly]), select, textarea');
            if (primeiro) {
                primeiro.focus();
            }
        }, 300);
    }

    function abrirEdicao(id) {
        limparFormulario();
        abrirModal('Editar ' + (cfg.singular || 'registro'));
        fetch(apiUrl + '?acao=obter&id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (!dados.ok) {
                    if (modal) {
                        modal.hide();
                    }
                    mostrarToast(dados.erro || 'Registro não encontrado.', 'danger');
                    return;
                }
                preencherFormulario(dados.item);
                if (typeof cfg.aoPreencher === 'function') {
                    cfg.aoPreencher(dados.item, api);
                }
                if (modal) {
                    modal.show();
                }
            })
            .catch(function () {
                mostrarToast('Erro ao carregar o registro.', 'danger');
            });
    }

    function preencherFormulario(item) {
        if (!el.form) {
            return;
        }
        Object.keys(item).forEach(function (chave) {
            var input = el.form.querySelector('[name="' + chave + '"]');
            if (!input) {
                return;
            }
            if (input.type === 'checkbox') {
                input.checked = !!item[chave];
            } else {
                input.value = item[chave] === null || item[chave] === undefined ? '' : item[chave];
            }
        });
    }

    function salvar(e) {
        e.preventDefault();
        var botao = el.form.querySelector('[type="submit"]');
        if (botao) {
            botao.disabled = true;
        }

        var fd = new FormData(el.form);
        fd.set('acao', 'salvar');
        el.form.querySelectorAll('input[type="checkbox"][name]').forEach(function (chk) {
            fd.set(chk.name, chk.checked ? '1' : '0');
        });

        fetch(apiUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: fd
        })
            .then(function (r) {
                return r.json().catch(function () {
                    return { ok: false, erro: 'Resposta inválida do servidor.' };
                });
            })
            .then(function (dados) {
                if (!dados.ok) {
                    if (el.modalErro) {
                        el.modalErro.textContent = dados.erro || 'Não foi possível salvar.';
                        el.modalErro.hidden = false;
                    }
                    return;
                }
                if (modal) {
                    modal.hide();
                }
                mostrarToast(dados.mensagem, 'success');
                carregar();
            })
            .catch(function () {
                if (el.modalErro) {
                    el.modalErro.textContent = 'Erro de conexão ao salvar.';
                    el.modalErro.hidden = false;
                }
            })
            .finally(function () {
                if (botao) {
                    botao.disabled = false;
                }
            });
    }

    // ---------- Confirmação ----------
    function abrirConfirmacao(titulo, mensagem, aoConfirmar) {
        texto(el.confirmTitulo, titulo);
        texto(el.confirmMensagem, mensagem);
        estado.confirmarAcao = aoConfirmar;
        if (confirmModal) {
            confirmModal.show();
        }
    }

    function tratarResposta(resposta) {
        if (!resposta.ok) {
            mostrarToast(resposta.erro || 'Não foi possível concluir a ação.', 'danger');
            return;
        }
        mostrarToast(resposta.mensagem, 'success');
        estado.pagina = 1;
        carregar();
    }

    // ---------- Eventos ----------
    if (el.busca) {
        el.busca.addEventListener('input', function () {
            clearTimeout(buscaTimer);
            buscaTimer = setTimeout(function () {
                estado.filtros[cfg.campoBusca || 'busca'] = el.busca.value.trim();
                estado.pagina = 1;
                carregar();
            }, 350);
        });
    }
    el.filtros.forEach(function (select) {
        select.addEventListener('change', function () {
            estado.filtros[select.getAttribute('data-crud-filtro')] = select.value;
            estado.pagina = 1;
            carregar();
        });
    });
    if (el.checkTodos) {
        el.checkTodos.addEventListener('change', function () {
            var marcar = el.checkTodos.checked;
            el.tbody.querySelectorAll('tr[data-id]').forEach(function (tr) {
                var check = tr.querySelector('.row-check');
                var id = tr.getAttribute('data-id');
                if (!check) {
                    return;
                }
                check.checked = marcar;
                if (marcar) {
                    estado.selecionados[id] = true;
                    tr.classList.add('is-selected');
                } else {
                    delete estado.selecionados[id];
                    tr.classList.remove('is-selected');
                }
            });
            atualizarBulk();
        });
    }
    if (el.btnNovo) {
        el.btnNovo.addEventListener('click', function (e) {
            e.preventDefault();
            abrirNovo();
        });
    }
    if (el.form) {
        el.form.addEventListener('submit', salvar);
    }
    if (el.confirmOk) {
        el.confirmOk.addEventListener('click', function () {
            if (confirmModal) {
                confirmModal.hide();
            }
            if (typeof estado.confirmarAcao === 'function') {
                estado.confirmarAcao();
            }
            estado.confirmarAcao = null;
        });
    }

    // API exposta às páginas que estendem o comportamento.
    var api = {
        carregar: carregar,
        recarregar: carregar,
        irParaPagina: irParaPagina,
        abrirNovo: abrirNovo,
        abrirEdicao: abrirEdicao,
        abrirConfirmacao: abrirConfirmacao,
        requisicao: requisicao,
        tratarResposta: tratarResposta,
        mostrarToast: mostrarToast,
        idsSelecionados: idsSelecionados,
        selecionar: function (id, marcado) {
            if (marcado) {
                estado.selecionados[id] = true;
            } else {
                delete estado.selecionados[id];
            }
            atualizarBulk();
        },
        estado: estado,
        modal: modal,
        elementos: el
    };
    window.Crud = api;

    // Adia o primeiro carregamento para permitir que as páginas registrem
    // seus hooks (preencherLinha, aposCarregar, aoPreencher) logo após
    // carregar este script, mas ainda antes do primeiro fetch.
    setTimeout(function () {
        if (typeof cfg.aoInicializar === 'function') {
            cfg.aoInicializar(api);
        }
        carregar();
    }, 0);
})();
