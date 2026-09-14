<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
$status_nomes = ['solicitado' => 'Aguardando validação', 'validado' => 'Validado', 'pedido' => 'Pedido realizado', 'a_caminho' => 'A caminho', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado'];
$filtro_papel = $_SESSION['usuario_tipo'] === 'mecanico' ? ' JOIN os_responsaveis r ON r.os_id = os.id AND r.usuario_id = ' . (int) $_SESSION['usuario_id'] . ' AND r.concluido_em IS NULL ' : '';
$stmt = $conn->query("SELECT p.id, p.os_id, p.status, p.fornecedor, p.previsao_entrega, p.criado_em, os.numero, c.nome AS cliente, v.modelo, v.placa, GROUP_CONCAT(pi.descricao SEPARATOR ', ') AS itens FROM pedidos_pecas p JOIN ordens_servico os ON os.id = p.os_id $filtro_papel JOIN clientes c ON c.id = os.cliente_id LEFT JOIN veiculos v ON v.id = os.veiculo_id LEFT JOIN pedido_pecas_itens pi ON pi.pedido_id = p.id WHERE " . ($_SESSION['usuario_tipo'] === 'mecanico' ? "p.criado_por = " . (int) $_SESSION['usuario_id'] . " OR p.status IN ('validado','pedido','a_caminho','recebido')" : '1=1') . " GROUP BY p.id ORDER BY p.criado_em DESC");
$pedidos = $stmt->fetchAll();
$titulo_pagina = 'Pedidos de peças';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header"><div><h1><i data-lucide="truck" class="text-primary me-2"></i>Pedidos de peças</h1><span class="page-subtitle"><?php echo $_SESSION['usuario_tipo'] === 'mecanico' ? 'Solicitações das suas ordens de serviço' : 'Valide solicitações e acompanhe compras'; ?></span></div><?php if ($_SESSION['usuario_tipo'] !== 'mecanico'): ?><a href="cadastrar.php" class="btn btn-primary btn-icon"> <i data-lucide="plus"></i>Novo pedido</a><?php endif; ?></div>
<div class="card"><div class="card-body p-0">
<div class="filter-bar mb-0 rounded-bottom-0">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" id="buscarPedido" data-filtro-busca="tabelaPedidos" placeholder="Buscar por pedido, OS, cliente, item ou fornecedor...">
            </div>
            <div class="filter-group">
                <label for="filtroStatusPedido">Status</label>
                <select class="form-select" id="filtroStatusPedido" data-filtro-tabela="tabelaPedidos" data-filtro-coluna="Status">
                    <option value="">Todos</option>
                    <?php foreach ($status_nomes as $status_ped) : ?>
                        <option value="<?php echo esc($status_ped); ?>"><?php echo esc($status_ped); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="limparFiltros('tabelaPedidos')">
                <i data-lucide="filter-x"></i> Limpar
            </button>
        </div>
        <div class="px-3 pt-3 filter-results-info">
            <span>Exibindo <strong data-filtro-total="tabelaPedidos"><?php echo count($pedidos); ?></strong> de <?php echo count($pedidos); ?> pedidos</span>
        </div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0 responsive-card" id="tabelaPedidos"><thead><tr><th>Pedido</th><th>OS / Cliente</th><th>Itens</th><th>Fornecedor</th><th>Previsão</th><th>Status</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($pedidos as $pedido): ?><tr><td data-label="Pedido">#<?php echo (int) $pedido['id']; ?></td><td data-label="OS / Cliente"><a href="../ordens_servico/detalhes.php?id=<?php echo (int) $pedido['os_id']; ?>">OS #<?php echo str_pad((int) $pedido['numero'], 4, '0', STR_PAD_LEFT); ?></a><?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><br><small><?php echo esc($pedido['cliente']); ?> · <?php echo esc($pedido['placa'] ?: $pedido['modelo'] ?: 'Sem veículo'); ?></small><?php endif; ?></td><td data-label="Itens"><?php echo esc($pedido['itens'] ?: '-'); ?></td><td data-label="Fornecedor"><?php echo tem_papel(['admin', 'secretaria', 'mecanico_chefe']) ? esc($pedido['fornecedor'] ?: '-') : 'Restrito'; ?></td><td data-label="Previsão"><?php echo $pedido['previsao_entrega'] ? date('d/m/Y', strtotime($pedido['previsao_entrega'])) : '-'; ?></td><td data-label="Status"><span class="badge badge-status-<?php echo esc($pedido['status']); ?>"><?php echo esc($status_nomes[$pedido['status']] ?? $pedido['status']); ?></span></td><td data-label="Ação" class="actions-cell"><div class="dropdown table-dropdown"><button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do pedido"><i data-lucide="more-horizontal"></i></button><div class="dropdown-menu dropdown-menu-end table-dropdown-menu"><h6 class="dropdown-header">Ações do pedido</h6><?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a class="dropdown-item" href="editar.php?id=<?php echo (int) $pedido['id']; ?>"><i data-lucide="pencil"></i> Atualizar pedido</a><?php else: ?><span class="dropdown-item-text text-muted"><i data-lucide="eye"></i> Acompanhar</span><?php endif; ?></div></div></td></tr><?php endforeach; ?>
<?php if (!$pedidos): ?><tr class="row-empty"><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="package-search"></i></div><h4>Nenhum pedido de peça</h4><p><?php echo $_SESSION['usuario_tipo'] === 'mecanico' ? 'Nenhuma solicitação de peça encontrada para suas OS.' : 'Nenhum pedido cadastrado ainda.'; ?></p><?php if ($_SESSION['usuario_tipo'] !== 'mecanico'): ?><a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="plus"></i> Novo pedido</a><?php endif; ?></div></td></tr><?php endif; ?>
<tr class="row-filtro-vazio" data-filtro-vazio="tabelaPedidos" style="display:none;"><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="filter"></i></div><h4>Nenhum pedido encontrado com esses filtros</h4><p>Ajuste a busca ou limpe os filtros para ver todos os pedidos.</p></div></td></tr>
</tbody></table></div></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>