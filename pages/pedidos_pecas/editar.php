<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT p.*, os.numero, c.nome AS cliente FROM pedidos_pecas p JOIN ordens_servico os ON os.id = p.os_id JOIN clientes c ON c.id = os.cliente_id WHERE p.id = :id");
$stmt->execute([':id' => $id]);
$pedido = $stmt->fetch();
if (!$pedido) { http_response_code(404); exit('Pedido não encontrado.'); }
$stmt = $conn->prepare('SELECT id, descricao, quantidade, recebido FROM pedido_pecas_itens WHERE pedido_id = :id ORDER BY id');
$stmt->execute([':id' => $id]);
$itens = $stmt->fetchAll();
$quantidades_total = 0;
$quantidades_recebidas = 0;
$itens_recebidos = 0;
foreach ($itens as $item) {
    $quantidades_total += (int) $item['quantidade'];
    $quantidades_recebidas += (int) $item['recebido'];
    if ((int) $item['recebido'] >= (int) $item['quantidade']) $itens_recebidos++;
}
$pode_receber = in_array($pedido['status'], ['validado', 'pedido', 'a_caminho'], true);
$titulo_pagina = 'Atualizar pedido de peça';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div><h1><i data-lucide="package-check" class="text-primary me-2"></i>Pedido #<?php echo $id; ?></h1><span class="page-subtitle">OS #<?php echo str_pad((int) $pedido['numero'], 4, '0', STR_PAD_LEFT); ?> · <?php echo esc($pedido['cliente']); ?></span></div></div>
<div class="row g-3 mb-4"><div class="col-6 col-xl-3"><div class="stat-card border-blue"><div class="stat-header"><span class="stat-label">Itens</span><span class="stat-icon blue"><i data-lucide="list-checks"></i></span></div><div class="stat-value"><?php echo count($itens); ?></div><div class="stat-delta">no pedido</div></div></div><div class="col-6 col-xl-3"><div class="stat-card border-accent"><div class="stat-header"><span class="stat-label">Recebimento</span><span class="stat-icon"><i data-lucide="package-check"></i></span></div><div class="stat-value"><?php echo $quantidades_recebidas . '/' . $quantidades_total; ?></div><div class="stat-delta">unidades recebidas</div></div></div><div class="col-6 col-xl-3"><div class="stat-card border-green"><div class="stat-header"><span class="stat-label">Itens completos</span><span class="stat-icon green"><i data-lucide="check-circle"></i></span></div><div class="stat-value"><?php echo $itens_recebidos . '/' . count($itens); ?></div><div class="stat-delta">itens totalmente recebidos</div></div></div></div>
<?php if ($pode_receber): ?>
<div class="card mb-3"><div class="card-header"><i data-lucide="package-open" class="me-2"></i>Registrar recebimento</div><div class="card-body p-0"><form method="POST" action="processar.php?acao=receber_itens"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo $id; ?>"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Peça</th><th>Qtd. pedida</th><th>Qtd. recebida</th><th>Receber agora</th></tr></thead><tbody><?php foreach ($itens as $item): ?><tr><td><?php echo esc($item['descricao']); ?></td><td><?php echo (int) $item['quantidade']; ?></td><td><?php echo (int) $item['recebido']; ?></td><td style="width:180px;"><input type="number" class="form-control form-control-sm" name="qtd[<?php echo (int) $item['id']; ?>]" min="0" max="<?php echo max(0, (int) $item['quantidade'] - (int) $item['recebido']); ?>" value="0" <?php echo (int) $item['recebido'] >= (int) $item['quantidade'] ? 'disabled' : ''; ?>></td></tr><?php endforeach; ?></tbody></table></div><div class="d-flex justify-content-between align-items-center px-3 py-3 border-top"><div class="form-check"><input class="form-check-input" type="checkbox" name="recebido_no_pedido" id="recebidoNoPedido" value="1"><label class="form-check-label" for="recebidoNoPedido">Receber todos os itens restantes (total)</label></div><button class="btn btn-success btn-icon action-button" type="submit"><i data-lucide="package-check"></i>Registrar recebimento</button></div></form></div></div>
<?php endif; ?>
<div class="card"><div class="card-body p-4"><form method="POST" action="processar.php?acao=atualizar"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo $id; ?>"><div class="row g-3"><div class="col-md-4"><label class="form-label"><i data-lucide="shield-check" class="form-label-icon"></i>Status</label><select class="form-select" name="status"><?php foreach (['solicitado' => 'Aguardando validação', 'validado' => 'Validado', 'pedido' => 'Pedido realizado', 'a_caminho' => 'A caminho', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado'] as $status => $nome): ?><option value="<?php echo $status; ?>" <?php echo $pedido['status'] === $status ? 'selected' : ''; ?>><?php echo $nome; ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label"><i data-lucide="store" class="form-label-icon"></i>Fornecedor</label><input class="form-control" name="fornecedor" maxlength="120" value="<?php echo esc($pedido['fornecedor']); ?>"></div><div class="col-md-4"><label class="form-label"><i data-lucide="calendar-clock" class="form-label-icon"></i>Previsão</label><input class="form-control" type="date" name="previsao_entrega" value="<?php echo esc($pedido['previsao_entrega']); ?>"></div><div class="col-12"><label class="form-label"><i data-lucide="notebook-pen" class="form-label-icon"></i>Observações</label><textarea class="form-control" name="observacoes" rows="4"><?php echo esc($pedido['observacoes']); ?></textarea></div></div><div class="d-flex justify-content-between mt-4"><a href="listar.php" class="btn btn-outline-secondary">Voltar</a><button class="btn btn-primary btn-icon action-button" type="submit"><i data-lucide="save"></i>Atualizar pedido</button></div></form></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
