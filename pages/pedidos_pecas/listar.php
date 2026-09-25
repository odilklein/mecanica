<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
$status_nomes = ['solicitado' => 'Aguardando validação', 'validado' => 'Validado', 'pedido' => 'Pedido realizado', 'a_caminho' => 'A caminho', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado'];

$from = 'FROM pedidos_pecas p
    JOIN ordens_servico os ON os.id = p.os_id
    JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN veiculos v ON v.id = os.veiculo_id';

// ---------- Filtros (server-side) ----------
$busca = limpar($_GET['busca'] ?? '');
$status = $_GET['status'] ?? '';

$condicoes = [];
$params = [];
// Mecânico enxerga apenas pedidos das OS atribuídas a ele
if ($_SESSION['usuario_tipo'] === 'mecanico') {
    $condicoes[] = 'EXISTS (SELECT 1 FROM os_responsaveis r WHERE r.os_id = os.id AND r.usuario_id = :uid_scope AND r.concluido_em IS NULL)';
    $condicoes[] = "(p.criado_por = :uid_criado OR p.status IN ('validado', 'pedido', 'a_caminho', 'recebido'))";
    $params[':uid_scope'] = (int) $_SESSION['usuario_id'];
    $params[':uid_criado'] = (int) $_SESSION['usuario_id'];
}
if ($busca !== '') {
    $condicoes[] = '(p.id LIKE :busca OR CAST(os.numero AS CHAR) LIKE :busca OR c.nome LIKE :busca OR p.fornecedor LIKE :busca
        OR EXISTS (SELECT 1 FROM pedido_pecas_itens pi WHERE pi.pedido_id = p.id AND pi.descricao LIKE :busca))';
    $params[':busca'] = '%' . $busca . '%';
}
if (array_key_exists($status, $status_nomes)) {
    $condicoes[] = 'p.status = :status';
    $params[':status'] = $status;
}
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

// ---------- Paginação ----------
$por_pagina = por_pagina_atual();
$pagina = pagina_atual();

$st = $conn->prepare("SELECT COUNT(*) $from $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$pagina, $offset, $total_paginas] = paginacao_calcular($total, $por_pagina, $pagina);

$st = $conn->prepare("SELECT p.id, p.os_id, p.status, p.fornecedor, p.previsao_entrega, p.criado_em, os.numero, c.nome AS cliente, v.modelo, v.placa, GROUP_CONCAT(pi.descricao SEPARATOR ', ') AS itens
    $from
    LEFT JOIN pedido_pecas_itens pi ON pi.pedido_id = p.id
    $where
    GROUP BY p.id
    ORDER BY p.criado_em DESC
    LIMIT :limite OFFSET :offset");
foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$pedidos = $st->fetchAll();

$filtros_pag = array_filter(['busca' => $busca, 'status' => $status, 'por_pagina' => $por_pagina], fn($v) => $v !== '');
$tem_filtro = $busca !== '' || $status !== '';

$titulo_pagina = 'Pedidos de peças';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header"><div><h1><i data-lucide="truck" class="text-primary me-2"></i>Pedidos de peças</h1><span class="page-subtitle"><?php echo $_SESSION['usuario_tipo'] === 'mecanico' ? 'Solicitações das suas ordens de serviço' : 'Valide solicitações e acompanhe compras'; ?></span></div><?php if ($_SESSION['usuario_tipo'] !== 'mecanico'): ?><a href="cadastrar.php" class="btn btn-primary btn-icon"> <i data-lucide="plus"></i>Novo pedido</a><?php endif; ?></div>

<div class="card"><div class="card-body p-0">
    <form class="filter-bar mb-0 rounded-bottom-0" method="get" action="listar.php">
        <div class="filter-search">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" class="form-control" name="busca" value="<?php echo esc($busca); ?>" placeholder="Buscar por pedido, OS, cliente, item ou fornecedor...">
        </div>
        <div class="filter-group">
            <label for="filtroStatusPedido">Status</label>
            <select class="form-select" id="filtroStatusPedido" name="status" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($status_nomes as $key => $label) : ?>
                    <option value="<?php echo esc($key); ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo esc($label); ?></option>
                <?php endforeach; ?>
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

    <div class="px-3 pt-3 filter-results-info">
        <span>Exibindo <strong><?php echo count($pedidos); ?></strong> de <strong><?php echo $total; ?></strong> pedidos<?php echo $tem_filtro ? ' (filtrados)' : ''; ?></span>
    </div>

    <div class="table-responsive"><table class="table table-hover align-middle mb-0 responsive-card"><thead><tr><th>Pedido</th><th>OS / Cliente</th><th>Itens</th><th>Fornecedor</th><th>Previsão</th><th>Status</th><th>Ação</th></tr></thead><tbody>
    <?php foreach ($pedidos as $pedido): ?><tr><td data-label="Pedido">#<?php echo (int) $pedido['id']; ?></td><td data-label="OS / Cliente"><a href="../ordens_servico/detalhes.php?id=<?php echo (int) $pedido['os_id']; ?>">OS #<?php echo str_pad((int) $pedido['numero'], 4, '0', STR_PAD_LEFT); ?></a><?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><br><small><?php echo esc($pedido['cliente']); ?> · <?php echo esc($pedido['placa'] ?: $pedido['modelo'] ?: 'Sem veículo'); ?></small><?php endif; ?></td><td data-label="Itens"><?php echo esc($pedido['itens'] ?: '-'); ?></td><td data-label="Fornecedor"><?php echo tem_papel(['admin', 'secretaria', 'mecanico_chefe']) ? esc($pedido['fornecedor'] ?: '-') : 'Restrito'; ?></td><td data-label="Previsão"><?php echo $pedido['previsao_entrega'] ? date('d/m/Y', strtotime($pedido['previsao_entrega'])) : '-'; ?></td><td data-label="Status"><span class="badge badge-status-<?php echo esc($pedido['status']); ?>"><?php echo esc($status_nomes[$pedido['status']] ?? $pedido['status']); ?></span></td><td data-label="Ação" class="actions-cell"><div class="dropdown table-dropdown"><button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do pedido"><i data-lucide="more-horizontal"></i></button><div class="dropdown-menu dropdown-menu-end table-dropdown-menu"><h6 class="dropdown-header">Ações do pedido</h6><?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a class="dropdown-item" href="editar.php?id=<?php echo (int) $pedido['id']; ?>"><i data-lucide="pencil"></i> Atualizar pedido</a><?php else: ?><span class="dropdown-item-text text-muted"><i data-lucide="eye"></i> Acompanhar</span><?php endif; ?></div></div></td></tr><?php endforeach; ?>
    <?php if (!$pedidos): ?><tr class="row-empty"><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="<?php echo $tem_filtro ? 'filter' : 'package-search'; ?>"></i></div><h4><?php echo $tem_filtro ? 'Nenhum pedido encontrado' : 'Nenhum pedido de peça'; ?></h4><p><?php echo $tem_filtro ? 'Ajuste a busca ou limpe os filtros para ver todos os pedidos.' : ($_SESSION['usuario_tipo'] === 'mecanico' ? 'Nenhuma solicitação de peça encontrada para suas OS.' : 'Nenhum pedido cadastrado ainda.'); ?></p><?php if (!$tem_filtro && $_SESSION['usuario_tipo'] !== 'mecanico'): ?><a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="plus"></i> Novo pedido</a><?php endif; ?></div></td></tr><?php endif; ?>
    </tbody></table></div>

    <?php echo paginacao_links($pagina, $total_paginas, $filtros_pag); ?>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
