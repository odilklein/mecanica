// =============================================
// ORÇAMENTOS - extensões sobre o CRUD genérico
// Render da linha, KPIs e ações em massa.
// =============================================
(function () {
    'use strict';

    var cfg = window.CrudConfig || {};

    var statusBadges = {
        aberto: 'bg-label-info',
        aprovado: 'bg-label-success',
        reprovado: 'bg-label-danger',
        cancelado: 'bg-label-secondary'
    };

    function moeda(valor) {
        return Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatarData(data) {
        if (!data) { return '—'; }
        var d = new Date(data + 'T00:00:00');
        return isNaN(d.getTime()) ? data : d.toLocaleDateString('pt-BR');
    }

    cfg.preencherLinha = function (tr, item) {
        tr.querySelector('.orc-numero').textContent = '#' + String(item.numero).padStart(4, '0');
        tr.querySelector('.orc-cliente').textContent = item.cliente_nome || '—';

        var veiculo = tr.querySelector('.orc-veiculo');
        var placa = tr.querySelector('.orc-placa');
        if (item.marca) {
            veiculo.textContent = item.marca + ' ' + item.modelo;
            placa.textContent = item.placa || '';
            placa.parentElement.style.display = item.placa ? '' : 'none';
        } else {
            veiculo.textContent = '—';
            placa.parentElement.style.display = 'none';
        }

        tr.querySelector('.orc-data').textContent = formatarData(item.data_orcamento);

        var validade = tr.querySelector('.orc-validade');
        validade.textContent = formatarData(item.data_validade);
        validade.classList.toggle('text-danger', !!item.expirado);
        if (item.expirado) {
            validade.classList.add('fw-bold');
        }

        tr.querySelector('.orc-total').textContent = moeda(item.total_geral);

        var badge = tr.querySelector('.orc-status');
        badge.textContent = item.status.charAt(0).toUpperCase() + item.status.slice(1);
        badge.className = 'badge ' + (statusBadges[item.status] || 'bg-label-secondary');

        var aberto = item.status === 'aberto';
        tr.querySelector('.acao-detalhes').href = 'detalhes.php?id=' + item.id;
        tr.querySelector('.acao-imprimir').href = 'imprimir.php?id=' + item.id;
        var editar = tr.querySelector('.acao-editar');
        editar.href = 'editor.php?id=' + item.id;
        editar.style.display = aberto ? '' : 'none';
        var excluir = tr.querySelector('.acao-excluir');
        excluir.style.display = aberto ? '' : 'none';
        excluir.addEventListener('click', function () {
            window.Crud.abrirConfirmacao('Confirmar exclusão', 'Excluir o orçamento #' + String(item.numero).padStart(4, '0') + '?', function () {
                window.Crud.requisicao({ acao: 'excluir', id: item.id }).then(window.Crud.tratarResposta);
            });
        });
    };

    cfg.aposCarregar = function (dados) {
        var itens = dados.itens || [];
        var totalEl = document.getElementById('kpiTotal');
        if (totalEl && dados.total !== undefined) { totalEl.textContent = dados.total; }
        var abertos = 0, aprovados = 0, reprovados = 0;
        itens.forEach(function (i) {
            if (i.status === 'aberto') { abertos++; }
            else if (i.status === 'aprovado') { aprovados++; }
            else { reprovados++; }
        });
        var a = document.getElementById('kpiAbertos');
        var ap = document.getElementById('kpiAprovados');
        var rp = document.getElementById('kpiReprovados');
        if (a) { a.textContent = abertos; }
        if (ap) { ap.textContent = aprovados; }
        if (rp) { rp.textContent = reprovados; }
    };

    // ---------- Ações em massa ----------
    var bulkBar = document.querySelector('[data-crud="bulk-bar"]');
    if (bulkBar) {
        function aplicarStatus(status, titulo, mensagem) {
            return function () {
                var ids = window.Crud.idsSelecionados();
                if (!ids.length) { return; }
                window.Crud.abrirConfirmacao(titulo, mensagem.replace('{n}', ids.length), function () {
                    var sequencia = Promise.resolve();
                    ids.forEach(function (id) {
                        sequencia = sequencia.then(function () {
                            return window.Crud.requisicao({ acao: 'mudar_status', id: id, status: status });
                        });
                    });
                    sequencia.then(function () {
                        window.Crud.mostrarToast(ids.length + ' orçamento(s) atualizado(s).', 'success');
                        window.Crud.recarregar();
                    });
                });
            };
        }

        var btnAprovar = bulkBar.querySelector('[data-crud-acao="aprovar"]');
        if (btnAprovar) {
            btnAprovar.addEventListener('click', aplicarStatus('aprovado', 'Aprovar selecionados', 'Aprovar {n} orçamento(s) selecionado(s)?'));
        }
        var btnReprovar = bulkBar.querySelector('[data-crud-acao="reprovar"]');
        if (btnReprovar) {
            btnReprovar.addEventListener('click', aplicarStatus('reprovado', 'Reprovar selecionados', 'Reprovar {n} orçamento(s) selecionado(s)?'));
        }
        var btnExcluir = bulkBar.querySelector('[data-crud-acao="excluir"]');
        if (btnExcluir) {
            btnExcluir.addEventListener('click', function () {
                var ids = window.Crud.idsSelecionados();
                if (!ids.length) { return; }
                window.Crud.abrirConfirmacao('Confirmar exclusão', 'Excluir ' + ids.length + ' orçamento(s) selecionado(s)? Apenas orçamentos abertos serão removidos.', function () {
                    window.Crud.requisicao({ acao: 'excluir_lote', ids: ids }).then(window.Crud.tratarResposta);
                });
            });
        }
    }
})();
