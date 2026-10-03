// =============================================
// STEPPER DO ATENDIMENTO (bs-stepper — vertical icons)
// Mesma abordagem do template de referência:
//   new Stepper(el, {linear: false})
//   .btn-next → next()   .btn-prev → previous()   .btn-submit → envia o form
// =============================================
(function () {
    'use strict';

    var el = document.getElementById('osStepper');
    if (!el || typeof Stepper === 'undefined') { return; }

    var form = document.getElementById('formOS');
    var stepper = new Stepper(el, { linear: false, animation: false });

    function rotuloCampo(campo) {
        var label = document.querySelector('label[for="' + campo.id + '"]');
        if (label) {
            return label.textContent.replace('*', '').trim();
        }
        return campo.name || 'campo obrigatório';
    }

    // Valida os campos [required] da etapa visível.
    function validarEtapa() {
        var conteudo = el.querySelector('.content.active');
        if (!conteudo) { return true; }
        var invalidos = conteudo.querySelectorAll('[required]');
        for (var i = 0; i < invalidos.length; i++) {
            var campo = invalidos[i];
            if (typeof campo.value !== 'string' || campo.value.trim() === '') {
                campo.focus();
                mostrarToast('Preencha o campo obrigatório "' + rotuloCampo(campo) + '".', 'warning');
                return false;
            }
        }
        return true;
    }

    el.querySelectorAll('.btn-next').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (validarEtapa()) {
                stepper.next();
            }
        });
    });

    el.querySelectorAll('.btn-prev').forEach(function (btn) {
        btn.addEventListener('click', function () {
            stepper.previous();
        });
    });

    // Revisão: é gerada sempre que a última etapa for exibida.
    el.addEventListener('shown.bs-stepper', function (evento) {
        var etapas = el.querySelectorAll('.content').length - 1;
        if (evento.detail.to === etapas && typeof renderReview === 'function') {
            renderReview();
        }
    });

    // Seções de revisão levam de volta à etapa correspondente.
    document.querySelectorAll('[data-ir-pra]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            stepper.to(parseInt(botao.getAttribute('data-ir-pra'), 10) + 1);
        });
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            // A validação final percorre todas as etapas.
            var conteudos = el.querySelectorAll('.content');
            for (var i = 0; i < conteudos.length; i++) {
                var invalidos = conteudos[i].querySelectorAll('[required]');
                for (var j = 0; j < invalidos.length; j++) {
                    if (typeof invalidos[j].value !== 'string' || invalidos[j].value.trim() === '') {
                        e.preventDefault();
                        stepper.to(i + 1);
                        setTimeout(function () { invalidos[j].focus(); }, 60);
                        mostrarToast('Preencha o campo obrigatório "' + rotuloCampo(invalidos[j]) + '".', 'warning');
                        return;
                    }
                }
            }
            e.preventDefault();
            if (typeof enviarOrdemServico === 'function') {
                enviarOrdemServico(form);
            }
        });
    }
})();