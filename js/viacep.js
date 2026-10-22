// =============================================
// VIA CEP — preenchimento automático de endereço
// Fonte: https://viacep.com.br
// =============================================
(function () {
    'use strict';

    var base = window.CAMINHO_BASE || '';

    function valor(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function preencher(id, v) {
        var el = document.getElementById(id);
        if (el && v) { el.value = v; }
    }

    function limparEndereco() {
        ['endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf'].forEach(function (id) {
            preencher(id, '');
        });
    }

    function buscar(cep) {
        var somenteNumeros = cep.replace(/\D/g, '');
        if (somenteNumeros.length !== 8) { return; }

        var botao = document.getElementById('cep');
        if (botao) { botao.disabled = true; }

        fetch('https://viacep.com.br/ws/' + somenteNumeros + '/json/')
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (dados.erro) {
                    mostrarToast('CEP não encontrado. Confira o número.', 'warning');
                    limparEndereco();
                    return;
                }
                preencher('endereco', dados.logradouro || '');
                preencher('bairro', dados.bairro || '');
                preencher('cidade', dados.localidade || '');
                preencher('uf', dados.uf || '');
                mostrarToast('Endereço preenchido pelo CEP.', 'success');
            })
            .catch(function () {
                mostrarToast('Não foi possível consultar o CEP.', 'danger');
            })
            .finally(function () {
                if (botao) { botao.disabled = false; }
            });
    }

    // CEP losing focus (ou Enter) dispara a consulta.
    document.addEventListener('DOMContentLoaded', function () {
        var campoCep = document.getElementById('cep');
        if (!campoCep) { return; }

        campoCep.addEventListener('blur', function () {
            buscar(campoCep.value);
        });
        campoCep.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                campoCep.blur();
            }
        });
    });
})();