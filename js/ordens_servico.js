// =============================================
// ORDENS DE SERVIÇO - extensões sobre o CRUD genérico
// Render da linha, KPIs, atualização de status e
// cancelamento em massa.
// =============================================
(function () {
    'use strict';

    var cfg = window.CrudConfig || {};
    var apiUrl = cfg.apiUrl;
    var csrf = window.CSRF_TOKEN || '';

    var prioridadeBadges = {
        urgente: 'badge-prioridade-urgente',
        alta: 'badge-prioridade-alta',
        normal: 'badge-prioridade-normal',
        baixa: 'badge-prioridade-baixa'
    };
    // Rótulos e transições vêm da fonte única do backend (config/status_os.php)
    // exposta em window.OS_STATUS pelo header.php.
    var osStatus = window.OS_STATUS || { labels: {}, transicoes: {} };
    var statusNomes = osStatus.labels || {};
    var transicoes = osStatus.transicoes || {};
    var statusBadges = {};
    Object.keys(statusNomes).forEach(function (s) {
        statusBadges[s] = 'badge-status-' + s;
    });

    function formatarData(valor) {
        if (!valor) { return '—'; }
        var d = new Date(valor.replace(' ', 'T'));
        if (isNaN(d.getTime())) { return valor; }
        return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function truncar(texto, tam) {
        texto = String(texto || '');
        return texto.length > tam ? texto.slice(0, tam - 1) + '…' : texto;
    }

    cfg.preencherLinha = function (tr, item) {
        var link = tr.querySelector('.os-link');
        link.href = 'detalhes.php?id=' + item.id;
        tr.querySelector('.os-numero').textContent = '#' + String(item.numero).padStart(4, '0');
        tr.querySelector('.os-cliente').textContent = item.cliente || '—';

        var veiculo = tr.querySelector('.os-veiculo');
        var placa = tr.querySelector('.os-placa');
        var nomeVeiculo = ((item.marca || '') + ' ' + (item.modelo || '')).trim();
        veiculo.textContent = nomeVeiculo || 'Não informado';
        placa.textContent = item.placa || '';

        var queixa = tr.querySelector('.os-queixa');
        queixa.textContent = truncar(item.queixa_inicial, 55);
        queixa.title = item.queixa_inicial || '';

        var prio = tr.querySelector('.os-prioridade');
        prio.textContent = item.prioridade.charAt(0).toUpperCase() + item.prioridade.slice(1);
        prio.className = 'badge ' + (prioridadeBadges[item.prioridade] || 'bg-label-secondary');

        var badge = tr.querySelector('.os-status');
        badge.textContent = item.status_label || item.status;
        badge.className = 'badge ' + (statusBadges[item.status] || 'bg-label-secondary');

        tr.querySelector('.os-entrada').textContent = formatarData(item.data_entrada);

        tr.querySelector('.acao-detalhes').href = 'detalhes.php?id=' + item.id;
        var btnStatus = tr.querySelector('.acao-status');
        if (btnStatus) {
            btnStatus.hidden = !cfg.podeCriar;
            btnStatus.addEventListener('click', function () { abrirStatus(item); });
        }
        var btnCancelar = tr.querySelector('.acao-cancelar');
        if (btnCancelar) {
            var podeCancelar = cfg.podeCriar && item.status !== 'cancelada';
            btnCancelar.hidden = !podeCancelar;
            btnCancelar.addEventListener('click', function () {
                window.Crud.abrirConfirmacao('Cancelar OS', 'Cancelar a OS #' + String(item.numero).padStart(4, '0') + '?', function () {
                    window.Crud.requisicao({ acao: 'mudar_status', os_id: item.id, status: 'cancelada' }).then(window.Crud.tratarResposta);
                });
            });
        }
    };

    cfg.aposCarregar = function (dados) {
        var itens = dados.itens || [];
        var totalEl = document.getElementById('kpiTotal');
        if (totalEl && dados.total !== undefined) { totalEl.textContent = dados.total; }
        var urgentes = 0, execucao = 0, aguardando = 0;
        itens.forEach(function (i) {
            if (i.prioridade === 'urgente') { urgentes++; }
            if (i.status === 'em_execucao') { execucao++; }
            if (i.status === 'aguardando_peca') { aguardando++; }
        });
        var u = document.getElementById('kpiUrgentes');
        var e = document.getElementById('kpiExecucao');
        var a = document.getElementById('kpiAguardando');
        if (u) { u.textContent = urgentes; }
        if (e) { e.textContent = execucao; }
        if (a) { a.textContent = aguardando; }
    };

    // ---------- Modal de status ----------
    var modalStatusEl = document.getElementById('osStatusModal');
    var formStatus = document.getElementById('osStatusForm');

    function abrirStatus(item) {
        formStatus.reset();
        document.getElementById('osStatusErro').hidden = true;
        formStatus.querySelector('[name="os_id"]').value = item.id;
        var select = formStatus.querySelector('[name="status"]');
        select.innerHTML = '';
        var opcoes = [item.status].concat(transicoes[item.status] || []);
        opcoes.forEach(function (s) {
            var opt = document.createElement('option');
            opt.value = s;
            opt.textContent = statusNomes[s] || s;
            select.appendChild(opt);
        });
        bootstrap.Modal.getOrCreateInstance(modalStatusEl).show();
    }

    if (formStatus) {
        formStatus.addEventListener('submit', function (e) {
            e.preventDefault();
            var botao = formStatus.querySelector('[type="submit"]');
            if (botao) { botao.disabled = true; }
            var fd = new FormData(formStatus);
            fd.set('acao', 'mudar_status');
            fetch(apiUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        var erro = document.getElementById('osStatusErro');
                        erro.textContent = dados.erro || 'Não foi possível atualizar.';
                        erro.hidden = false;
                        return;
                    }
                    bootstrap.Modal.getOrCreateInstance(modalStatusEl).hide();
                    window.Crud.mostrarToast(dados.mensagem, 'success');
                    window.Crud.recarregar();
                })
                .catch(function () {
                    var erro = document.getElementById('osStatusErro');
                    erro.textContent = 'Erro de conexão ao salvar.';
                    erro.hidden = false;
                })
                .finally(function () { if (botao) { botao.disabled = false; } });
        });
    }

    // ---------- Cancelamento em massa ----------
    var bulkBar = document.querySelector('[data-crud="bulk-bar"]');
    if (bulkBar) {
        var btnCancelar = bulkBar.querySelector('[data-crud-acao="cancelar"]');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', function () {
                var ids = window.Crud.idsSelecionados();
                if (!ids.length) { return; }
                window.Crud.abrirConfirmacao('Cancelar selecionadas', 'Cancelar ' + ids.length + ' ordem(s) de serviço selecionada(s)?', function () {
                    var sequencia = Promise.resolve();
                    ids.forEach(function (id) {
                        sequencia = sequencia.then(function () {
                            return window.Crud.requisicao({ acao: 'mudar_status', os_id: id, status: 'cancelada' });
                        });
                    });
                    sequencia.then(function () {
                        window.Crud.mostrarToast(ids.length + ' ordem(ns) cancelada(s).', 'success');
                        window.Crud.recarregar();
                    });
                });
            });
        }
    }
})();
