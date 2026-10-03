// =============================================
// VEÍCULOS - extensões sobre o CRUD genérico
// Render da linha (cor, KM, FIPE), KPIs e
// inicialização da cascata FIPE no modal.
// =============================================
(function () {
    'use strict';

    var cfg = window.CrudConfig || {};

    function moeda(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '—';
        }
        return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function numero(valor) {
        return Number(valor || 0).toLocaleString('pt-BR');
    }

    cfg.preencherLinha = function (tr, item) {
        var nome = tr.querySelector('.veiculo-nome');
        var versao = tr.querySelector('.veiculo-versao');
        if (nome) {
            nome.textContent = (item.marca || '') + ' ' + (item.modelo || '');
        }
        if (versao) {
            versao.textContent = item.fipe_mes_referencia ? 'FIPE ' + item.fipe_mes_referencia : '';
        }

        tr.querySelector('.veiculo-placa').textContent = item.placa || '—';
        tr.querySelector('.veiculo-cliente').textContent = item.cliente_nome || '—';
        tr.querySelector('.veiculo-ano').textContent = item.ano || '—';

        var corBadge = tr.querySelector('.veiculo-cor-badge');
        var corTexto = tr.querySelector('.veiculo-cor');
        if (item.cor_hex) {
            corBadge.style.backgroundColor = item.cor_hex;
            corBadge.style.display = '';
        } else {
            corBadge.style.display = 'none';
        }
        corTexto.textContent = item.cor || '—';

        tr.querySelector('.veiculo-km').textContent = numero(item.km_atual) + ' km';
        tr.querySelector('.veiculo-fipe').textContent = moeda(item.valor_fipe);

        var btnEditar = tr.querySelector('.acao-editar');
        if (btnEditar) {
            btnEditar.addEventListener('click', function () {
                window.Crud.abrirEdicao(item.id);
            });
        }
        var btnExcluir = tr.querySelector('.acao-excluir');
        if (btnExcluir) {
            btnExcluir.addEventListener('click', function () {
                window.Crud.abrirConfirmacao('Confirmar exclusão', 'Excluir o veículo ' + item.marca + ' ' + item.modelo + ' (' + item.placa + ')?', function () {
                    window.Crud.requisicao({ acao: 'excluir', id: item.id }).then(window.Crud.tratarResposta);
                });
            });
        }
    };

    cfg.aposCarregar = function (dados) {
        var totalEl = document.getElementById('kpiTotal');
        if (totalEl && dados.total !== undefined) {
            totalEl.textContent = dados.total;
        }
        var comFipe = 0;
        var somaKm = 0;
        var itens = dados.itens || [];
        itens.forEach(function (item) {
            if (item.valor_fipe) {
                comFipe++;
            }
            somaKm += Number(item.km_atual || 0);
        });
        var n = itens.length;
        var kpiFipe = document.getElementById('kpiFipe');
        var kpiSemFipe = document.getElementById('kpiSemFipe');
        var kpiKm = document.getElementById('kpiKm');
        if (kpiFipe) { kpiFipe.textContent = comFipe; }
        if (kpiSemFipe) { kpiSemFipe.textContent = Math.max(0, n - comFipe); }
        if (kpiKm) { kpiKm.textContent = n ? numero(Math.round(somaKm / n)) + ' km' : '—'; }
    };

    // A cascata FIPE (js/veiculo-fipe.js) expõe window.VeiculoFipe.aplicar.
    // Chamado após o formulário ser preenchido (novo ou edição).
    cfg.aoPreencher = function (item) {
        if (window.VeiculoFipe && typeof window.VeiculoFipe.aplicar === 'function') {
            window.VeiculoFipe.aplicar(item || {});
        }
        // Ressincroniza os seletores de cor com o valor atual do campo.
        if (typeof initColorPicker === 'function') {
            initColorPicker();
        }
    };

    // ------- Ações em massa -------
    var bulkBar = document.querySelector('[data-crud="bulk-bar"]');
    if (bulkBar) {
        var btnExcluir = bulkBar.querySelector('[data-crud-acao="excluir"]');
        if (btnExcluir) {
            btnExcluir.addEventListener('click', function () {
                var ids = window.Crud.idsSelecionados();
                if (!ids.length) { return; }
                window.Crud.abrirConfirmacao('Confirmar exclusão', 'Excluir ' + ids.length + ' veículo(s) selecionado(s)?', function () {
                    window.Crud.requisicao({ acao: 'excluir_lote', ids: ids }).then(window.Crud.tratarResposta);
                });
            });
        }
    }
})();
