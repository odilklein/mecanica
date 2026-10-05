<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/crud.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);

$titulo_pagina = 'Ordens de serviço';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$config = [
    'apiUrl' => $caminho_base . '/api/ordens_servico.php',
    'idCampo' => 'id',
    'singular' => 'Ordem de serviço',
    'plural' => 'ordens',
    'porPagina' => 15,
    'tplId' => 'tplOs',
    'podeCriar' => tem_papel(['admin', 'secretaria', 'mecanico_chefe']),
    'emptyHtml' => crud_empty_state('clipboard-list', 'Nenhuma ordem encontrada', 'Ajuste a busca ou crie um novo atendimento.'),
];
$scripts_config = crud_config($config);
$scripts_extra = '<script src="' . $caminho_base . '/js/ordens_servico.js"></script>';
?>

<div class="page-header">
    <div>
        <h1>Ordens de serviço</h1>
        <span class="page-subtitle">Fila de atendimento da oficina</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?>
        <a href="novo.php" class="btn btn-primary"><i class="icon-base ti tabler-plus me-1"></i>Novo atendimento</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-1">
    <?php
    echo crud_kpi('kpiTotal', 'Em aberto', 'clipboard-list', 'primary');
    echo crud_kpi('kpiUrgentes', 'Urgentes', 'alert-triangle', 'danger');
    echo crud_kpi('kpiExecucao', 'Em execução', 'tools', 'info');
    echo crud_kpi('kpiAguardando', 'Aguardando peça', 'hourglass', 'warning');
    ?>
</div>

<div class="card">
    <?php
    $filtros = crud_filtro_select('prioridade', [
        'urgente' => 'Urgente', 'alta' => 'Alta', 'normal' => 'Normal', 'baixa' => 'Baixa',
    ], 'Prioridade: todas');
    $filtros .= crud_filtro_select('status', os_status_opcoes(false), 'Status: todos');
    echo crud_toolbar('Buscar por OS, cliente, veículo, placa ou queixa...', $filtros);
    if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])) {
        echo crud_bulk_bar([
            ['id' => 'cancelar', 'rotulo' => 'Cancelar OS', 'icone' => 'ban', 'tom' => 'outline-danger'],
        ]);
    }
    echo crud_tabela_abrir([
        ['rotulo' => 'OS'],
        ['rotulo' => 'Cliente'],
        ['rotulo' => 'Veículo'],
        ['rotulo' => 'Queixa'],
        ['rotulo' => 'Prioridade'],
        ['rotulo' => 'Status'],
        ['rotulo' => 'Entrada'],
        ['rotulo' => 'Ações', 'classe' => 'actions-cell'],
    ]);
    echo crud_rodape();
    ?>
</div>

<template id="tplOs">
    <tr>
        <td class="check-cell"><input class="form-check-input row-check" type="checkbox"></td>
        <td data-label="OS"><a class="os-link" href="#"><span class="fw-medium os-numero"></span></a></td>
        <td data-label="Cliente" class="os-cliente"></td>
        <td data-label="Veículo">
            <span class="os-veiculo"></span>
            <br><small class="text-body-secondary os-placa"></small>
        </td>
        <td data-label="Queixa"><span class="os-queixa"></span></td>
        <td data-label="Prioridade"><span class="badge os-prioridade"></span></td>
        <td data-label="Status"><span class="badge os-status"></span></td>
        <td data-label="Entrada"><small class="os-entrada"></small></td>
        <td class="actions-cell">
            <div class="dropdown table-dropdown">
                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da OS">
                    <i class="icon-base ti tabler-dots-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                    <h6 class="dropdown-header">Ações da OS</h6>
                    <a class="dropdown-item acao-detalhes" href="#"><i class="icon-base ti tabler-eye"></i> Ver detalhes</a>
                    <button class="dropdown-item acao-status" type="button"><i class="icon-base ti tabler-refresh"></i> Atualizar status</button>
                    <button class="dropdown-item text-danger acao-cancelar" type="button"><i class="icon-base ti tabler-ban"></i> Cancelar OS</button>
                </div>
            </div>
        </td>
    </tr>
</template>

<?php echo crud_toast('osToast'); ?>
<?php echo crud_confirm_modal(); ?>

<!-- Modal: atualizar status -->
<div class="modal fade" id="osStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="osStatusForm">
                <div class="modal-header">
                    <h5 class="modal-title">Atualizar status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" id="osStatusErro" hidden></div>
                    <input type="hidden" name="os_id">
                    <label class="form-label">Novo status</label>
                    <select class="form-select" name="status"></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy me-1"></i> Salvar status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
