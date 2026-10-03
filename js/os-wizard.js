// =============================================
// NOVO ATENDIMENTO — autocomplete de cliente, veículos
// do cliente e cascata FIPE dentro do wizard.
// =============================================
(function () {
    'use strict';

    var cfg = window.OrdemServicoWizardConfig || {};
    var base = window.CAMINHO_BASE || '';
    var apiClientes = base + '/api/clientes.php';
    var apiVeiculos = base + '/api/veiculos.php';
    var apiFipe = base + '/api/fipe.php';

    var campoNome = document.getElementById('nome');
    var campoTelefone = document.getElementById('telefone');
    var campoClienteId = document.getElementById('cliente_id');
    var campoVeiculoId = document.getElementById('veiculo_id');
    var boxSugestoes = document.getElementById('clienteSugestoes');
    var btnLimpar = document.getElementById('clienteLimpar');
    var selectVeiculoExistente = document.getElementById('veiculo_existente');
    var boxVeiculoExistente = document.getElementById('veiculoExistenteBox');

    if (!campoNome || !campoClienteId) { return; }

    var timerBusca = null;

    // =============================================
    // 1) AUTOCOMPLETE DE CLIENTES
    // =============================================
    function fecharSugestoes() {
        if (!boxSugestoes) return;
        boxSugestoes.innerHTML = '';
        boxSugestoes.hidden = true;
        campoNome.setAttribute('aria-expanded', 'false');
    }

    function selecionarCliente(cliente) {
        campoClienteId.value = cliente.id;
        campoNome.value = cliente.nome;
        if (campoTelefone && !campoTelefone.value.trim()) {
            campoTelefone.value = cliente.telefone || '';
        }
        fecharSugestoes();
        carregarVeiculosDoCliente(cliente.id);
    }

    function buscarClientes(termo) {
        if (!boxSugestoes) return;
        fetch(apiClientes + '?acao=buscar&termo=' + encodeURIComponent(termo), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                var itens = (dados && dados.itens) || [];
                boxSugestoes.innerHTML = '';
                if (!itens.length) {
                    boxSugestoes.hidden = true;
                    return;
                }
                itens.forEach(function (cliente) {
                    var opcao = document.createElement('button');
                    opcao.type = 'button';
                    opcao.className = 'busca-resultado';
                    opcao.setAttribute('role', 'option');

                    var info = [cliente.telefone, cliente.cidade].filter(Boolean).join(' · ');
                    opcao.innerHTML = '<span class="busca-resultado-nome"></span>'
                        + '<span class="busca-resultado-info"></span>'
                        + '<span class="badge bg-label-secondary ms-auto"></span>';
                    opcao.querySelector('.busca-resultado-nome').textContent = cliente.nome;
                    opcao.querySelector('.busca-resultado-info').textContent = info || '—';
                    opcao.querySelector('.badge').textContent = cliente.veiculos + ' veículo(s)';
                    opcao.addEventListener('click', function () { selecionarCliente(cliente); });
                    boxSugestoes.appendChild(opcao);
                });
                boxSugestoes.hidden = false;
                campoNome.setAttribute('aria-expanded', 'true');
            })
            .catch(function () { fecharSugestoes(); });
    }

    if (campoNome) {
        campoNome.addEventListener('input', function () {
            // Digitou algo novo: descarta o cliente anteriormente selecionado.
            campoClienteId.value = '';

            clearTimeout(timerBusca);
            var termo = campoNome.value.trim();
            if (termo.length < 2) {
                fecharSugestoes();
                return;
            }
            timerBusca = setTimeout(function () { buscarClientes(termo); }, 280);
        });

        campoNome.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { fecharSugestoes(); }
        });
    }

    document.addEventListener('click', function (e) {
        var box = document.getElementById('clienteBuscaBox');
        if (box && !box.contains(e.target)) { fecharSugestoes(); }
    });

    if (btnLimpar) {
        btnLimpar.addEventListener('click', function () {
            campoClienteId.value = '';
            if (campoTelefone) campoTelefone.value = '';
            if (campoVeiculoId) campoVeiculoId.value = '';
            campoNome.value = '';
            campoNome.focus();
            resetVeiculo();
        });
    }

    // =============================================
    // 2) VEÍCULOS DO CLIENTE
    // =============================================
    function resetVeiculo() {
        if (selectVeiculoExistente) {
            selectVeiculoExistente.value = '';
            if (boxVeiculoExistente) boxVeiculoExistente.hidden = true;
        }
        var boxInfo = document.getElementById('veiculoInfo');
        if (boxInfo) boxInfo.classList.add('hidden');
        if (window.VeiculoFipe && typeof window.VeiculoFipe.aplicar === 'function') {
            window.VeiculoFipe.aplicar({});
        }
    }

    function aplicarVeiculo(veiculo) {
        if (campoVeiculoId) campoVeiculoId.value = veiculo.id;

        var placa = document.getElementById('placa');
        var cor = document.getElementById('cor');
        var km = document.getElementById('km_atual');
        var chassi = document.getElementById('chassi');

        if (placa) placa.value = veiculo.placa || '';
        if (cor) cor.value = veiculo.cor || '';
        if (km) km.value = veiculo.km_atual || '';
        if (chassi) chassi.value = veiculo.chassi || '';

        var marcaNome = document.getElementById('marca_nome');
        var modeloNome = document.getElementById('modelo_nome');
        if (marcaNome) marcaNome.value = veiculo.marca || '';
        if (modeloNome) modeloNome.value = veiculo.modelo || '';

        // Veículos cadastrados antes/fora da FIPE não têm os ids do catálogo;
        // nesse caso mostramos os nomes em vez de deixar os selects vazios.
        var boxInfo = document.getElementById('veiculoInfo');
        var textoInfo = document.getElementById('veiculoInfoTexto');
        var semFipe = !veiculo.marca_id && (veiculo.marca || veiculo.modelo);
        if (boxInfo && textoInfo) {
            if (semFipe) {
                textoInfo.textContent = [veiculo.marca, veiculo.modelo, veiculo.placa]
                    .filter(Boolean).join(' · ');
                boxInfo.classList.remove('hidden');
            } else {
                boxInfo.classList.add('hidden');
            }
        }

        // Se o veículo veio do catálogo FIPE, repovoa a cascata.
        if (window.VeiculoFipe && typeof window.VeiculoFipe.aplicar === 'function') {
            window.VeiculoFipe.aplicar({
                marca_id: veiculo.marca_id || 0,
                modelo_base_id: veiculo.modelo_base_id || 0,
                modelo_id: veiculo.modelo_id || 0,
                ano: veiculo.ano || 0
            });
        }
    }

    function carregarVeiculosDoCliente(clienteId) {
        if (!clienteId) { resetVeiculo(); return; }

        fetch(apiVeiculos + '?cliente_id=' + encodeURIComponent(clienteId), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (veiculos) {
                if (!Array.isArray(veiculos) || !veiculos.length) {
                    if (boxVeiculoExistente) boxVeiculoExistente.hidden = true;
                    mostrarToast('Cliente sem veículos cadastrados — informe o veículo ou consulte a FIPE.', 'info');
                    return;
                }
                if (!selectVeiculoExistente) return;

                selectVeiculoExistente.innerHTML = '<option value="">Selecione um veículo cadastrado</option>';
                veiculos.forEach(function (v) {
                    var opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = [v.marca, v.modelo, v.placa].filter(Boolean).join(' · ');
                    selectVeiculoExistente.appendChild(opt);
                });
                if (boxVeiculoExistente) boxVeiculoExistente.hidden = false;

                // Pré-seleciona o primeiro para agilizar.
                selectVeiculoExistente.value = veiculos[0].id;
                buscarDadosVeiculo(veiculos[0].id);
            })
            .catch(function () { /* mantém o formulário utilizável */ });
    }

    function buscarDadosVeiculo(veiculoId) {
        fetch(apiVeiculos + '?acao=obter&id=' + encodeURIComponent(veiculoId), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (dados && dados.ok && dados.item) {
                    aplicarVeiculo(dados.item);
                }
            })
            .catch(function () {});
    }

    if (selectVeiculoExistente) {
        selectVeiculoExistente.addEventListener('change', function () {
            if (!this.value) {
                if (campoVeiculoId) campoVeiculoId.value = '';
                return;
            }
            buscarDadosVeiculo(this.value);
        });
    }
})();