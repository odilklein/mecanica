// =============================================
// PEDIDOS DE PEÇAS - extensões sobre o CRUD genérico
// Render da linha, KPIs e modais de atualização e
// recebimento (com transições de status).
// =============================================
(function () {
    'use strict';

    var cfg = window.CrudConfig || {};
    var apiUrl = cfg.apiUrl;
    var csrf = window.CSRF_TOKEN || '';

    var statusNomes = {
        solicitado: 'Aguardando validação',
        validado: 'Validado',
        pedido: 'Pedido realizado',
        a_caminho: 'A caminho',
        recebido: 'Recebido',
        cancelado: 'Cancelado'
    };
    var statusBadges = {
        solicitado: 'badge-status-solicitado',
        validado: 'badge-status-validado',
        pedido: 'badge-status-pedido',
        a_caminho: 'badge-status-a_caminho',
        recebido: 'badge-status-recebido',
        cancelado: 'badge-status-cancelado'
    };
    var transicoes = {
        solicitado: ['validado', 'cancelado'],
        validado: ['pedido', 'cancelado'],
        pedido: ['a_caminho', 'cancelado'],
        a_caminho: ['recebido'],
        recebido: [],
        cancelado: []
    };

    function formatarData(data) {
        if (!data) { return '—'; }
        var d = new Date(data + 'T00:00:00');
        return isNaN(d.getTime()) ? data : d.toLocaleDateString('pt-BR');
    }

    function escapar(texto) {
        return String(texto === null || texto === undefined ? '' : texto)
            .replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
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
            return r.json().catch(function () { return { ok: false, erro: 'Resposta inválida do servidor.' }; });
        });
    }

    function obter(id) {
        return fetch(apiUrl + '?acao=obter&id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); });
    }

    function mostrarErro(idEl, mensagem) {
        var el = document.getElementById(idEl);
        if (el) {
            el.textContent = mensagem;
            el.hidden = false;
        }
    }

    // ---------- Render ----------
    cfg.preencherLinha = function (tr, item) {
        tr.querySelector('.pedido-id').textContent = '#' + item.id;

        var link = tr.querySelector('.pedido-os-link');
        link.href = '../ordens_servico/detalhes.php?id=' + item.os_id;
        tr.querySelector('.pedido-os').textContent = 'OS #' + String(item.os_numero).padStart(4, '0');

        var cliente = tr.querySelector('.pedido-cliente');
        if (cfg.ehMecanico) {
            cliente.textContent = '';
        } else {
            cliente.textContent = (item.cliente || '') + (item.placa ? ' · ' + item.placa : '');
        }

        tr.querySelector('.pedido-itens').textContent = item.itens || '—';
        tr.querySelector('.pedido-fornecedor').textContent = cfg.ehMecanico ? 'Restrito' : (item.fornecedor || '—');
        tr.querySelector('.pedido-previsao').textContent = formatarData(item.previsao_entrega);

        var badge = tr.querySelector('.pedido-status');
        badge.textContent = statusNomes[item.status] || item.status;
        badge.className = 'badge ' + (statusBadges[item.status] || 'bg-label-secondary');

        var btnAtualizar = tr.querySelector('.acao-atualizar');
        if (btnAtualizar) {
            btnAtualizar.hidden = cfg.ehMecanico;
            btnAtualizar.addEventListener('click', function () { abrirAtualizar(item.id); });
        }
        var btnReceber = tr.querySelector('.acao-receber');
        if (btnReceber) {
            var podeReceber = !cfg.ehMecanico && ['validado', 'pedido', 'a_caminho'].indexOf(item.status) !== -1;
            btnReceber.hidden = !podeReceber;
            btnReceber.addEventListener('click', function () { abrirReceber(item.id); });
        }
    };

    cfg.aposCarregar = function (dados) {
        var itens = dados.itens || [];
        var totalEl = document.getElementById('kpiTotal');
        if (totalEl && dados.total !== undefined) { totalEl.textContent = dados.total; }
        var pendentes = 0, recebidos = 0, cancelados = 0;
        itens.forEach(function (i) {
            if (i.status === 'recebido') { recebidos++; }
            else if (i.status === 'cancelado') { cancelados++; }
            else { pendentes++; }
        });
        var p = document.getElementById('kpiPendentes');
        var r = document.getElementById('kpiRecebidos');
        var c = document.getElementById('kpiCancelados');
        if (p) { p.textContent = pendentes; }
        if (r) { r.textContent = recebidos; }
        if (c) { c.textContent = cancelados; }
    };

    // ---------- Modal novo (submit próprio) ----------
    var modalNovoEl = document.getElementById('pedidoModal');
    var formNovo = document.getElementById('pedidoNovoForm');

    cfg.aoNovo = function () {
        if (formNovo) { formNovo.reset(); }
        var erro = modalNovoEl.querySelector('[data-crud="modal-erro"]');
        if (erro) { erro.hidden = true; }
        var osId = new URLSearchParams(window.location.search).get('os_id');
        if (osId) {
            var select = formNovo.querySelector('[name="os_id"]');
            if (select) { select.value = osId; }
        }
        bootstrap.Modal.getOrCreateInstance(modalNovoEl).show();
    };

    // Abre o modal automaticamente quando vier de uma OS (?os_id=).
    if (new URLSearchParams(window.location.search).get('os_id')) {
        cfg.aoNovo();
    }

    if (formNovo) {
        formNovo.addEventListener('submit', function (e) {
            e.preventDefault();
            var botao = formNovo.querySelector('[type="submit"]');
            if (botao) { botao.disabled = true; }
            var fd = new FormData(formNovo);
            fd.set('acao', 'criar');
            fetch(apiUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        var erro = modalNovoEl.querySelector('[data-crud="modal-erro"]');
                        if (erro) { erro.textContent = dados.erro || 'Não foi possível criar o pedido.'; erro.hidden = false; }
                        return;
                    }
                    bootstrap.Modal.getOrCreateInstance(modalNovoEl).hide();
                    window.Crud.mostrarToast(dados.mensagem, 'success');
                    window.Crud.recarregar();
                })
                .catch(function () {
                    var erro = modalNovoEl.querySelector('[data-crud="modal-erro"]');
                    if (erro) { erro.textContent = 'Erro de conexão ao salvar.'; erro.hidden = false; }
                })
                .finally(function () { if (botao) { botao.disabled = false; } });
        });
    }

    // ---------- Atualizar ----------
    var modalAtualizarEl = document.getElementById('pedidoAtualizarModal');
    var formAtualizar = document.getElementById('pedidoAtualizarForm');

    function abrirAtualizar(id) {
        obter(id).then(function (dados) {
            if (!dados.ok) {
                window.Crud.mostrarToast(dados.erro || 'Pedido não encontrado.', 'danger');
                return;
            }
            var item = dados.item;
            formAtualizar.reset();
            formAtualizar.querySelector('[name="id"]').value = item.id;
            formAtualizar.querySelector('[name="fornecedor"]').value = item.fornecedor || '';
            formAtualizar.querySelector('[name="previsao_entrega"]').value = item.previsao_entrega || '';
            formAtualizar.querySelector('[name="observacoes"]').value = item.observacoes || '';

            var select = formAtualizar.querySelector('[name="status"]');
            var permitidos = [item.status].concat(transicoes[item.status] || []);
            select.innerHTML = '';
            permitidos.forEach(function (s) {
                var opt = document.createElement('option');
                opt.value = s;
                opt.textContent = statusNomes[s] || s;
                select.appendChild(opt);
            });

            bootstrap.Modal.getOrCreateInstance(modalAtualizarEl).show();
        });
    }

    if (formAtualizar) {
        formAtualizar.addEventListener('submit', function (e) {
            e.preventDefault();
            var botao = formAtualizar.querySelector('[type="submit"]');
            if (botao) { botao.disabled = true; }
            var fd = new FormData(formAtualizar);
            fd.set('acao', 'atualizar');
            fetch(apiUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        mostrarErro('pedidoAtualizarErro', dados.erro || 'Não foi possível atualizar.');
                        return;
                    }
                    bootstrap.Modal.getOrCreateInstance(modalAtualizarEl).hide();
                    window.Crud.mostrarToast(dados.mensagem, 'success');
                    window.Crud.recarregar();
                })
                .catch(function () { mostrarErro('pedidoAtualizarErro', 'Erro de conexão ao salvar.'); })
                .finally(function () { if (botao) { botao.disabled = false; } });
        });
    }

    // ---------- Receber ----------
    var modalReceberEl = document.getElementById('pedidoReceberModal');
    var formReceber = document.getElementById('pedidoReceberForm');

    function abrirReceber(id) {
        obter(id).then(function (dados) {
            if (!dados.ok) {
                window.Crud.mostrarToast(dados.erro || 'Pedido não encontrado.', 'danger');
                return;
            }
            var item = dados.item;
            formReceber.reset();
            formReceber.querySelector('[name="id"]').value = item.id;
            var tbody = document.getElementById('pedidoReceberTbody');
            tbody.innerHTML = '';
            (item.itens_lista || []).forEach(function (it) {
                var pendente = Number(it.quantidade) - Number(it.recebido);
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + escapar(it.descricao) + '</td>'
                    + '<td>' + it.quantidade + '</td>'
                    + '<td>' + it.recebido + '</td>'
                    + '<td style="width:180px"><input type="number" class="form-control form-control-sm" min="0" max="' + Math.max(0, pendente) + '" value="0" ' + (pendente <= 0 ? 'disabled' : '') + ' data-item-id="' + it.id + '"></td>';
                tbody.appendChild(tr);
            });
            bootstrap.Modal.getOrCreateInstance(modalReceberEl).show();
        });
    }

    if (formReceber) {
        formReceber.addEventListener('submit', function (e) {
            e.preventDefault();
            var botao = formReceber.querySelector('[type="submit"]');
            if (botao) { botao.disabled = true; }
            var fd = new FormData(formReceber);
            fd.set('acao', 'receber');
            formReceber.querySelectorAll('[data-item-id]').forEach(function (input) {
                fd.append('qtd[' + input.getAttribute('data-item-id') + ']', input.value);
            });
            fetch(apiUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        mostrarErro('pedidoReceberErro', dados.erro || 'Não foi possível registrar o recebimento.');
                        return;
                    }
                    bootstrap.Modal.getOrCreateInstance(modalReceberEl).hide();
                    window.Crud.mostrarToast(dados.mensagem, 'success');
                    window.Crud.recarregar();
                })
                .catch(function () { mostrarErro('pedidoReceberErro', 'Erro de conexão ao salvar.'); })
                .finally(function () { if (botao) { botao.disabled = false; } });
        });
    }
})();
