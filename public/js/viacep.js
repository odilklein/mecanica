// =============================================
// VIA CEP — preenchimento automático de endereço.
// Fonte: https://viacep.com.br
// Campos: #zip -> #address, #neighborhood, #city, #state
// =============================================
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var cep = document.getElementById('zip');
        if (!cep) {
            return;
        }

        function preencher(id, valor) {
            var el = document.getElementById(id);
            if (el && valor != null) {
                el.value = valor;
            }
        }

        function buscar() {
            var digits = cep.value.replace(/\D/g, '');
            if (digits.length !== 8) {
                return;
            }

            cep.classList.add('is-loading');
            fetch('https://viacep.com.br/ws/' + digits + '/json/')
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.erro) {
                        return;
                    }
                    preencher('address', d.logradouro || '');
                    preencher('neighborhood', d.bairro || '');
                    preencher('city', d.localidade || '');
                    preencher('state', d.uf || '');
                })
                .catch(function () {})
                .finally(function () { cep.classList.remove('is-loading'); });
        }

        cep.addEventListener('blur', buscar);
        cep.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                cep.blur();
            }
        });
    });
})();
