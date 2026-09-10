<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$titulo_pagina = "Orçamentos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

// Filtros
$filtro_status = $_GET['status'] ?? 'todos';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="file-text" class="text-primary me-2"></i>Orçamentos</h1>
        <span class="page-subtitle">Crie e gerencie orçamentos para clientes</span>
    </div>
    <a href="cadastrar.php" class="btn btn-primary btn-icon">
        <i data-lucide="plus"></i> Novo Orçamento
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label mb-0">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="todos" <?php echo $filtro_status == 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="aberto" <?php echo $filtro_status == 'aberto' ? 'selected' : ''; ?>>Aberto</option>
                    <option value="aprovado" <?php echo $filtro_status == 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                    <option value="reprovado" <?php echo $filtro_status == 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                    <option value="cancelado" <?php echo $filtro_status == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-6"></div>
            <div class="col-md-3 text-end">
                <a href="listar.php" class="btn btn-outline-secondary btn-sm">Limpar</a>
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
                        <th>Nº</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Data</th>
                        <th>Validade</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT o.*, c.nome as cliente_nome, v.marca, v.modelo, v.placa FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id LEFT JOIN veiculos v ON v.id = o.veiculo_id WHERE 1=1";
                    $params = [];

                    if ($filtro_status != 'todos') {
                        $sql .= " AND o.status = :status";
                        $params[':status'] = $filtro_status;
                    }

                    $sql .= " ORDER BY o.id DESC LIMIT 50";
                    $stmt = $conn->prepare($sql);
                    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
                    $stmt->execute();
                    $orcamentos = $stmt->fetchAll();

                    $badge_status = [
                        'aberto' => 'info',
                        'aprovado' => 'success',
                        'reprovado' => 'danger',
                        'cancelado' => 'secondary'
                    ];

                    if (count($orcamentos) > 0):
                        foreach ($orcamentos as $o):
                            $expirado = !empty($o['data_validade']) && strtotime($o['data_validade']) < strtotime(date('Y-m-d')) && $o['status'] == 'aberto';
                    ?>
                    <tr>
                        <td><strong>#<?php echo str_pad($o['numero'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                        <td><?php echo limpar($o['cliente_nome']); ?></td>
                        <td>
                            <?php if ($o['marca']): ?>
                                <?php echo limpar($o['marca']) . ' ' . limpar($o['modelo']); ?>
                                <br><small><span class="badge bg-dark"><?php echo limpar($o['placa']); ?></span></small>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><small><?php echo date('d/m/Y', strtotime($o['data_orcamento'])); ?></small></td>
                        <td>
                            <?php if ($o['data_validade']): ?>
                                <small class="<?php echo $expirado ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo date('d/m/Y', strtotime($o['data_validade'])); ?>
                                    <?php echo $expirado ? ' <i data-lucide="triangle-alert"></i>' : ''; ?>
                                </small>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td class="fw-600 text-success"><?php echo formatar_moeda($o['total_geral']); ?></td>
                        <td><span class="badge badge-<?php echo $badge_status[$o['status']]; ?>"><?php echo ucfirst($o['status']); ?></span></td>
                        <td class="actions-cell">
                            <a href="detalhes.php?id=<?php echo $o['id']; ?>" class="btn btn-outline-info btn-sm" title="Ver detalhes">
                                <i data-lucide="eye"></i>
                            </a>
                            <a href="imprimir.php?id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-outline-dark btn-sm" title="Imprimir">
                                <i data-lucide="printer"></i>
                            </a>
                            <?php if ($o['status'] == 'aberto'): ?>
                            <a href="editar.php?id=<?php echo $o['id']; ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                                <i data-lucide="pencil"></i>
                            </a>
                            <form method="POST" action="processar.php?acao=excluir" id="excluirOrc<?php echo $o['id']; ?>" class="d-inline" onsubmit="return confirmarExclusao('excluirOrc<?php echo $o['id']; ?>', 'Excluir o orçamento #<?php echo str_pad($o['numero'], 4, '0', STR_PAD_LEFT); ?>?');">
                                <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir"><i data-lucide="trash-2"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="receipt-text"></i></div>
                                <h4>Nenhum orçamento encontrado</h4>
                                <p>Crie um novo orçamento para seus clientes.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Novo Orçamento
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
