<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
$status_nomes = ['solicitado' => 'Solicitado', 'pedido' => 'Pedido', 'a_caminho' => 'A caminho', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado'];
$stmt = $conn->query("SELECT p.id, p.os_id, p.status, p.fornecedor, p.previsao_entrega, p.criado_em, os.numero, c.nome AS cliente, v.modelo, v.placa, GROUP_CONCAT(pi.descricao SEPARATOR ', ') AS itens FROM pedidos_pecas p JOIN ordens_servico os ON os.id = p.os_id JOIN clientes c ON c.id = os.cliente_id LEFT JOIN veiculos v ON v.id = os.veiculo_id LEFT JOIN pedido_pecas_itens pi ON pi.pedido_id = p.id GROUP BY p.id ORDER BY p.criado_em DESC");
$pedidos = $stmt->fetchAll();
$titulo_pagina = 'Pedidos de peças';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header"><div><h1><i data-lucide="truck" class="text-primary me-2"></i>Pedidos de peças</h1><span class="page-subtitle">Acompanhe peças encomendadas por ordem de serviço</span></div><a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="plus"></i>Novo pedido</a></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Pedido</th><th>OS / Cliente</th><th>Itens</th><th>Fornecedor</th><th>Previsão</th><th>Status</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($pedidos as $pedido): ?><tr><td>#<?php echo (int) $pedido['id']; ?></td><td><a href="../ordens_servico/detalhes.php?id=<?php echo (int) $pedido['os_id']; ?>">OS #<?php echo str_pad((int) $pedido['numero'], 4, '0', STR_PAD_LEFT); ?></a><br><small><?php echo esc($pedido['cliente']); ?> · <?php echo esc($pedido['placa'] ?: $pedido['modelo'] ?: 'Sem veículo'); ?></small></td><td><?php echo esc($pedido['itens'] ?: '-'); ?></td><td><?php echo esc($pedido['fornecedor'] ?: '-'); ?></td><td><?php echo $pedido['previsao_entrega'] ? date('d/m/Y', strtotime($pedido['previsao_entrega'])) : '-'; ?></td><td><span class="badge badge-info"><?php echo esc($status_nomes[$pedido['status']] ?? $pedido['status']); ?></span></td><td><?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a class="btn btn-outline-primary btn-sm" href="editar.php?id=<?php echo (int) $pedido['id']; ?>" title="Atualizar pedido"><i data-lucide="pencil"></i></a><?php else: ?><span class="text-muted">Acompanhar</span><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$pedidos): ?><tr><td colspan="7" class="text-center py-5 text-muted">Nenhum pedido de peça cadastrado.</td></tr><?php endif; ?></tbody></table></div></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
