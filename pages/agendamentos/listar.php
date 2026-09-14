<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$stmt = $conn->prepare("SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone, v.marca, v.modelo, v.placa, s.nome as servico_nome
    FROM agendamentos a
    LEFT JOIN clientes c ON c.id = a.cliente_id
    LEFT JOIN veiculos v ON v.id = a.veiculo_id
    LEFT JOIN servicos s ON s.id = a.servico_id
    ORDER BY a.data_agendamento ASC, a.hora ASC");
$stmt->execute();
$agendamentos = $stmt->fetchAll();

$titulo_pagina = "Agendamentos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$badge_status = [
    'agendado' => 'info',
    'confirmado' => 'success',
    'concluido' => 'secondary',
    'cancelado' => 'danger'
];
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="calendar-check" class="text-primary me-2"></i>Agendamentos</h1>
        <span class="page-subtitle">Gerencie os agendamentos da oficina</span>
    </div>
    <a href="cadastrar.php" class="btn btn-primary btn-icon">
        <i data-lucide="plus"></i> Novo Agendamento
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="filter-bar">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" id="buscarAgendamento" data-filtro-busca="tabelaAgendamentos" placeholder="Buscar por cliente, veículo, placa ou serviço...">
            </div>
            <div class="filter-group">
                <label for="filtroStatus">Status</label>
                <select class="form-select" id="filtroStatus" data-filtro-tabela="tabelaAgendamentos" data-filtro-coluna="Status">
                    <option value="">Todos</option>
                    <option value="Agendado">Agendado</option>
                    <option value="Confirmado">Confirmado</option>
                    <option value="Concluido">Concluído</option>
                    <option value="Cancelado">Cancelado</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroPeriodo">Período</label>
                <select class="form-select" id="filtroPeriodo" data-filtro-tabela="tabelaAgendamentos" data-filtro-coluna="Data" data-filtro-data-range="date">
                    <option value="">Todos</option>
                    <option value="hoje">Hoje</option>
                    <option value="proximos7">Próximos 7 dias</option>
                </select>
            </div>
            <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="limparFiltros('tabelaAgendamentos')">
                <i data-lucide="filter-x"></i> Limpar
            </button>
        </div>

        <div class="filter-results-info">
            <span>Exibindo <strong data-filtro-total="tabelaAgendamentos"><?php echo count($agendamentos); ?></strong> de <?php echo count($agendamentos); ?> agendamentos</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tabelaAgendamentos">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Hora</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Serviço</th>
                        <th>Status</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($agendamentos) > 0): foreach ($agendamentos as $a):
                        $status_txt = ucfirst($a['status']);
                    ?>
                    <tr>
                        <td data-label="Data" data-filtro-normalizado="<?php echo $a['data_agendamento']; ?>"><strong><?php echo date('d/m/Y', strtotime($a['data_agendamento'])); ?></strong></td>
                        <td data-label="Hora"><span class="badge bg-primary"><?php echo date('H:i', strtotime($a['hora'])); ?></span></td>
                        <td data-label="Cliente">
                            <strong><?php echo esc($a['cliente_nome']); ?></strong>
                            <br><small class="text-muted"><?php echo esc($a['cliente_telefone']); ?></small>
                        </td>
                        <td data-label="Veículo">
                            <?php if ($a['marca']): ?>
                                <?php echo esc($a['marca']) . ' ' . esc($a['modelo']); ?>
                                <br><small class="text-muted"><span class="badge bg-dark"><?php echo esc($a['placa']); ?></span></small>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td data-label="Serviço"><?php echo esc($a['servico_nome'] ?: '-'); ?></td>
                        <td data-label="Status"><span class="badge badge-<?php echo $badge_status[$a['status']]; ?>"><?php echo $status_txt; ?></span></td>
                        <td data-label="Ações" class="actions-cell">
                            <div class="dropdown table-dropdown">
                                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do agendamento">
                                    <i data-lucide="more-horizontal"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                                    <h6 class="dropdown-header">Ações do agendamento</h6>
                                    <?php if ($a['status'] != 'cancelado'): ?>
                                    <form method="POST" action="processar.php?acao=mudar_status" id="concluir<?php echo $a['id']; ?>" onsubmit="return confirmarExclusao('concluir<?php echo $a['id']; ?>', 'Marcar este agendamento como concluído?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <input type="hidden" name="status" value="concluido">
                                        <button type="submit" class="dropdown-item">
                                            <i data-lucide="check"></i> Concluir
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a class="dropdown-item" href="editar.php?id=<?php echo $a['id']; ?>">
                                        <i data-lucide="pencil"></i> Editar
                                    </a>
                                    <form method="POST" action="excluir.php" id="excluirAgend<?php echo $a['id']; ?>" onsubmit="return confirmarExclusao('excluirAgend<?php echo $a['id']; ?>', 'Excluir este agendamento?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i data-lucide="trash-2"></i> Excluir
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr class="row-empty">
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="calendar-x"></i></div>
                                <h4>Nenhum agendamento encontrado</h4>
                                <p>Cadastre um novo agendamento para começar.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Novo Agendamento
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="row-filtro-vazio" data-filtro-vazio="tabelaAgendamentos" style="display:none;">
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="filter"></i></div>
                                <h4>Nenhum agendamento encontrado com esses filtros</h4>
                                <p>Ajuste a busca ou limpe os filtros para ver todos os agendamentos.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>