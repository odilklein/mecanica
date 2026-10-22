// =============================================
// DETALHES DA OS - formulários via API JSON
// Intercepta os forms marcados com data-os-acao.
// =============================================
(function () {
    'use strict';

    var apiUrl = window.OrdemServicoDetalheApi || ((window.CAMINHO_BASE || '') + '/api/ordens_servico.php');
    var csrf = window.CSRF_TOKEN || '';

    function enviar(form, containerErro) {
        var botao = form.querySelector('[type="submit"]');
        if (botao) { botao.disabled = true; }
        var fd = new FormData(form);
        fd.set('acao', form.getAttribute('data-os-acao'));

        fetch(apiUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: fd
        })
            .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inválida do servidor.' }; }); })
            .then(function (dados) {
                if (!dados.ok) {
                    alert(dados.erro || 'Não foi possível concluir a ação.');
                    if (botao) { botao.disabled = false; }
                    return;
                }
                window.location.reload();
            })
            .catch(function () {
                alert('Erro de conexão.');
                if (botao) { botao.disabled = false; }
            });
    }

    document.querySelectorAll('form[data-os-acao]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            enviar(form);
        });

        // Tarefas: o select envia automaticamente ao mudar.
        var selectTarefa = form.querySelector('[name="tarefa_status"]');
        if (selectTarefa) {
            selectTarefa.addEventListener('change', function () {
                enviar(form);
            });
        }
    });
})();
