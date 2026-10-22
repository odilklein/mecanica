// =============================================
// DETALHES DO ORÇAMENTO - aprovar/reprovar via API
// =============================================
(function () {
    'use strict';

    var apiUrl = window.OrcamentoDetalheApi || ((window.CAMINHO_BASE || '') + '/api/orcamentos.php');
    var csrf = window.CSRF_TOKEN || '';

    var rotulos = { aprovado: 'Aprovar', reprovado: 'Reprovar' };

    document.querySelectorAll('[data-orc-status]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var status = btn.getAttribute('data-orc-status');
            var id = btn.getAttribute('data-orc-id');
            if (!confirm(rotulos[status] + ' este orçamento?')) { return; }

            var fd = new FormData();
            fd.set('acao', 'mudar_status');
            fd.set('id', id);
            fd.set('status', status);

            btn.disabled = true;
            fetch(apiUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        alert(dados.erro || 'Não foi possível atualizar o orçamento.');
                        btn.disabled = false;
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    alert('Erro de conexão ao salvar.');
                    btn.disabled = false;
                });
        });
    });
})();
