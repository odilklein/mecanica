<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/campos.php';
require_once __DIR__ . '/../../includes/fipe.php';
require_once __DIR__ . '/../../includes/configuracoes.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

// O módulo FIPE pode ser desligado pelo admin em Configurações.
$modulo_fipe = config_flag($conn, 'modulo_fipe');

fipe_garantir_schema($conn);
$fipe_marcas = $conn->query("SELECT id, nome_exibicao FROM fipe_marcas ORDER BY nome_exibicao")->fetchAll();
$catalogo_ok = $modulo_fipe && count($fipe_marcas) > 0;

$clientes_opcoes = $conn->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();

$titulo_pagina = 'Veículos';
$head_extra = '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$config = [
    'apiUrl' => $caminho_base . '/api/veiculos.php',
    'idCampo' => 'id',
    'singular' => 'Veículo',
    'plural' => 'veículos',
    'porPagina' => 15,
    'tplId' => 'tplVeiculo',
    'emptyHtml' => crud_empty_state('car', 'Nenhum veículo encontrado', 'Ajuste a busca ou cadastre um novo veículo.'),
];
$scripts_extra  = '<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>';
$scripts_config  = crud_config($config);
$scripts_extra .= '<script src="' . $caminho_base . '/js/veiculo-fipe.js"></script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/veiculos.js"></script>';

$opcoes_cliente = '';
foreach ($clientes_opcoes as $c) {
    $opcoes_cliente .= '<option value="' . (int) $c['id'] . '">' . esc($c['nome']) . '</option>';
}
?>

<div class="page-header">
    <div>
        <h1>Veículos</h1>
        <span class="page-subtitle">Frota cadastrada e valores FIPE</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (tem_papel(['admin']) && $modulo_fipe): ?>
        <form method="POST" action="sincronizar_fipe.php" class="d-inline" onsubmit="this.querySelector('button').disabled=true; return confirm('Sincronizar o catálogo FIPE? Isso leva cerca de 1 minuto.');">
            <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
            <button type="submit" class="btn btn-outline-secondary"><i class="icon-base ti tabler-refresh me-1"></i>Sincronizar FIPE</button>
        </form>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" data-crud="novo"><i class="icon-base ti tabler-plus me-1"></i>Novo Veículo</button>
    </div>
</div>

<div class="row g-3 mb-1">
    <?php
    echo crud_kpi('kpiTotal', 'Veículos', 'car', 'primary');
    echo crud_kpi('kpiFipe', 'Com valor FIPE', 'currency-dollar', 'success');
    echo crud_kpi('kpiSemFipe', 'Sem referência FIPE', 'help-circle', 'warning');
    echo crud_kpi('kpiKm', 'KM médio', 'gauge', 'info');
    ?>
</div>

<div class="card">
    <?php
    $filtros = crud_filtro_select('cliente_id', array_column($clientes_opcoes, 'nome', 'id'), 'Todos os clientes');
    echo crud_toolbar('Buscar por placa, marca, modelo, cliente...', $filtros);
    echo crud_bulk_bar([
        ['id' => 'excluir', 'rotulo' => 'Excluir', 'icone' => 'trash', 'tom' => 'outline-danger'],
    ]);
    echo crud_tabela_abrir([
        ['rotulo' => 'Veículo'],
        ['rotulo' => 'Placa'],
        ['rotulo' => 'Cliente'],
        ['rotulo' => 'Ano'],
        ['rotulo' => 'Cor'],
        ['rotulo' => 'KM Atual'],
        ['rotulo' => 'Valor FIPE'],
        ['rotulo' => 'Ações', 'classe' => 'actions-cell'],
    ]);
    echo crud_rodape();
    ?>
</div>

<template id="tplVeiculo">
    <tr>
        <td class="check-cell"><input class="form-check-input row-check" type="checkbox"></td>
        <td data-label="Veículo">
            <div class="d-flex align-items-center">
                <i class="icon-base ti tabler-car text-body-secondary me-2"></i>
                <div>
                    <span class="fw-medium d-block veiculo-nome"></span>
                    <small class="text-body-secondary veiculo-versao"></small>
                </div>
            </div>
        </td>
        <td data-label="Placa"><span class="badge bg-label-secondary veiculo-placa"></span></td>
        <td data-label="Cliente" class="veiculo-cliente"></td>
        <td data-label="Ano" class="veiculo-ano"></td>
        <td data-label="Cor">
            <span class="color-badge veiculo-cor-badge"></span>
            <span class="ms-1 veiculo-cor"></span>
        </td>
        <td data-label="KM Atual" class="veiculo-km"></td>
        <td data-label="Valor FIPE" class="veiculo-fipe"></td>
        <td class="actions-cell">
            <div class="dropdown table-dropdown">
                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do veículo">
                    <i class="icon-base ti tabler-dots-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                    <h6 class="dropdown-header">Ações do veículo</h6>
                    <button class="dropdown-item acao-editar" type="button"><i class="icon-base ti tabler-pencil"></i> Editar</button>
                    <button class="dropdown-item text-danger acao-excluir" type="button"><i class="icon-base ti tabler-trash"></i> Excluir</button>
                </div>
            </div>
        </td>
    </tr>
