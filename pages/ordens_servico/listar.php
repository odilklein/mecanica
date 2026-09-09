<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
$titulo_pagina = 'Ordens de serviço';
$stmt = $conn->query("SELECT os.id, os.numero, os.status, os.prioridade, os.queixa_inicial, os.data_entrada, c.nome AS cliente, c.telefone, v.marca, v.modelo, v.placa FROM ordens_servico os JOIN clientes c ON c.id = os.cliente_id LEFT JOIN veiculos v ON v.id = os.veiculo_id WHERE os.status NOT IN ('entregue', 'cancelada') ORDER BY FIELD(os.prioridade, 'urgente', 'alta', 'normal', 'baixa'), os.data_entrada ASC");
$ordens = $stmt->fetchAll();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
$status_nomes = ['recebida' => 'Recebida', 'triagem' => 'Triagem', 'aguardando_diagnostico' => 'Aguardando diagnóstico', 'aguardando_orcamento' => 'Aguardando orçamento', 'aguardando_aprovacao' => 'Aguardando aprovação', 'aguardando_peca' => 'Aguardando peça', 'pronta_para_execucao' => 'Pronta para execução', 'em_execucao' => 'Em execução', 'aguardando_retorno' => 'Aguardando retorno', 'pronta_para_entrega' => 'Pronta para entrega'];
?>
<div class="page-header">
    <div>
        <h1><i data-lucide="clipboard-list" class="text-primary me-2"></i>Ordens de serviço</h1>
        <span class="page-subtitle">Fila operacional da oficina</span>
    </div>
    <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="plus"></i>Novo atendimento</a><?php endif; ?>
</div>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Queixa</th><th>Prioridade</th><th>Status</th><th>Entrada</th></tr></thead>
                <tbody>
                <?php foreach ($ordens as $ordem): ?>
                    <tr>
                        <td><strong>#<?php echo str_pad((int) $ordem['numero'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                        <td><?php echo esc($ordem['cliente']); ?><br><small class="text-muted"><?php echo esc($ordem['telefone'] ?: 'Sem telefone'); ?></small></td>
                        <td><?php echo esc(trim(($ordem['marca'] ?? '') . ' ' . ($ordem['modelo'] ?? '')) ?: 'Não informado'); ?><br><small class="text-muted"><?php echo esc($ordem['placa'] ?: ''); ?></small></td>
                        <td><span title="<?php echo esc($ordem['queixa_inicial']); ?>"><?php echo esc(mb_strimwidth($ordem['queixa_inicial'], 0, 55, '...')); ?></span></td>
                        <td><?php echo esc(ucfirst($ordem['prioridade'])); ?></td>
                        <td><span class="badge badge-info"><?php echo esc($status_nomes[$ordem['status']] ?? $ordem['status']); ?></span></td>
                        <td><small><?php echo date('d/m/Y H:i', strtotime($ordem['data_entrada'])); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ordens): ?><tr><td colspan="7" class="text-center py-5 text-muted">Nenhuma ordem de serviço em aberto.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
