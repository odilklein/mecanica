<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$stmt = $conn->query("SELECT o.*, c.nome as cliente_nome, v.marca, v.modelo, v.placa FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id LEFT JOIN veiculos v ON v.id = o.veiculo_id ORDER BY o.id DESC");
$orcamentos = $stmt->fetchAll();

$titulo_pagina = "Orçamentos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$badge_status = [
    'aberto' => 'info',
    'aprovado' => 'success',
    'reprovado' => 'danger',
    'cancelado' => 'secondary'
];
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

<div class="card">
    <div class="card-body">
        <div class="filter-bar">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" id="buscarOrcamento" data-filtro-busca="tabelaOrcamentos" placeholder="Buscar por número, cliente, veículo ou placa...">
            </div>
            <div class="filter-group">
                <label for="filtroStatus">Status</label>
                <select class="form-select" id="filtroStatus" data-filtro-tabela="tabelaOrcamentos" data-filtro-coluna="Status">
                    <option value="">Todos</option>
                    <option value="Aberto">Aberto</option>
                    <option value="Aprovado">Aprovado</option>
                    <option value="Reprovado">Reprovado</option>
                    <option value="Cancelado">Cancelado</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroPeriodo">Período</label>
                <select class="form-select" id="filtroPeriodo" data-filtro-tabela="tabelaOrcamentos" data-filtro-coluna="Data" data-filtro-data-range="date">
                    <option value="">Todos</option>
                    <option value="hoje">Hoje</option>
                    <option value="ultimos7">Últimos 7 dias</option>
                    <option value="ultimos30">Últimos 30 dias</option>
                </select>
            </div>
            <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="limparFiltros('tabelaOrcamentos')">
                <i data-lucide="filter-x"></i> Limpar
            </button>
        </div>

        <div class="filter-results-info">
            <span>Exibindo <strong data-filtro-total="tabelaOrcamentos"><?php echo count($orcamentos); ?></strong> de <?php echo count($orcamentos); ?> orçamentos</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle responsive-card" id="tabelaOrcamentos">
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
                    <?php if (count($orcamentos) > 0): foreach ($orcamentos as $o):
                        $expirado = !empty($o['data_validade']) && strtotime($o['data_validade']) < strtotime(date('Y-m-d')) && $o['status'] == 'aberto';
                    ?>
                    <tr>
                        <td data-label="Nº"><strong>#<?php echo str_pad($o['numero'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                        <td data-label="Cliente"><?php echo limpar($o['cliente_nome']); ?></td>
                        <td data-label="Veículo">
                            <?php if ($o['marca']): ?>
                                <?php echo limpar($o['marca']) . ' ' . limpar($o['modelo']); ?>
                                <br><small><span class="badge bg-dark"><?php echo limpar($o['placa']); ?></span></small>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td data-label="Data" data-filtro-normalizado="<?php echo $o['data_orcamento']; ?>"><small><?php echo date('d/m/Y', strtotime($o['data_orcamento'])); ?></small></td>
                        <td data-label="Validade">
                            <?php if ($o['data_validade']): ?>
                                <small class="<?php echo $expirado ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo date('d/m/Y', strtotime($o['data_validade'])); ?>
                                    <?php echo $expirado ? ' <i data-lucide="triangle-alert"></i>' : ''; ?>
                                    <?php echo $expirado ? '<br><span class="badge badge-warning" style="font-size:.65rem;">Expirado</span>' : ''; ?>
                                </small>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td data-label="Total" class="fw-600 text-success"><?php echo formatar_moeda($o['total_geral']); ?></td>
                        <td data-label="Status"><span class="badge badge-<?php echo $badge_status[$o['status']] ?? 'secondary'; ?>"><?php echo ucfirst($o['status']); ?></span></td>
                        <td data-label="Ações" class="actions-cell">
                            <div class="dropdown table-dropdown">
                                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do orçamento">
                                    <i data-lucide="more-horizontal"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                                    <h6 class="dropdown-header">Ações do orçamento</h6>
                                    <a class="dropdown-item" href="detalhes.php?id=<?php echo $o['id']; ?>">
                                        <i data-lucide="eye"></i> Ver detalhes
                                    </a>
                                    <a class="dropdown-item" href="imprimir.php?id=<?php echo $o['id']; ?>" target="_blank">
                                        <i data-lucide="printer"></i> Imprimir
                                    </a>
                                    <?php if ($o['status'] == 'aberto'): ?>
                                    <a class="dropdown-item" href="editar.php?id=<?php echo $o['id']; ?>">
                                        <i data-lucide="pencil"></i> Editar
                                    </a>
                                    <form method="POST" action="processar.php?acao=excluir" id="excluirOrc<?php echo $o['id']; ?>" onsubmit="return confirmarExclusao('excluirOrc<?php echo $o['id']; ?>', 'Excluir o orçamento #<?php echo str_pad($o['numero'], 4, '0', STR_PAD_LEFT); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                                        <button type="submit" class="dropdown-item text-danger"><i data-lucide="trash-2"></i> Excluir</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr class="row-empty">
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
                    <tr class="row-filtro-vazio" data-filtro-vazio="tabelaOrcamentos" style="display:none;">
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="filter"></i></div>
                                <h4>Nenhum orçamento encontrado com esses filtros</h4>
                                <p>Ajuste a busca ou limpe os filtros para ver todos os orçamentos.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>