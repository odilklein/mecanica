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

if (isset($_GET['marcar_notificacao'])) {
    $notif_id = (int) $_GET['marcar_notificacao'];
    $stmt = $conn->prepare('UPDATE notificacoes SET lida_em = NOW() WHERE id = :id AND usuario_id = :uid AND os_id = :os_id AND lida_em IS NULL');
    $stmt->execute([':id' => $notif_id, ':uid' => (int) $_SESSION['usuario_id'], ':os_id' => $id]);
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
$scripts_extra = '<script>window.OrdemServicoDetalheApi = ' . json_encode($caminho_base . '/api/ordens_servico.php') . ';</script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/os-detalhes.js"></script>';
$status_nomes = os_status_labels();
// Status que o operador pode escolher manualmente: o atual + os
// próximos válidos. "entregue" só via confirmação de entrega/retirada.
$status_disponiveis = array_values(array_unique(array_merge(
    [$os['status']],
    array_diff(os_status_proximos((string) $os['status']), ['entregue'])
)));
$tarefa_status_nomes = ['pendente' => 'Pendente', 'em_execucao' => 'Em execução', 'concluida' => 'Concluída', 'bloqueada' => 'Bloqueada'];
$tarefa_status_cores = ['pendente' => '', 'em_execucao' => 'info', 'concluida' => 'success', 'bloqueada' => 'danger'];
$pedido_status_nomes = ['solicitado' => 'Aguardando validação', 'validado' => 'Validado', 'pedido' => 'Pedido realizado', 'a_caminho' => 'A caminho', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado'];
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-clipboard-check text-primary me-2"></i>OS #<?php echo str_pad((int) $os['numero'], 4, '0', STR_PAD_LEFT); ?></h1>
        <span class="page-subtitle"><span class="badge badge-status-<?php echo esc($os['status']); ?>"><?php echo esc($status_nomes[$os['status']] ?? $os['status']); ?></span></span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i>Voltar para fila</a>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header"><i class="icon-base ti tabler-car me-2"></i>Atendimento</div><div class="card-body">
            <div class="row g-3"><div class="col-md-6"><strong>Cliente</strong><br><?php echo esc($os['cliente']); ?><?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><br><small><?php echo esc($os['telefone'] ?: 'Sem telefone'); ?></small><?php endif; ?></div><div class="col-md-6"><strong>Veículo</strong><br><?php echo esc(trim(($os['marca'] ?? '') . ' ' . ($os['modelo'] ?? '')) ?: 'Não informado'); ?><br><small><?php echo esc($os['placa'] ?: ''); ?></small></div><div class="col-12"><strong>Queixa inicial</strong><p class="mb-0 mt-1"><?php echo nl2br(esc($os['queixa_inicial'])); ?></p></div></div>
        </div></div>
        <div class="card mb-3"><div class="card-header"><i class="icon-base ti tabler-stethoscope me-2"></i>Diagnóstico técnico</div><div class="card-body">
            <form method="POST" data-os-acao="atualizar_diagnostico"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><label class="form-label-with-icon" for="diagnostico"><i class="icon-base ti tabler-stethoscope"></i> Diagnóstico</label><textarea class="form-control mb-3" id="diagnostico" name="diagnostico" rows="5" <?php echo tem_papel(['mecanico', 'mecanico_chefe', 'admin']) ? '' : 'readonly'; ?>><?php echo esc($os['diagnostico'] ?? ''); ?></textarea><label class="form-label-with-icon" for="observacoes_internas"><i class="icon-base ti tabler-notebook"></i> Observações internas</label><textarea class="form-control mb-3" id="observacoes_internas" name="observacoes_internas" rows="3" <?php echo tem_papel(['mecanico', 'mecanico_chefe', 'admin']) ? '' : 'readonly'; ?>><?php echo esc($os['observacoes_internas'] ?? ''); ?></textarea><?php if (tem_papel(['mecanico', 'mecanico_chefe', 'admin'])): ?><button class="btn btn-primary" type="submit"><i class="icon-base ti tabler-device-floppy"></i>Salvar diagnóstico</button><?php endif; ?></form>
        </div></div>
        <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="icon-base ti tabler-package me-2"></i>Peças vinculadas</span><?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico'])): ?><a href="../pedidos_pecas/listar.php?os_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary"><i class="icon-base ti tabler-plus"></i><?php echo $_SESSION['usuario_tipo'] === 'mecanico' ? 'Solicitar peça' : 'Adicionar peça'; ?></a><?php endif; ?></div><div class="card-body">
            <?php if ($pedidos_pecas): ?>
            <div class="dynamic-list">
                <?php foreach ($pedidos_pecas as $pedido): ?>
                <div class="dynamic-list-item">
                    <i class="icon-base ti tabler-package item-grip" style="color:var(--accent)"></i>
                    <div class="flex-grow-1">
                        <span class="item-text"><?php echo esc($pedido['itens'] ?: 'Itens não informados'); ?></span>
                        <br><small class="text-muted">
                            <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?>Fornecedor: <?php echo esc($pedido['fornecedor'] ?: '-'); ?> · <?php endif; ?>
                            Previsão: <?php echo $pedido['previsao_entrega'] ? date('d/m/Y', strtotime($pedido['previsao_entrega'])) : '-'; ?>
                        </small>
                    </div>
                    <span class="badge badge-status-<?php echo esc($pedido['status']); ?>"><?php echo esc($pedido_status_nomes[$pedido['status']] ?? $pedido['status']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:2rem 1rem;border:none;background:transparent">
                <div class="empty-state-icon" style="width:52px;height:52px;border-radius:14px"><i  style="width:24px;height:24px" class="icon-base ti tabler-package"></i></div>
                <h4 style="font-size:0.95rem">Nenhuma peça solicitada</h4>
                <p style="font-size:0.82rem">Adicione peças quando necessário para o reparo.</p>
            </div>
            <?php endif; ?>
        </div></div>
        <div class="card mb-3"><div class="card-header"><i class="icon-base ti tabler-list-check me-2"></i>Tarefas</div><div class="card-body">
            <?php if ($tarefas): ?>
            <div class="dynamic-list">
                <?php foreach ($tarefas as $tarefa): ?>
                <form method="POST" data-os-acao="mudar_tarefa" class="dynamic-list-item">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                    <input type="hidden" name="os_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="tarefa_id" value="<?php echo (int) $tarefa['id']; ?>">
                    <i class="icon-base ti tabler-grip-vertical item-grip"></i>
                    <span class="item-text"><?php echo esc($tarefa['descricao']); ?></span>
                    <select class="form-select form-select-sm w-auto" name="tarefa_status" onchange="this.form.submit()" style="min-width:130px">
                        <?php foreach ($tarefa_status_nomes as $key => $nome): ?>
                        <option value="<?php echo esc($key); ?>" <?php echo $tarefa['status'] === $key ? 'selected' : ''; ?>><?php echo esc($nome); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:2rem 1rem;border:none;background:transparent">
                <div class="empty-state-icon" style="width:52px;height:52px;border-radius:14px"><i  style="width:24px;height:24px" class="icon-base ti tabler-list-check"></i></div>
                <h4 style="font-size:0.95rem">Nenhuma tarefa definida</h4>
                <p style="font-size:0.82rem">Tarefas são adicionadas ao criar a OS.</p>
            </div>
            <?php endif; ?>
        </div></div>
        <div class="card"><div class="card-header"><i class="icon-base ti tabler-history me-2"></i>Histórico</div><div class="card-body"><div class="timeline"><?php foreach ($eventos as $evento): ?><div class="mb-3"><strong><?php echo esc($evento['descricao']); ?></strong><br><small class="text-muted"><?php echo esc($evento['autor']); ?> · <?php echo date('d/m/Y H:i', strtotime($evento['criado_em'])); ?></small></div><?php endforeach; ?><?php if (!$eventos): ?><span class="text-muted">Nenhum evento registrado.</span><?php endif; ?></div></div></div>
    </div>
    <div class="col-lg-4">
        <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a href="../orcamentos/editor.php?os_id=<?php echo $id; ?>&cliente_id=<?php echo (int) $os['cliente_id']; ?>&veiculo_id=<?php echo (int) $os['veiculo_id']; ?>" class="btn btn-primary w-100 mb-3"><i class="icon-base ti tabler-file-plus"></i>Novo orçamento desta OS</a><?php endif; ?>
        <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><div class="card mb-3"><div class="card-header"><i class="icon-base ti tabler-refresh me-2"></i>Atualizar status</div><div class="card-body"><form method="POST" data-os-acao="mudar_status"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><select class="form-select mb-3" name="status"><?php foreach ($status_disponiveis as $status): ?><option value="<?php echo esc($status); ?>" <?php echo $os['status'] === $status ? 'selected' : ''; ?>><?php echo esc(os_status_label($status)); ?></option><?php endforeach; ?></select><button class="btn btn-primary w-100" type="submit">Salvar status</button></form></div></div><?php endif; ?>
        <?php if ($mecanicos): ?><div class="card mb-3"><div class="card-header"><i class="icon-base ti tabler-user-plus me-2"></i>Designar mecânico</div><div class="card-body"><form method="POST" data-os-acao="atribuir"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><select class="form-select mb-3" name="usuario_id" required><option value="">Selecione</option><?php foreach ($mecanicos as $mecanico): ?><option value="<?php echo (int) $mecanico['id']; ?>"><?php echo esc($mecanico['nome']); ?> (<?php echo esc($mecanico['tipo']); ?>)</option><?php endforeach; ?></select><button class="btn btn-outline-primary w-100" type="submit">Atribuir</button></form></div></div><?php endif; ?>
        <div class="card mb-3"><div class="card-header"><i class="icon-base ti tabler-users me-2"></i>Responsáveis</div><div class="card-body">
            <?php if ($responsaveis): ?>
            <div class="dynamic-list">
                <?php foreach ($responsaveis as $responsavel): ?>
                <div class="dynamic-list-item">
                    <div class="emp-avatar" style="width:32px;height:32px;min-width:32px;border-radius:8px;font-size:0.75rem;background:linear-gradient(135deg,var(--accent-light),color-mix(in srgb,var(--accent-light) 30%,transparent));color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:600"><?php echo strtoupper(substr($responsavel['nome'], 0, 1)); ?></div>
                    <div>
                        <span class="item-text" style="font-size:0.85rem"><?php echo esc($responsavel['nome']); ?></span>
                        <br><small class="text-muted"><?php echo esc(ucfirst(str_replace('_', ' ', $responsavel['tipo']))); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:1.5rem 1rem;border:none;background:transparent">
                <p style="font-size:0.82rem;margin:0">Nenhum mecânico designado.</p>
            </div>
            <?php endif; ?>
        </div></div>
        <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><div class="card mt-3"><div class="card-header"><i class="icon-base ti tabler-truck me-2"></i>Entrega ou retirada</div><div class="card-body"><?php if ($entrega): ?><p class="mb-2"><strong><?php echo esc(ucfirst($entrega['modalidade'])); ?></strong> · <?php echo esc($entrega['status']); ?></p><small><?php echo esc($entrega['responsavel'] ?: 'Responsável não informado'); ?></small><?php else: ?><form method="POST" data-os-acao="registrar_entrega"><input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>"><input type="hidden" name="os_id" value="<?php echo $id; ?>"><label class="form-label-with-icon"><i class="icon-base ti tabler-truck"></i> Modalidade</label><select class="form-select mb-2" name="modalidade"><option value="retirada">Retirada pelo cliente</option><option value="entrega">Entrega pela oficina</option></select><label class="form-label-with-icon"><i class="icon-base ti tabler-user"></i> Responsável</label><input class="form-control mb-2" name="responsavel" maxlength="120" placeholder="Nome do responsável / motorista"><label class="form-label-with-icon"><i class="icon-base ti tabler-map-pin"></i> Destino</label><input class="form-control mb-2" name="destino" maxlength="255" placeholder="Destino ou observação"><label class="form-label-with-icon"><i class="icon-base ti tabler-gauge"></i> KM na saída</label><input class="form-control mb-2" type="number" name="km_saida" min="0" placeholder="0"><button class="btn btn-outline-primary w-100" type="submit"><i class="icon-base ti tabler-device-floppy"></i> Registrar entrega</button></form><?php endif; ?></div></div><?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
