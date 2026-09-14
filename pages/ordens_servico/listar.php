<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
$titulo_pagina = 'Ordens de serviço';
$filtro_funcionario = $_SESSION['usuario_tipo'] === 'mecanico' ? ' JOIN os_responsaveis r ON r.os_id = os.id AND r.usuario_id = ' . (int) $_SESSION['usuario_id'] . ' AND r.concluido_em IS NULL ' : '';
$stmt = $conn->query("SELECT os.id, os.numero, os.status, os.prioridade, os.queixa_inicial, os.data_entrada, c.nome AS cliente, v.marca, v.modelo, v.placa FROM ordens_servico os JOIN clientes c ON c.id = os.cliente_id LEFT JOIN veiculos v ON v.id = os.veiculo_id $filtro_funcionario WHERE os.status NOT IN ('entregue', 'cancelada') ORDER BY FIELD(os.prioridade, 'urgente', 'alta', 'normal', 'baixa'), os.data_entrada ASC");
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
        <div class="filter-bar mb-0 rounded-bottom-0">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" id="buscarOS" data-filtro-busca="tabelaOrdens" placeholder="Buscar por OS, cliente, veículo, placa ou queixa...">
            </div>
            <div class="filter-group">
                <label for="filtroPrioridade">Prioridade</label>
                <select class="form-select" id="filtroPrioridade" data-filtro-tabela="tabelaOrdens" data-filtro-coluna="Prioridade">
                    <option value="">Todas</option>
                    <option value="Urgente">Urgente</option>
                    <option value="Alta">Alta</option>
                    <option value="Normal">Normal</option>
                    <option value="Baixa">Baixa</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroStatusOS">Status</label>
                <select class="form-select" id="filtroStatusOS" data-filtro-tabela="tabelaOrdens" data-filtro-coluna="Status">
                    <option value="">Todos</option>
                    <?php foreach ($status_nomes as $status_key => $status_label): ?>
                        <option value="<?php echo esc($status_label); ?>"><?php echo esc($status_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="limparFiltros('tabelaOrdens')">
                <i data-lucide="filter-x"></i> Limpar
            </button>
        </div>

        <div class="px-3 pt-3 filter-results-info">
            <span>Exibindo <strong data-filtro-total="tabelaOrdens"><?php echo count($ordens); ?></strong> de <?php echo count($ordens); ?> ordens de serviço</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 responsive-card" id="tabelaOrdens">
                <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Queixa</th><th>Prioridade</th><th>Status</th><th>Entrada</th></tr></thead>
                <tbody>
                <?php foreach ($ordens as $ordem): ?>
                    <tr>
                        <td data-label="OS"><a href="detalhes.php?id=<?php echo (int) $ordem['id']; ?>"><strong>#<?php echo str_pad((int) $ordem['numero'], 4, '0', STR_PAD_LEFT); ?></strong></a></td>
                        <td data-label="Cliente"><?php echo esc($ordem['cliente']); ?></td>
                        <td data-label="Veículo"><?php echo esc(trim(($ordem['marca'] ?? '') . ' ' . ($ordem['modelo'] ?? '')) ?: 'Não informado'); ?><br><small class="text-muted"><?php echo esc($ordem['placa'] ?: ''); ?></small></td>
                        <td data-label="Queixa"><span title="<?php echo esc($ordem['queixa_inicial']); ?>"><?php echo esc(mb_strimwidth($ordem['queixa_inicial'], 0, 55, '...')); ?></span></td>
                        <td data-label="Prioridade" data-filtro-normalizado="<?php echo esc(ucfirst($ordem['prioridade'])); ?>"><span class="badge badge-prioridade-<?php echo esc($ordem['prioridade']); ?>"><?php echo esc(ucfirst($ordem['prioridade'])); ?></span></td>
                        <td data-label="Status" data-filtro-normalizado="<?php echo esc($status_nomes[$ordem['status']] ?? $ordem['status']); ?>"><span class="badge badge-status-<?php echo esc($ordem['status']); ?>"><?php echo esc($status_nomes[$ordem['status']] ?? $ordem['status']); ?></span></td>
                        <td data-label="Entrada"><small><?php echo date('d/m/Y H:i', strtotime($ordem['data_entrada'])); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ordens): ?>
                    <tr class="row-empty"><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i data-lucide="clipboard-list"></i></div>
                            <h4>Nenhuma ordem de serviço em aberto</h4>
                            <p>Crie um novo atendimento para começar a acompanhar o trabalho da oficina.</p>
                            <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?>
                            <a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="plus"></i> Novo atendimento</a>
                            <?php endif; ?>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <tr class="row-filtro-vazio" data-filtro-vazio="tabelaOrdens" style="display:none;">
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i data-lucide="filter"></i></div>
                            <h4>Nenhuma ordem encontrada com esses filtros</h4>
                            <p>Ajuste a busca ou limpe os filtros para ver todas as ordens de serviço.</p>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
