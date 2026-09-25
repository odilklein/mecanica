<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

// ---------- Filtros (server-side) ----------
$busca = limpar($_GET['busca'] ?? '');
$status = $_GET['status'] ?? '';
$periodo = $_GET['periodo'] ?? '';
$status_validos = ['aberto', 'aprovado', 'reprovado', 'cancelado'];

$condicoes = [];
$params = [];
if ($busca !== '') {
    $condicoes[] = '(CAST(o.numero AS CHAR) LIKE :busca OR c.nome LIKE :busca OR v.marca LIKE :busca OR v.modelo LIKE :busca OR v.placa LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if (in_array($status, $status_validos, true)) {
    $condicoes[] = 'o.status = :status';
    $params[':status'] = $status;
}
if ($periodo === 'hoje') {
    $condicoes[] = 'DATE(o.data_orcamento) = CURDATE()';
} elseif ($periodo === 'ultimos7') {
    $condicoes[] = 'o.data_orcamento >= (CURDATE() - INTERVAL 6 DAY)';
} elseif ($periodo === 'ultimos30') {
    $condicoes[] = 'o.data_orcamento >= (CURDATE() - INTERVAL 29 DAY)';
}
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

// ---------- Paginação ----------
$por_pagina = por_pagina_atual();
$pagina = pagina_atual();

$st = $conn->prepare("SELECT COUNT(*) FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id LEFT JOIN veiculos v ON v.id = o.veiculo_id $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$pagina, $offset, $total_paginas] = paginacao_calcular($total, $por_pagina, $pagina);

$st = $conn->prepare("SELECT o.*, c.nome AS cliente_nome, v.marca, v.modelo, v.placa FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id LEFT JOIN veiculos v ON v.id = o.veiculo_id $where ORDER BY o.id DESC LIMIT :limite OFFSET :offset");
foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$orcamentos = $st->fetchAll();

$filtros_pag = array_filter(['busca' => $busca, 'status' => $status, 'periodo' => $periodo, 'por_pagina' => $por_pagina], fn($v) => $v !== '');
$tem_filtro = $busca !== '' || $status !== '' || $periodo !== '';

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
        <form class="filter-bar" method="get" action="listar.php">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" name="busca" value="<?php echo esc($busca); ?>" placeholder="Buscar por número, cliente, veículo ou placa...">
            </div>
            <div class="filter-group">
                <label for="filtroStatus">Status</label>
                <select class="form-select" id="filtroStatus" name="status" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="aberto" <?php echo $status === 'aberto' ? 'selected' : ''; ?>>Aberto</option>
                    <option value="aprovado" <?php echo $status === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                    <option value="reprovado" <?php echo $status === 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                    <option value="cancelado" <?php echo $status === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroPeriodo">Período</label>
                <select class="form-select" id="filtroPeriodo" name="periodo" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="hoje" <?php echo $periodo === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                    <option value="ultimos7" <?php echo $periodo === 'ultimos7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                    <option value="ultimos30" <?php echo $periodo === 'ultimos30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroPorPagina">Por página</label>
                <select class="form-select" id="filtroPorPagina" name="por_pagina" onchange="this.form.submit()">
                    <?php echo opcoes_por_pagina($por_pagina); ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit"><i data-lucide="search"></i> Filtrar</button>
            <a class="btn btn-outline-secondary btn-reset-filters" href="listar.php"><i data-lucide="filter-x"></i> Limpar</a>
        </form>

        <div class="filter-results-info">
            <span>Exibindo <strong><?php echo count($orcamentos); ?></strong> de <strong><?php echo $total; ?></strong> orçamentos<?php echo $tem_filtro ? ' (filtrados)' : ''; ?></span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle responsive-card">
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
                        <td data-label="Cliente"><?php echo esc($o['cliente_nome']); ?></td>
                        <td data-label="Veículo">
                            <?php if ($o['marca']): ?>
                                <?php echo esc($o['marca']) . ' ' . esc($o['modelo']); ?>
                                <br><small><span class="badge bg-dark"><?php echo esc($o['placa']); ?></span></small>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td data-label="Data"><small><?php echo date('d/m/Y', strtotime($o['data_orcamento'])); ?></small></td>
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
                                <div class="empty-state-icon"><i data-lucide="<?php echo $tem_filtro ? 'filter' : 'receipt-text'; ?>"></i></div>
                                <h4><?php echo $tem_filtro ? 'Nenhum orçamento encontrado' : 'Nenhum orçamento cadastrado'; ?></h4>
                                <p><?php echo $tem_filtro ? 'Ajuste a busca ou limpe os filtros para ver todos os orçamentos.' : 'Crie um novo orçamento para seus clientes.'; ?></p>
                                <?php if (!$tem_filtro): ?>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Novo Orçamento
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php echo paginacao_links($pagina, $total_paginas, $filtros_pag); ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
