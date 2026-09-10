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
if ($_SESSION['usuario_tipo'] === 'mecanico') {
    $stmt = $conn->prepare('SELECT 1 FROM os_responsaveis WHERE os_id = :os_id AND usuario_id = :usuario_id AND concluido_em IS NULL');
    $stmt->execute([':os_id' => $id, ':usuario_id' => $_SESSION['usuario_id']]);
    if (!$stmt->fetchColumn()) { http_response_code(403); exit('Esta ordem de serviço não está atribuída a você.'); }
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
$stmt = $conn->prepare('SELECT p.id, p.status, p.fornecedor, p.previsao_entrega, GROUP_CONCAT(pi.descricao SEPARATOR ", ") AS itens FROM pedidos_pecas p LEFT JOIN pedido_pecas_itens pi ON pi.pedido_id = p.id WHERE p.os_id = :id GROUP BY p.id ORDER BY p.criado_em DESC');
$stmt->execute([':id' => $id]);
$pedidos_pecas = $stmt->fetchAll();
$stmt = $conn->prepare('SELECT * FROM os_entregas WHERE os_id = :id ORDER BY criado_em DESC LIMIT 1');
$stmt->execute([':id' => $id]);
$entrega = $stmt->fetch();
$stmt = $conn->prepare('SELECT t.*, u.nome AS criador FROM os_tarefas t JOIN usuarios u ON u.id = t.criado_por WHERE t.os_id = :id ORDER BY t.id');
$stmt->execute([':id' => $id]);
$tarefas = $stmt->fetchAll();

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
            <div class="row g-3"><div class="col-md-6"><strong>Cliente</strong><br><?php echo esc($os['cliente']); ?><?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><br><small><?php echo esc($os['telefone'] ?: 'Sem telefone'); ?></small><?php endif; ?></div><div class="col-md-6"><strong>Veículo</strong><br><?php echo esc(trim(($os['marca'] ?? '') . ' ' . ($os['modelo'] ?? '')) ?: 'Não informado'); ?><br><small><?php echo esc($os['placa'] ?: ''); ?></small></div><div class="col-12"><strong>Queixa inicial</strong><p class="mb-0 mt-1"><?php echo nl2br(esc($os['queixa_inicial'])); ?></p></div></div>
        </div></div>
        <div class="card mb-3"><div class="card-header"><i data-lucide="stethoscope" class="me-2"></i>Diagnóstico técnico</div><div class="card-body">
            <form method="POST" action="processar.php?acao=atualizar_diagnostico"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><label class="form-label" for="diagnostico">Diagnóstico</label><textarea class="form-control mb-3" id="diagnostico" name="diagnostico" rows="5" <?php echo tem_papel(['mecanico', 'mecanico_chefe', 'admin']) ? '' : 'readonly'; ?>><?php echo esc($os['diagnostico'] ?? ''); ?></textarea><label class="form-label" for="observacoes_internas">Observações internas</label><textarea class="form-control mb-3" id="observacoes_internas" name="observacoes_internas" rows="3" <?php echo tem_papel(['mecanico', 'mecanico_chefe', 'admin']) ? '' : 'readonly'; ?>><?php echo esc($os['observacoes_internas'] ?? ''); ?></textarea><?php if (tem_papel(['mecanico', 'mecanico_chefe', 'admin'])): ?><button class="btn btn-primary btn-icon" type="submit"><i data-lucide="save"></i>Salvar diagnóstico</button><?php endif; ?></form>
        </div></div>
        <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span><i data-lucide="package" class="me-2"></i>Peças vinculadas</span><?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico'])): ?><a href="../pedidos_pecas/cadastrar.php?os_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary"><i data-lucide="plus"></i><?php echo $_SESSION['usuario_tipo'] === 'mecanico' ? 'Solicitar peça' : 'Adicionar peça'; ?></a><?php endif; ?></div><div class="card-body"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Itens</th><th>Fornecedor</th><th>Previsão</th><th>Status</th></tr></thead><tbody><?php foreach ($pedidos_pecas as $pedido): ?><tr><td><?php echo esc($pedido['itens'] ?: '-'); ?></td><td><?php echo tem_papel(['admin', 'secretaria', 'mecanico_chefe']) ? esc($pedido['fornecedor'] ?: '-') : 'Restrito'; ?></td><td><?php echo $pedido['previsao_entrega'] ? date('d/m/Y', strtotime($pedido['previsao_entrega'])) : '-'; ?></td><td><?php echo esc(ucfirst(str_replace('_', ' ', $pedido['status']))); ?></td></tr><?php endforeach; ?><?php if (!$pedidos_pecas): ?><tr><td colspan="4" class="text-muted">Nenhuma peça solicitada.</td></tr><?php endif; ?></tbody></table></div></div></div>
        <div class="card mb-3"><div class="card-header"><i data-lucide="list-checks" class="me-2"></i>Tarefas</div><div class="card-body"><?php foreach ($tarefas as $tarefa): ?><form method="POST" action="processar.php?acao=mudar_tarefa" class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><input type="hidden" name="tarefa_id" value="<?php echo (int) $tarefa['id']; ?>"><span><?php echo esc($tarefa['descricao']); ?></span><select class="form-select form-select-sm w-auto" name="tarefa_status" onchange="this.form.submit()"><option value="pendente" <?php echo $tarefa['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option><option value="em_execucao" <?php echo $tarefa['status'] === 'em_execucao' ? 'selected' : ''; ?>>Em execução</option><option value="concluida" <?php echo $tarefa['status'] === 'concluida' ? 'selected' : ''; ?>>Concluída</option><option value="bloqueada" <?php echo $tarefa['status'] === 'bloqueada' ? 'selected' : ''; ?>>Bloqueada</option></select></form><?php endforeach; ?><?php if (!$tarefas): ?><span class="text-muted">Nenhuma tarefa definida.</span><?php endif; ?></div></div>
        <div class="card"><div class="card-header"><i data-lucide="history" class="me-2"></i>Histórico</div><div class="card-body"><div class="timeline"><?php foreach ($eventos as $evento): ?><div class="mb-3"><strong><?php echo esc($evento['descricao']); ?></strong><br><small class="text-muted"><?php echo esc($evento['autor']); ?> · <?php echo date('d/m/Y H:i', strtotime($evento['criado_em'])); ?></small></div><?php endforeach; ?></div></div></div>
    </div>
    <div class="col-lg-4">
        <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a href="../orcamentos/cadastrar.php?os_id=<?php echo $id; ?>&cliente_id=<?php echo (int) $os['cliente_id']; ?>&veiculo_id=<?php echo (int) $os['veiculo_id']; ?>" class="btn btn-primary btn-icon w-100 mb-3"><i data-lucide="file-plus-2"></i>Novo orçamento desta OS</a><?php endif; ?>
        <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><div class="card mb-3"><div class="card-header"><i data-lucide="refresh-cw" class="me-2"></i>Atualizar status</div><div class="card-body"><form method="POST" action="processar.php?acao=mudar_status"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><select class="form-select mb-3" name="status"><?php foreach ($status_nomes as $status => $nome): ?><?php if ($status !== 'entregue'): ?><option value="<?php echo esc($status); ?>" <?php echo $os['status'] === $status ? 'selected' : ''; ?>><?php echo esc($nome); ?></option><?php endif; ?><?php endforeach; ?></select><button class="btn btn-primary w-100" type="submit">Salvar status</button></form></div></div><?php endif; ?>
        <?php if ($mecanicos): ?><div class="card mb-3"><div class="card-header"><i data-lucide="user-plus" class="me-2"></i>Designar mecânico</div><div class="card-body"><form method="POST" action="processar.php?acao=atribuir"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><select class="form-select mb-3" name="usuario_id" required><option value="">Selecione</option><?php foreach ($mecanicos as $mecanico): ?><option value="<?php echo (int) $mecanico['id']; ?>"><?php echo esc($mecanico['nome']); ?> (<?php echo esc($mecanico['tipo']); ?>)</option><?php endforeach; ?></select><button class="btn btn-outline-primary w-100" type="submit">Atribuir</button></form></div></div><?php endif; ?>
        <div class="card"><div class="card-header"><i data-lucide="users" class="me-2"></i>Responsáveis</div><div class="card-body"><?php if (!$responsaveis): ?><span class="text-muted">Nenhum mecânico designado.</span><?php else: foreach ($responsaveis as $responsavel): ?><div class="mb-2"><strong><?php echo esc($responsavel['nome']); ?></strong><br><small class="text-muted"><?php echo esc($responsavel['tipo']); ?></small></div><?php endforeach; endif; ?></div></div>
        <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><div class="card mt-3"><div class="card-header"><i data-lucide="truck" class="me-2"></i>Entrega ou retirada</div><div class="card-body"><?php if ($entrega): ?><p class="mb-2"><strong><?php echo esc(ucfirst($entrega['modalidade'])); ?></strong> · <?php echo esc($entrega['status']); ?></p><small><?php echo esc($entrega['responsavel'] ?: 'Responsável não informado'); ?></small><?php else: ?><form method="POST" action="processar.php?acao=registrar_entrega"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><label class="form-label">Modalidade</label><select class="form-select mb-2" name="modalidade"><option value="retirada">Retirada pelo cliente</option><option value="entrega">Entrega pela oficina</option></select><input class="form-control mb-2" name="responsavel" maxlength="120" placeholder="Responsável / motorista"><input class="form-control mb-2" name="destino" maxlength="255" placeholder="Destino ou observação de retirada"><input class="form-control mb-2" type="number" name="km_saida" min="0" placeholder="KM na saída"><button class="btn btn-outline-primary w-100" type="submit">Registrar entrega</button></form><?php endif; ?></div></div><?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>