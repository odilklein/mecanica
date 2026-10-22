// =============================================
// FUNCIONÁRIOS - extensões sobre o CRUD genérico
// =============================================
(function () {
    'use strict';

    var cfg = window.CrudConfig || {};

    var perfis = {
        admin: { label: 'Administrador', badge: 'bg-label-primary' },
        secretaria: { label: 'Secretaria', badge: 'bg-label-info' },
        mecanico_chefe: { label: 'Mecânico-chefe', badge: 'bg-label-warning' },
        mecanico: { label: 'Mecânico', badge: 'bg-label-secondary' }
    };

    function texto(node, valor) {
        if (node) {
            node.textContent = (valor === null || valor === undefined || valor === '') ? '—' : String(valor);
        }
    }

    function formatarSalario(valor) {
        if (valor === null || valor === undefined || valor === '') { return '—'; }
        return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatarData(data) {
        if (!data) { return '—'; }
        var d = new Date(data + 'T00:00:00');
        return isNaN(d.getTime()) ? data : d.toLocaleDateString('pt-BR');
    }

    cfg.preencherLinha = function (tr, func) {
        texto(tr.querySelector('.func-inicial'), (func.nome || '?').charAt(0).toUpperCase());
        texto(tr.querySelector('.func-nome'), func.nome);
        texto(tr.querySelector('.func-email'), func.email);
        texto(tr.querySelector('.func-telefone'), func.telefone);

        var perfil = perfis[func.tipo] || { label: func.tipo, badge: 'bg-label-secondary' };
        var badge = tr.querySelector('.func-perfil');
        badge.textContent = perfil.label;
        badge.className = 'badge func-perfil me-1 ' + perfil.badge;
        texto(tr.querySelector('.func-cargo'), func.cargo);

        texto(tr.querySelector('.func-salario'), formatarSalario(func.salario));
        texto(tr.querySelector('.func-admissao'), formatarData(func.data_admissao));

        var statusBox = tr.querySelector('.status-dot');
        statusBox.classList.toggle('on', !!func.ativo);
        statusBox.classList.toggle('off', !func.ativo);
        texto(statusBox.querySelector('.status-text'), func.ativo ? 'Ativo' : 'Suspenso');

        var toggle = tr.querySelector('.toggle-ativo');
        toggle.checked = !!func.ativo;
        toggle.addEventListener('change', function () {
            var novo = toggle.checked;
            window.Crud.requisicao({ acao: 'status', id: func.id, ativo: novo ? 1 : 0 }).then(function (resposta) {
                if (!resposta.ok) {
                    toggle.checked = !novo;
                    window.Crud.mostrarToast(resposta.erro || 'Não foi possível atualizar o status.', 'danger');
                    return;
                }
                func.ativo = novo;
                statusBox.classList.toggle('on', novo);
                statusBox.classList.toggle('off', !novo);
                texto(statusBox.querySelector('.status-text'), novo ? 'Ativo' : 'Suspenso');
                window.Crud.mostrarToast(resposta.mensagem, 'success');
            });
        });

        tr.querySelector('.acao-editar').addEventListener('click', function () {
            window.Crud.abrirEdicao(func.id);
        });
    };

    cfg.aoPreencher = function (item) {
        // Senha só é obrigatória no cadastro.
        var senha = document.querySelector('#funcModal [name="senha"]');
        if (senha) {
            if (item) { senha.removeAttribute('required'); }
            else { senha.setAttribute('required', 'required'); }
        }
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
        var admins = 0;
        (dados.itens || []).forEach(function (f) { if (f.tipo === 'admin' && f.ativo) { admins++; } });
        var kpiAdmins = document.getElementById('kpiAdmins');
        if (kpiAdmins) { kpiAdmins.textContent = admins; }
    };

    // ---------- Ações em massa ----------
    var bulkBar = document.querySelector('[data-crud="bulk-bar"]');
    if (bulkBar) {
        function acaoLote(ativo) {
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
                window.Crud.abrirConfirmacao('Desativar selecionados', 'Desativar ' + ids.length + ' funcionário(s)? Eles perderão o acesso ao sistema.', function () { acaoLote(0); });
            });
        }
    }
})();