</template>

<?php echo crud_toast('veiculosToast'); ?>

<div class="modal fade" id="veiculoModal" tabindex="-1" data-crud="modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form data-crud="form" onsubmit="return validarFormularioVeiculo()">
                <div class="modal-header">
                    <h5 class="modal-title" data-crud="modal-titulo">Novo Veículo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" data-crud="modal-erro" hidden></div>
                    <input type="hidden" name="id" value="">
                    <?php if ($catalogo_ok): ?>
                    <input type="hidden" name="marca_nome" id="marca_nome" value="">
                    <input type="hidden" name="modelo_nome" id="modelo_nome" value="">
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="cliente_id" class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">-- Selecione o cliente --</option>
                                <?php echo $opcoes_cliente; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('placa', 'Placa *', 'license', 'text', ['required' => true, 'class' => 'text-uppercase', 'oninput' => 'formatarPlaca(this)'], 'ABC-1234'); ?>
                        </div>
                        <?php if ($catalogo_ok): ?>
                        <div class="col-md-4">
                            <label for="marca_id" class="form-label">Marca *</label>
                            <select class="form-select" id="marca_id" name="marca_id" data-placeholder="Selecione a marca">
                                <option value="">Selecione a marca</option>
                                <?php foreach ($fipe_marcas as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>"><?php echo esc($m['nome_exibicao']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="modelo_base_id" class="form-label">Modelo *</label>
                            <select class="form-select" id="modelo_base_id" name="modelo_base_id" data-placeholder="Selecione o modelo">
                                <option value="">Selecione a marca primeiro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="modelo_id" class="form-label">Versão (opcional)</label>
                            <select class="form-select" id="modelo_id" name="modelo_id" data-placeholder="Selecione a versão">
                                <option value="">Selecione o modelo primeiro</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <div class="col-md-4">
                            <label for="marca_nome_input" class="form-label">Marca *</label>
                            <input type="text" class="form-control" id="marca_nome_input" name="marca_nome" placeholder="Ex: Volkswagen" required>
                        </div>
                        <div class="col-md-4">
                            <label for="modelo_nome_input" class="form-label">Modelo *</label>
                            <input type="text" class="form-control" id="modelo_nome_input" name="modelo_nome" placeholder="Ex: Gol" required>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-warning py-2 mb-0"><i class="icon-base ti tabler-alert-triangle"></i> Catálogo FIPE não sincronizado. Rode <code>php scripts/sync_fipe.php</code> para habilitar as listas.</div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label for="ano" class="form-label">Ano</label>
                            <?php if ($catalogo_ok): ?>
                            <select class="form-select" id="ano" name="ano" data-placeholder="Selecione o ano">
                                <option value="">Selecione a versão primeiro</option>
                            </select>
                            <?php else: ?>
                            <input type="number" class="form-control" id="ano" name="ano" min="1950" max="2030" placeholder="Ex: 2020">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="valorFipe" class="form-label">Valor FIPE</label>
                            <input type="text" class="form-control" id="valorFipe" readonly placeholder="Calculado ao informar versão e ano">
                        </div>
                        <div class="col-md-8">
                            <?php echo campo_icone('cor', 'Cor', 'palette', 'text', ['autocomplete' => 'off', 'maxlength' => 30], 'Preto, #b3261e'); ?>
                            <div class="color-palette" id="corPalette">
                                <?php foreach (cores_veiculo() as $nome => $hex): ?>
                                    <button type="button" class="color-swatch" data-cor="<?php echo esc($nome); ?>" data-hex="<?php echo esc($hex); ?>" title="<?php echo esc($nome); ?>" style="background-color:<?php echo esc($hex); ?>;"></button>
                                <?php endforeach; ?>
                                <label class="color-swatch color-picker-swatch" title="Cor personalizada (seletor de cor)">
                                    <input type="color" id="corPicker" value="#b3261e" aria-label="Cor personalizada">
                                    <i class="icon-base ti tabler-color-picker color-picker-icon"></i>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <?php echo campo_icone('km_atual', 'KM Atual', 'gauge', 'number', ['min' => 0], '0'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo campo_icone('chassi', 'Chassi', 'fingerprint', 'text', ['maxlength' => 30], 'Opcional'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy me-1"></i> Salvar Veículo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php echo crud_confirm_modal(); ?>

<script>
function validarFormularioVeiculo() {
    const placa = document.getElementById('placa').value;
    if (!/^[A-Z]{3}-?\d[A-Z0-9]\d{2}$/.test(placa)) {
        const erro = document.querySelector('#veiculoModal [data-crud="modal-erro"]');
        if (erro) { erro.textContent = 'Formato de placa inválido. Use ABC-1234 ou ABC1D23 (Mercosul).'; erro.hidden = false; }
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
