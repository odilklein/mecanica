// =============================================
// CLIENTES - extensões sobre o CRUD genérico
// Render da linha, KPIs, filtro de cidades e ações.
// =============================================
(function () {
    'use strict';

    var cfg = window.CrudConfig || {};

    function texto(node, valor, vazio) {
        if (node) {
            node.textContent = (valor === null || valor === undefined || valor === '') ? (vazio || '—') : String(valor);
        }
    }

    // Lista de veículos por nome, limitada para não quebrar o layout.
var MAX_CARACTERES_VEICULOS = 46;

function truncar(texto, limite) {
    texto = String(texto || '').trim();
    return texto.length > limite ? texto.slice(0, limite - 1).trimEnd() + '…' : texto;
}

cfg.preencherLinha = function (tr, item) {
        texto(tr.querySelector('.cliente-inicial'), (item.nome || '?').charAt(0).toUpperCase());
        texto(tr.querySelector('.cliente-nome'), item.nome);
        texto(tr.querySelector('.cliente-id'), 'Cliente #' + item.id);
        texto(tr.querySelector('.cliente-telefone'), item.telefone);
        texto(tr.querySelector('.cliente-email'), item.email);
        texto(tr.querySelector('.cliente-cpf'), item.cpf);

        // Endereço: rua/número + cidade (informação geográfica do cliente)
        var endereco = tr.querySelector('.cliente-endereco');
        var cidade = tr.querySelector('.cliente-cidade');
        texto(endereco, item.endereco, 'Endereço não informado');
        if (cidade) {
            cidade.textContent = item.cidade || 'Cidade não informada';
        }

        // Veículos: nomes (ex.: Gol GTI, BMW i3) em vez da contagem
        var veic = tr.querySelector('.cliente-veiculos-texto');
        if (veic) {
            var lista = (item.veiculos_lista || '').trim();
            var total = Number(item.veiculos || 0);
            if (!total) {
                veic.textContent = 'Sem veículos';
                veic.classList.add('text-body-secondary');
                veic.title = '';
            } else {
                veic.classList.remove('text-body-secondary');
                veic.textContent = truncar(lista, MAX_CARACTERES_VEICULOS);
                veic.title = lista;
            }
        }

        tr.querySelector('.acao-detalhes').href = 'detalhes.php?id=' + item.id;
        tr.querySelector('.acao-editar').addEventListener('click', function () {
            window.Crud.abrirEdicao(item.id);
        });
        tr.querySelector('.acao-excluir').addEventListener('click', function () {
            window.Crud.abrirConfirmacao('Confirmar exclusão', 'Excluir o cliente "' + item.nome + '"? Todos os dados vinculados (veículos, históricos) serão removidos.', function () {
                window.Crud.requisicao({ acao: 'excluir_lote', ids: [item.id] }).then(window.Crud.tratarResposta);
            });
        });
    };

    cfg.aposCarregar = function (dados) {
        if (dados.totais) {
            var t = document.getElementById('kpiTotal');
            var a = document.getElementById('kpiAtivos');
            var i = document.getElementById('kpiInativos');
            if (t) { t.textContent = dados.totais.total; }
            if (a) { a.textContent = dados.totais.ativos; }
            if (i) { i.textContent = dados.totais.inativos; }
        }
        var cid = document.getElementById('kpiCidades');
        if (cid) { cid.textContent = (dados.cidades || []).length; }
    };

    // ---------- Ações em massa ----------
    var bulkBar = document.querySelector('[data-crud="bulk-bar"]');
    if (bulkBar) {
        function acaoLote(ativo, feita) {
            var ids = window.Crud.idsSelecionados();
            if (!ids.length) { return; }
            window.Crud.requisicao({ acao: 'status_lote', ids: ids, ativo: ativo }).then(function (resposta) {
                if (resposta.ok) { window.Crud.mostrarToast(resposta.mensagem, 'success'); }
                window.Crud.recarregar();
            });
        }
        var btnAtivar = bulkBar.querySelector('[data-crud-acao="ativar"]');
        if (btnAtivar) { btnAtivar.addEventListener('click', function () { acaoLote(1); }); }

        var btnSuspender = bulkBar.querySelector('[data-crud-acao="suspender"]');
        if (btnSuspender) {
            btnSuspender.addEventListener('click', function () {
                var ids = window.Crud.idsSelecionados();
                if (!ids.length) { return; }
                window.Crud.abrirConfirmacao('Suspender selecionados', 'Suspender ' + ids.length + ' cliente(s)? Eles continuarão cadastrados, porém marcados como suspensos.', function () { acaoLote(0); });
            });
        }

        var btnExcluir = bulkBar.querySelector('[data-crud-acao="excluir"]');
        if (btnExcluir) {
            btnExcluir.addEventListener('click', function () {
                var ids = window.Crud.idsSelecionados();
                if (!ids.length) { return; }
                window.Crud.abrirConfirmacao('Confirmar exclusão', 'Excluir ' + ids.length + ' cliente(s) selecionado(s)? Todos os dados vinculados serão removidos.', function () {
                    window.Crud.requisicao({ acao: 'excluir_lote', ids: ids }).then(window.Crud.tratarResposta);
                });
            });
        }
    }
})();
