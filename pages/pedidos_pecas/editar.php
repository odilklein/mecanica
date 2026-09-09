<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT p.*, os.numero, c.nome AS cliente FROM pedidos_pecas p JOIN ordens_servico os ON os.id = p.os_id JOIN clientes c ON c.id = os.cliente_id WHERE p.id = :id");
$stmt->execute([':id' => $id]);
$pedido = $stmt->fetch();
if (!$pedido) { http_response_code(404); exit('Pedido não encontrado.'); }
$titulo_pagina = 'Atualizar pedido de peça';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div><h1><i data-lucide="package-check" class="text-primary me-2"></i>Pedido #<?php echo $id; ?></h1><span class="page-subtitle">OS #<?php echo str_pad((int) $pedido['numero'], 4, '0', STR_PAD_LEFT); ?> · <?php echo esc($pedido['cliente']); ?></span></div></div>
<div class="card"><div class="card-body p-4"><form method="POST" action="processar.php?acao=atualizar"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo $id; ?>"><div class="row g-3"><div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['solicitado' => 'Solicitado', 'pedido' => 'Pedido', 'a_caminho' => 'A caminho', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado'] as $status => $nome): ?><option value="<?php echo $status; ?>" <?php echo $pedido['status'] === $status ? 'selected' : ''; ?>><?php echo $nome; ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Fornecedor</label><input class="form-control" name="fornecedor" maxlength="120" value="<?php echo esc($pedido['fornecedor']); ?>"></div><div class="col-md-4"><label class="form-label">Previsão</label><input class="form-control" type="date" name="previsao_entrega" value="<?php echo esc($pedido['previsao_entrega']); ?>"></div><div class="col-12"><label class="form-label">Observações</label><textarea class="form-control" name="observacoes" rows="4"><?php echo esc($pedido['observacoes']); ?></textarea></div></div><div class="d-flex justify-content-between mt-4"><a href="listar.php" class="btn btn-outline-secondary">Voltar</a><button class="btn btn-primary btn-icon" type="submit"><i data-lucide="save"></i>Atualizar pedido</button></div></form></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
