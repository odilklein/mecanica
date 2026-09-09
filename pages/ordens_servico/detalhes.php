<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT os.*, c.nome AS cliente, c.telefone, c.email, v.marca, v.modelo, v.placa, v.ano, v.km_atual FROM ordens_servico os JOIN clientes c ON c.id = os.cliente_id LEFT JOIN veiculos v ON v.id = os.veiculo_id WHERE os.id = :id");
$stmt->execute([':id' => $id]);
$os = $stmt->fetch();
if (!$os) {
    http_response_code(404);
    exit('Ordem de serviço não encontrada.');
}

$stmt = $conn->prepare('SELECT u.nome, u.tipo, r.atribuido_em FROM os_responsaveis r JOIN usuarios u ON u.id = r.usuario_id WHERE r.os_id = :id AND r.concluido_em IS NULL ORDER BY u.nome');
$stmt->execute([':id' => $id]);
$responsaveis = $stmt->fetchAll();
$mecanicos = [];
if (tem_papel(['admin', 'mecanico_chefe'])) {
    $mecanicos = $conn->query("SELECT id, nome, tipo FROM usuarios WHERE tipo IN ('mecanico', 'mecanico_chefe') ORDER BY nome")->fetchAll();
}
$stmt = $conn->prepare('SELECT e.*, u.nome AS autor FROM os_eventos e JOIN usuarios u ON u.id = e.criado_por WHERE e.os_id = :id ORDER BY e.criado_em DESC');
$stmt->execute([':id' => $id]);
$eventos = $stmt->fetchAll();

$titulo_pagina = 'OS #' . str_pad((int) $os['numero'], 4, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
$status_nomes = ['recebida' => 'Recebida', 'triagem' => 'Triagem', 'aguardando_diagnostico' => 'Aguardando diagnóstico', 'aguardando_orcamento' => 'Aguardando orçamento', 'aguardando_aprovacao' => 'Aguardando aprovação', 'aguardando_peca' => 'Aguardando peça', 'pronta_para_execucao' => 'Pronta para execução', 'em_execucao' => 'Em execução', 'aguardando_retorno' => 'Aguardando retorno', 'pronta_para_entrega' => 'Pronta para entrega', 'entregue' => 'Entregue', 'cancelada' => 'Cancelada'];
?>
<div class="page-header">
    <div><h1><i data-lucide="clipboard-check" class="text-primary me-2"></i>OS #<?php echo str_pad((int) $os['numero'], 4, '0', STR_PAD_LEFT); ?></h1><span class="page-subtitle"><?php echo esc($status_nomes[$os['status']] ?? $os['status']); ?></span></div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon"><i data-lucide="arrow-left"></i>Voltar para fila</a>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header"><i data-lucide="car" class="me-2"></i>Atendimento</div><div class="card-body">
            <div class="row g-3"><div class="col-md-6"><strong>Cliente</strong><br><?php echo esc($os['cliente']); ?><br><small><?php echo esc($os['telefone'] ?: 'Sem telefone'); ?></small></div><div class="col-md-6"><strong>Veículo</strong><br><?php echo esc(trim(($os['marca'] ?? '') . ' ' . ($os['modelo'] ?? '')) ?: 'Não informado'); ?><br><small><?php echo esc($os['placa'] ?: ''); ?></small></div><div class="col-12"><strong>Queixa inicial</strong><p class="mb-0 mt-1"><?php echo nl2br(esc($os['queixa_inicial'])); ?></p></div></div>
        </div></div>
        <div class="card mb-3"><div class="card-header"><i data-lucide="stethoscope" class="me-2"></i>Diagnóstico técnico</div><div class="card-body">
            <form method="POST" action="processar.php?acao=atualizar_diagnostico"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><label class="form-label" for="diagnostico">Diagnóstico</label><textarea class="form-control mb-3" id="diagnostico" name="diagnostico" rows="5" <?php echo tem_papel(['mecanico', 'mecanico_chefe', 'admin']) ? '' : 'readonly'; ?>><?php echo esc($os['diagnostico'] ?? ''); ?></textarea><label class="form-label" for="observacoes_internas">Observações internas</label><textarea class="form-control mb-3" id="observacoes_internas" name="observacoes_internas" rows="3" <?php echo tem_papel(['mecanico', 'mecanico_chefe', 'admin']) ? '' : 'readonly'; ?>><?php echo esc($os['observacoes_internas'] ?? ''); ?></textarea><?php if (tem_papel(['mecanico', 'mecanico_chefe', 'admin'])): ?><button class="btn btn-primary btn-icon" type="submit"><i data-lucide="save"></i>Salvar diagnóstico</button><?php endif; ?></form>
        </div></div>
        <div class="card"><div class="card-header"><i data-lucide="history" class="me-2"></i>Histórico</div><div class="card-body"><div class="timeline"><?php foreach ($eventos as $evento): ?><div class="mb-3"><strong><?php echo esc($evento['descricao']); ?></strong><br><small class="text-muted"><?php echo esc($evento['autor']); ?> · <?php echo date('d/m/Y H:i', strtotime($evento['criado_em'])); ?></small></div><?php endforeach; ?></div></div></div>
    </div>
    <div class="col-lg-4">
        <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><div class="card mb-3"><div class="card-header"><i data-lucide="refresh-cw" class="me-2"></i>Atualizar status</div><div class="card-body"><form method="POST" action="processar.php?acao=mudar_status"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><select class="form-select mb-3" name="status"><?php foreach ($status_nomes as $status => $nome): ?><option value="<?php echo esc($status); ?>" <?php echo $os['status'] === $status ? 'selected' : ''; ?>><?php echo esc($nome); ?></option><?php endforeach; ?></select><button class="btn btn-primary w-100" type="submit">Salvar status</button></form></div></div><?php endif; ?>
        <?php if ($mecanicos): ?><div class="card mb-3"><div class="card-header"><i data-lucide="user-plus" class="me-2"></i>Designar mecânico</div><div class="card-body"><form method="POST" action="processar.php?acao=atribuir"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><select class="form-select mb-3" name="usuario_id" required><option value="">Selecione</option><?php foreach ($mecanicos as $mecanico): ?><option value="<?php echo (int) $mecanico['id']; ?>"><?php echo esc($mecanico['nome']); ?> (<?php echo esc($mecanico['tipo']); ?>)</option><?php endforeach; ?></select><button class="btn btn-outline-primary w-100" type="submit">Atribuir</button></form></div></div><?php endif; ?>
        <div class="card"><div class="card-header"><i data-lucide="users" class="me-2"></i>Responsáveis</div><div class="card-body"><?php if (!$responsaveis): ?><span class="text-muted">Nenhum mecânico designado.</span><?php else: foreach ($responsaveis as $responsavel): ?><div class="mb-2"><strong><?php echo esc($responsavel['nome']); ?></strong><br><small class="text-muted"><?php echo esc($responsavel['tipo']); ?></small></div><?php endforeach; endif; ?></div></div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>