<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/crud.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$titulo_pagina = 'Orçamentos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$config = [
    'apiUrl' => $caminho_base . '/api/orcamentos.php',
    'idCampo' => 'id',
    'singular' => 'Orçamento',
    'plural' => 'orçamentos',
    'porPagina' => 15,
    'tplId' => 'tplOrcamento',
    'emptyHtml' => crud_empty_state('receipt', 'Nenhum orçamento encontrado', 'Ajuste a busca ou crie um novo orçamento.'),
];
$scripts_config = crud_config($config);
$scripts_extra = '<script src="' . $caminho_base . '/js/orcamentos.js"></script>';
?>

<div class="page-header">
    <div>
        <h1>Orçamentos</h1>
        <span class="page-subtitle">Documentos, validade e aprovações</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="relatorio.php" class="btn btn-outline-secondary"><i class="icon-base ti tabler-chart-line me-1"></i>Relatórios</a>
        <a href="editor.php" class="btn btn-primary" id="btnNovoOrcamento"><i class="icon-base ti tabler-plus me-1"></i>Novo Orçamento</a>
    </div>
</div>

<div class="row g-3 mb-1">
    <?php
    echo crud_kpi('kpiTotal', 'Orçamentos', 'receipt', 'primary');
    echo crud_kpi('kpiAbertos', 'Abertos', 'file-text', 'info');
    echo crud_kpi('kpiAprovados', 'Aprovados', 'circle-check', 'success');
    echo crud_kpi('kpiReprovados', 'Reprovados / cancelados', 'ban', 'danger');
    ?>
</div>

<div class="card">
    <?php
    $filtros = crud_filtro_select('status', [
        'aberto' => 'Aberto',
        'aprovado' => 'Aprovado',
        'reprovado' => 'Reprovado',
        'cancelado' => 'Cancelado',
    ], 'Todos os status');
    $filtros .= crud_filtro_select('periodo', [
        'hoje' => 'Hoje',
        'ultimos7' => 'Últimos 7 dias',
        'ultimos30' => 'Últimos 30 dias',
    ], 'Período: todos');
    echo crud_toolbar('Buscar por número, cliente, veículo ou placa...', $filtros);
    echo crud_bulk_bar([
        ['id' => 'aprovar', 'rotulo' => 'Aprovar', 'icone' => 'check', 'tom' => 'outline-success'],
        ['id' => 'reprovar', 'rotulo' => 'Reprovar', 'icone' => 'x', 'tom' => 'outline-warning'],
        ['id' => 'excluir', 'rotulo' => 'Excluir', 'icone' => 'trash', 'tom' => 'outline-danger'],
    ]);
    echo crud_tabela_abrir([
        ['rotulo' => 'Nº'],
        ['rotulo' => 'Cliente'],
        ['rotulo' => 'Veículo'],
        ['rotulo' => 'Data'],
        ['rotulo' => 'Validade'],
        ['rotulo' => 'Total'],
        ['rotulo' => 'Status'],
        ['rotulo' => 'Ações', 'classe' => 'actions-cell'],
    ]);
    echo crud_rodape();
    ?>
</div>

<template id="tplOrcamento">
    <tr>
        <td class="check-cell"><input class="form-check-input row-check" type="checkbox"></td>
        <td data-label="Nº"><span class="fw-medium orc-numero"></span></td>
        <td data-label="Cliente" class="orc-cliente"></td>
        <td data-label="Veículo">
            <span class="orc-veiculo"></span>
            <br><small><span class="badge bg-label-secondary orc-placa"></span></small>
        </td>
        <td data-label="Data"><small class="orc-data"></small></td>
        <td data-label="Validade"><small class="orc-validade"></small></td>
        <td data-label="Total" class="fw-600 text-success orc-total"></td>
        <td data-label="Status"><span class="badge orc-status"></span></td>
        <td class="actions-cell">
            <div class="dropdown table-dropdown">
                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do orçamento">
                    <i class="icon-base ti tabler-dots-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                    <h6 class="dropdown-header">Ações do orçamento</h6>
                    <a class="dropdown-item acao-detalhes" href="#"><i class="icon-base ti tabler-eye"></i> Ver detalhes</a>
                    <a class="dropdown-item acao-imprimir" href="#" target="_blank"><i class="icon-base ti tabler-printer"></i> Imprimir</a>
                    <a class="dropdown-item acao-editar" href="#"><i class="icon-base ti tabler-pencil"></i> Editar</a>
                    <button class="dropdown-item text-danger acao-excluir" type="button"><i class="icon-base ti tabler-trash"></i> Excluir</button>
                </div>
            </div>
        </td>
    </tr>
</template>

<?php echo crud_toast('orcamentosToast'); ?>
<?php echo crud_confirm_modal(); ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
