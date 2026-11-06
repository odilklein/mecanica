// =============================================
// MÁSCARAS DE FORMULÁRIO (CPF, telefone)
// Aplica em inputs com data-mask="cpf" | "phone".
// =============================================
(function () {
    'use strict';

    function maskCpf(v) {
        v = v.replace(/\D/g, '').slice(0, 11);
        return v
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }

    function maskPhone(v) {
        v = v.replace(/\D/g, '').slice(0, 11);
        if (v.length <= 10) {
            return v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
        }
        return v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
    }

    var masks = { cpf: maskCpf, phone: maskPhone };

    function apply(input) {
        var fn = masks[input.getAttribute('data-mask')];
        if (!fn) {
            return;
        }
        var format = function () {
            input.value = fn(input.value);
        };
        input.addEventListener('input', format);
        format();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[data-mask]').forEach(apply);
    });
})();
