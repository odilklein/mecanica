// =============================================
// AUTO MECÂNICA - SCRIPTS PRINCIPAIS
// =============================================

// Inicializar tooltips do Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// =============================================
// FORMATAR CPF: 123.456.789-00
// =============================================
function formatarCPF(campo) {
    let valor = campo.value.replace(/\D/g, '');
    if (valor.length > 11) valor = valor.substring(0, 11);

    if (valor.length > 9) {
        valor = valor.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
    } else if (valor.length > 6) {
        valor = valor.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
    } else if (valor.length > 3) {
        valor = valor.replace(/(\d{3})(\d{1,3})/, '$1.$2');
    }
    campo.value = valor;
}

// =============================================
// FORMATAR TELEFONE: (11) 99999-9999
// =============================================
function formatarTelefone(campo) {
    let valor = campo.value.replace(/\D/g, '');
    if (valor.length > 11) valor = valor.substring(0, 11);

    if (valor.length > 10) {
        valor = valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (valor.length > 6) {
        valor = valor.replace(/(\d{2})(\d{4})(\d{1,4})/, '($1) $2-$3');
    } else if (valor.length > 2) {
        valor = valor.replace(/(\d{2})(\d{1,4})/, '($1) $2');
    }
    campo.value = valor;
}

// =============================================
// FORMATAR PLACA: ABC-1234
// =============================================
function formatarPlaca(campo) {
    let valor = campo.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (valor.length > 7) valor = valor.substring(0, 7);
    if (valor.length > 3) {
        valor = valor.substring(0, 3) + '-' + valor.substring(3);
    }
    campo.value = valor;
}

// =============================================
// FORMATAR CURRENCY: R$ 1.234,56
// =============================================
function formatarMoedaInput(campo) {
    let valor = campo.value.replace(/[^\d]/g, '');
    if (!valor) {
        campo.value = '';
        return;
    }
    valor = (parseInt(valor) / 100).toFixed(2);
    campo.value = 'R$ ' + valor.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Converter para número ao enviar formulário
function moedaParaNumero(campo) {
    let valor = campo.value.replace(/[^\d]/g, '');
    campo.value = valor ? (parseInt(valor) / 100) : 0;
    return true;
}

// =============================================
// CONFIRMAR EXCLUSÃO (uso nativo, sem libs)
// =============================================
function confirmarExclusao(formId, mensagem) {
    let texto = mensagem || 'Esta ação não pode ser desfeita!';
    if (confirm(texto)) {
        document.getElementById(formId).submit();
    }
    return false;
}

// =============================================
// BUSCA EM TEMPO REAL (tabelas)
// =============================================
function buscarTabela(buscaId, tabelaId) {
    const input = document.getElementById(buscaId);
    const tabela = document.getElementById(tabelaId);
    const linhas = tabela.querySelectorAll('tbody tr');

    input.addEventListener('keyup', function() {
        const termo = this.value.toLowerCase();
        linhas.forEach(function(linha) {
            const texto = linha.textContent.toLowerCase();
            linha.style.display = texto.indexOf(termo) > -1 ? '' : 'none';
        });
    });
}

// =============================================
// CARREGAR VEÍCULOS DE UM CLIENTE E PREENCHER SELECT
// =============================================
function carregarVeiculos(clienteId, selectId, servicoId) {
    if (!clienteId) {
        document.getElementById(selectId).innerHTML = '<option value="">Selecione um cliente primeiro</option>';
        return;
    }

    fetch('../api/veiculos.php?cliente_id=' + clienteId)
        .then(res => res.json())
        .then(data => {
            let options = '<option value="">-- Selecione o veículo --</option>';
            data.forEach(v => {
                options += `<option value="${v.id}">${v.marca} ${v.modelo} - ${v.placa}</option>`;
            });
            document.getElementById(selectId).innerHTML = options;

            if (servicoId) {
                document.getElementById(selectId).value = servicoId;
            }
        })
        .catch(err => console.error('Erro ao carregar veículos:', err));
}
