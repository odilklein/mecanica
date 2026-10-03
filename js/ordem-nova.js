// =============================================
// NOVO ATENDIMENTO — envio via API JSON
// A navegação entre etapas é feita pelo bs-stepper
// (js/os-stepper.js); aqui apenas enviamos o form.
// =============================================
(function () {
    'use strict';

    var apiUrl = window.OrdemServicoApi || ((window.CAMINHO_BASE || '') + '/api/ordens_servico.php');
    var csrf = window.CSRF_TOKEN || '';

    var enviando = false;

    window.enviarOrdemServico = function (form) {
        if (enviando) { return; }

        var fd = new FormData(form);
        fd.set('acao', 'criar');

        // Tarefas/peças vêm em campos-hidden separados por quebra de linha;
        // funcionários vêm como checkboxes (funcionarios_ids[]).
        enviando = true;
        document.querySelectorAll('#osStepper .btn-submit').forEach(function (b) {
            b.disabled = true;
        });

        fetch(apiUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: fd
        })
            .then(function (r) {
                return r.json().catch(function () { return { ok: false, erro: 'Resposta inválida do servidor.' }; });
            })
            .then(function (dados) {
                if (!dados.ok) {
                    mostrarToast(dados.erro || 'Não foi possível criar a OS.', 'danger');
                    enviando = false;
                    document.querySelectorAll('#osStepper .btn-submit').forEach(function (b) {
                        b.disabled = false;
                    });
                    return;
                }
                mostrarToast(dados.mensagem || 'Ordem de serviço criada!', 'success');
                window.location.href = 'listar.php';
            })
            .catch(function () {
                mostrarToast('Erro de conexão ao criar a OS.', 'danger');
                enviando = false;
                document.querySelectorAll('#osStepper .btn-submit').forEach(function (b) {
                    b.disabled = false;
                });
            });
    };
})();