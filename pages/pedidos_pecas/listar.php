<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/crud.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);

$eh_mecanico = $_SESSION['usuario_tipo'] === 'mecanico';

// OS disponíveis para solicitação (mecânico enxerga as atribuídas a ele).
$ordens_disponiveis = [];
if (!$eh_mecanico || tem_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico'])) {
    $filtro_os = $eh_mecanico
        ? 'JOIN os_responsaveis r ON r.os_id = os.id AND r.usuario_id = ' . (int) $_SESSION['usuario_id'] . ' AND r.concluido_em IS NULL'
        : '';
    $ordens_disponiveis = $conn->query("SELECT os.id, os.numero, c.nome AS cliente, v.modelo, v.placa
        FROM ordens_servico os
        $filtro_os
        JOIN clientes c ON c.id = os.cliente_id
        LEFT JOIN veiculos v ON v.id = os.veiculo_id
        WHERE os.status NOT IN ('entregue', 'cancelada')
        ORDER BY os.data_entrada DESC")->fetchAll();
}

$titulo_pagina = 'Pedidos de peças';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$config = [
    'apiUrl' => $caminho_base . '/api/pedidos_pecas.php',
    'idCampo' => 'id',
    'singular' => 'Pedido',
    'plural' => 'pedidos',
    'porPagina' => 15,
    'tplId' => 'tplPedido',
    'ehMecanico' => $eh_mecanico,
    'emptyHtml' => crud_empty_state('package', 'Nenhum pedido encontrado', 'Ajuste a busca ou crie um novo pedido de peça.'),
];
$scripts_config = crud_config($config);
$scripts_extra = '<script src="' . $caminho_base . '/js/pedidos_pecas.js"></script>';

$opcoes_os = '';
foreach ($ordens_disponiveis as $o) {
    $rotulo = 'OS #' . str_pad((int) $o['numero'], 4, '0', STR_PAD_LEFT)
        . ($eh_mecanico ? '' : ' - ' . $o['cliente'])
        . ' - ' . ($o['placa'] ?: $o['modelo'] ?: 'veículo');
    $opcoes_os .= '<option value="' . (int) $o['id'] . '">' . esc($rotulo) . '</option>';
}
?>

<div class="page-header">
    <div>
        <h1>Pedidos de peças</h1>
        <span class="page-subtitle">Solicitações, fornecedores e recebimento</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-primary" data-crud="novo">
            <i class="icon-base ti tabler-plus me-1"></i><?php echo $eh_mecanico ? 'Solicitar peça' : 'Novo pedido'; ?>
        </button>
    </div>
</div>

<div class="row g-3 mb-1">
    <?php
    echo crud_kpi('kpiTotal', 'Pedidos', 'package', 'primary');
    echo crud_kpi('kpiPendentes', 'Pendentes', 'hourglass', 'warning');
    echo crud_kpi('kpiRecebidos', 'Recebidos', 'circle-check', 'success');
    echo crud_kpi('kpiCancelados', 'Cancelados', 'ban', 'danger');
    ?>
</div>

<div class="card">
    <?php
    $filtros = crud_filtro_select('status', [
        'solicitado' => 'Aguardando validação',
        'validado' => 'Validado',
        'pedido' => 'Pedido realizado',
        'a_caminho' => 'A caminho',
        'recebido' => 'Recebido',
        'cancelado' => 'Cancelado',
    ], 'Todos os status');
    echo crud_toolbar('Buscar por pedido, OS, cliente, item ou fornecedor...', $filtros);
    echo crud_tabela_abrir([
        ['rotulo' => 'Pedido'],
        ['rotulo' => 'OS / Cliente'],
        ['rotulo' => 'Itens'],
        ['rotulo' => 'Fornecedor'],
        ['rotulo' => 'Previsão'],
        ['rotulo' => 'Status'],
        ['rotulo' => 'Ações', 'classe' => 'actions-cell'],
    ], false);
    echo crud_rodape();
    ?>
</div>

<template id="tplPedido">
    <tr>
        <td data-label="Pedido"><span class="fw-medium pedido-id"></span></td>
        <td data-label="OS / Cliente">
            <a class="pedido-os-link" href="#"><span class="pedido-os"></span></a>
            <small class="text-body-secondary d-block pedido-cliente"></small>
        </td>
        <td data-label="Itens" class="pedido-itens"></td>
        <td data-label="Fornecedor" class="pedido-fornecedor"></td>
        <td data-label="Previsão" class="pedido-previsao"></td>
        <td data-label="Status"><span class="badge pedido-status"></span></td>
        <td class="actions-cell">
            <div class="dropdown table-dropdown">
                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do pedido">
                    <i class="icon-base ti tabler-dots-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                    <h6 class="dropdown-header">Ações do pedido</h6>
                    <button class="dropdown-item acao-atualizar" type="button"><i class="icon-base ti tabler-pencil"></i> Atualizar pedido</button>
                    <button class="dropdown-item acao-receber" type="button"><i class="icon-base ti tabler-package-import"></i> Registrar recebimento</button>
                </div>
            </div>
        </td>
    </tr>
</template>

<?php echo crud_toast('pedidosToast'); ?>

<!-- Modal: novo pedido -->
<div class="modal fade" id="pedidoModal" tabindex="-1" data-crud="modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="pedidoNovoForm">
                <div class="modal-header">
                    <h5 class="modal-title" data-crud="modal-titulo">Novo pedido de peça</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" data-crud="modal-erro" hidden></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Ordem de serviço *</label>
                            <select class="form-select" name="os_id" required>
                                <option value="">Selecione a OS</option>
                                <?php echo $opcoes_os; ?>
                            </select>
                        </div>
                        <?php if (!$eh_mecanico): ?>
                        <div class="col-md-6">
                            <label class="form-label">Fornecedor</label>
                            <input class="form-control" name="fornecedor" maxlength="120" placeholder="Nome do fornecedor">
                        </div>
                        <?php endif; ?>
                        <div class="col-md-<?php echo $eh_mecanico ? '8' : '6'; ?>">
                            <label class="form-label">Descrição da peça *</label>
                            <input class="form-control" name="descricao" maxlength="200" required placeholder="Ex.: Filtro de óleo, Pastilha de freio...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Quantidade *</label>
                            <input class="form-control" type="number" name="quantidade" min="1" value="1" required>
                        </div>
                        <?php if (!$eh_mecanico): ?>
                        <div class="col-md-3">
                            <label class="form-label">Previsão entrega</label>
                            <input class="form-control" type="date" name="previsao_entrega">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" name="observacoes" rows="2" maxlength="1000"></textarea>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy me-1"></i> <?php echo $eh_mecanico ? 'Enviar solicitação' : 'Criar pedido'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: atualizar pedido (status/dados) -->
<div class="modal fade" id="pedidoAtualizarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="pedidoAtualizarForm">
                <div class="modal-header">
                    <h5 class="modal-title">Atualizar pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" id="pedidoAtualizarErro" hidden></div>
                    <input type="hidden" name="id">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fornecedor</label>
                            <input class="form-control" name="fornecedor" maxlength="120">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Previsão</label>
                            <input class="form-control" type="date" name="previsao_entrega">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" name="observacoes" rows="4"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy me-1"></i> Atualizar pedido</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: receber itens -->
<div class="modal fade" id="pedidoReceberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="pedidoReceberForm">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar recebimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" id="pedidoReceberErro" hidden></div>
                    <input type="hidden" name="id">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Peça</th><th>Qtd. pedida</th><th>Qtd. recebida</th><th>Receber agora</th></tr></thead>
                            <tbody id="pedidoReceberTbody"></tbody>
                        </table>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="recebido_no_pedido" id="recebidoNoPedido" value="1">
                        <label class="form-check-label" for="recebidoNoPedido">Receber todos os itens restantes (total)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="icon-base ti tabler-package me-1"></i> Registrar recebimento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php echo crud_confirm_modal(); ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
