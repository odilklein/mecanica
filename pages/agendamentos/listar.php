<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$titulo_pagina = "Agendamentos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

// Filtro de status
$filtro_status = $_GET['status'] ?? 'todos';

// Filtro de data
$filtro_data = $_GET['data'] ?? ($_POST['data'] ?? '');
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-calendar-check text-primary me-2"></i>Agendamentos</h1>
        <span class="page-subtitle">Gerencie os agendamentos da oficina</span>
    </div>
    <a href="cadastrar.php" class="btn btn-primary btn-icon">
        <i class="fas fa-plus"></i> Novo Agendamento
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label mb-0">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="todos" <?php echo $filtro_status == 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="agendado" <?php echo $filtro_status == 'agendado' ? 'selected' : ''; ?>>Agendado</option>
                    <option value="confirmado" <?php echo $filtro_status == 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                    <option value="concluido" <?php echo $filtro_status == 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                    <option value="cancelado" <?php echo $filtro_status == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-0">Data</label>
                <input type="date" name="data" class="form-control form-control-sm" value="<?php echo $filtro_data; ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-6 text-end">
                <a href="listar.php" class="btn btn-outline-secondary btn-sm">Limpar filtros</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
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
                    <?php
                    $sql = "SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone, v.marca, v.modelo, v.placa, s.nome as servico_nome FROM agendamentos a LEFT JOIN clientes c ON c.id = a.cliente_id LEFT JOIN veiculos v ON v.id = a.veiculo_id LEFT JOIN servicos s ON s.id = a.servico_id WHERE 1=1";

                    $params = [];
                    if ($filtro_status != 'todos') {
                        $sql .= " AND a.status = :status";
                        $params[':status'] = $filtro_status;
                    }

                    if (!empty($filtro_data)) {
                        $sql .= " AND a.data_agendamento = :data";
                        $params[':data'] = $filtro_data;
                    }

                    // Se nao tiver filtro, mostra proximos 7 dias por padrao
                    if ($filtro_status == 'todos' && empty($filtro_data)) {
                        $sql .= " AND a.data_agendamento >= CURDATE()";
                    }

                    $sql .= " ORDER BY a.data_agendamento ASC, a.hora ASC";

                    $stmt = $conn->prepare($sql);
                    foreach ($params as $k => $v) {
                        $stmt->bindValue($k, $v);
                    }
                    $stmt->execute();
                    $agendamentos = $stmt->fetchAll();

                    if (count($agendamentos) > 0):
                        foreach ($agendamentos as $a):
                            $badge_status = [
                                'agendado' => 'info',
                                'confirmado' => 'success',
                                'concluido' => 'secondary',
                                'cancelado' => 'danger'
                            ];
                            $status_txt = ucfirst($a['status']);
                    ?>
                    <tr>
                        <td><strong><?php echo date('d/m/Y', strtotime($a['data_agendamento'])); ?></strong></td>
                        <td><span class="badge bg-primary"><?php echo date('H:i', strtotime($a['hora'])); ?></span></td>
                        <td>
                            <strong><?php echo limpar($a['cliente_nome']); ?></strong>
                            <br><small class="text-muted"><?php echo limpar($a['cliente_telefone']); ?></small>
                        </td>
                        <td>
                            <?php if ($a['marca']): ?>
                                <?php echo limpar($a['marca']) . ' ' . limpar($a['modelo']); ?>
                                <br><small class="text-muted"><span class="badge bg-dark"><?php echo limpar($a['placa']); ?></span></small>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><?php echo limpar($a['servico_nome'] ?: '-'); ?></td>
                        <td><span class="badge badge-<?php echo $badge_status[$a['status']]; ?>"><?php echo $status_txt; ?></span></td>
                        <td class="actions-cell">
                            <a href="editar.php?id=<?php echo $a['id']; ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($a['status'] != 'cancelado'): ?>
                            <form method="POST" action="processar.php?acao=mudar_status" class="d-inline" id="concluir<?php echo $a['id']; ?>" onsubmit="return confirmarExclusao('concluir<?php echo $a['id']; ?>', 'Marcar este agendamento como concluído?');">
                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                <input type="hidden" name="status" value="concluido">
                                <button type="submit" class="btn btn-outline-success btn-sm" title="Concluir"><i class="fas fa-check"></i></button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="excluir.php" id="excluirAgend<?php echo $a['id']; ?>" class="d-inline" onsubmit="return confirmarExclusao('excluirAgend<?php echo $a['id']; ?>', 'Excluir este agendamento?');">
                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h4>Nenhum agendamento encontrado</h4>
                                <p>Cadastre um novo agendamento ou altere os filtros.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i class="fas fa-plus"></i> Novo Agendamento
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
