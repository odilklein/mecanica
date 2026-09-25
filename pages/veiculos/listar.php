<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

// ---------- Filtros (server-side) ----------
$busca = limpar($_GET['busca'] ?? '');
$cliente_id = (int) ($_GET['cliente_id'] ?? 0);

$condicoes = [];
$params = [];
if ($busca !== '') {
    $condicoes[] = '(v.placa LIKE :busca OR v.marca LIKE :busca OR v.modelo LIKE :busca OR c.nome LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if ($cliente_id > 0) {
    $condicoes[] = 'v.cliente_id = :cliente_id';
    $params[':cliente_id'] = $cliente_id;
}
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

// ---------- Paginação ----------
$por_pagina = por_pagina_atual();
$pagina = pagina_atual();

$st = $conn->prepare("SELECT COUNT(*) FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$pagina, $offset, $total_paginas] = paginacao_calcular($total, $por_pagina, $pagina);

$st = $conn->prepare("SELECT v.*, c.nome AS cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id $where ORDER BY v.marca, v.modelo LIMIT :limite OFFSET :offset");
foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$veiculos = $st->fetchAll();

$clientes_opcoes = $conn->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();
$filtros_pag = array_filter(['busca' => $busca, 'cliente_id' => $cliente_id ?: '', 'por_pagina' => $por_pagina], fn($v) => $v !== '');
$tem_filtro = $busca !== '' || $cliente_id > 0;

$titulo_pagina = "Veículos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="car" class="text-primary me-2"></i>Veículos</h1>
        <span class="page-subtitle">Gerencie os veículos dos clientes</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (tem_papel(['admin'])): ?>
        <form method="POST" action="sincronizar_fipe.php" onsubmit="this.querySelector('button').disabled=true; return confirm('Sincronizar o catálogo FIPE? Isso leva cerca de 1 minuto.');">
            <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
            <button type="submit" class="btn btn-outline-secondary btn-icon" title="Atualizar marcas/modelos da Tabela FIPE">
                <i data-lucide="refresh-cw"></i> Sincronizar FIPE
            </button>
        </form>
        <?php endif; ?>
        <a href="cadastrar.php" class="btn btn-primary btn-icon">
            <i data-lucide="plus"></i> Novo Veículo
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form class="filter-bar" method="get" action="listar.php">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" name="busca" value="<?php echo esc($busca); ?>" placeholder="Buscar por placa, marca, modelo, cliente...">
            </div>
            <div class="filter-group">
                <label for="filtroCliente">Cliente</label>
                <select class="form-select" id="filtroCliente" name="cliente_id" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($clientes_opcoes as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>" <?php echo $cliente_id === (int) $c['id'] ? 'selected' : ''; ?>><?php echo esc($c['nome']); ?></option>
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

        <div class="filter-results-info">
            <span>Exibindo <strong><?php echo count($veiculos); ?></strong> de <strong><?php echo $total; ?></strong> veículos<?php echo $tem_filtro ? ' (filtrados)' : ''; ?></span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle responsive-card">
                <thead>
                    <tr>
                        <th>Veículo</th>
                        <th>Placa</th>
                        <th>Cliente</th>
                        <th>Ano</th>
                        <th>Cor</th>
                        <th>KM Atual</th>
                        <th>Valor FIPE</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($veiculos) > 0): foreach ($veiculos as $v): ?>
                    <tr>
                        <td data-label="Veículo">
                            <i data-lucide="car" class="text-muted me-2"></i>
                            <strong><?php echo esc($v['marca']); ?> <?php echo esc($v['modelo']); ?></strong>
                        </td>
                        <td data-label="Placa"><span class="badge bg-dark"><?php echo esc($v['placa']); ?></span></td>
                        <td data-label="Cliente"><?php echo esc($v['cliente_nome']); ?></td>
                        <td data-label="Ano"><?php echo $v['ano']; ?></td>
                        <td data-label="Cor">
                            <?php
                            $cor_hex = cor_para_hex($v['cor']);
                            if ($cor_hex):
                            ?>
                                <span class="color-badge" style="background-color:<?php echo esc($cor_hex); ?>" title="<?php echo esc($v['cor']); ?>"></span>
                                <span class="ms-1"><?php echo esc($v['cor']); ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td data-label="KM Atual"><?php echo number_format($v['km_atual'], 0, ',', '.'); ?> km</td>
                        <td data-label="Valor FIPE"><?php echo !empty($v['valor_fipe']) ? '<small>' . formatar_moeda($v['valor_fipe']) . '</small>' : '<small class="text-muted">—</small>'; ?></td>
                        <td data-label="Ações" class="actions-cell">
                            <div class="dropdown table-dropdown">
                                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do veículo">
                                    <i data-lucide="more-horizontal"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                                    <h6 class="dropdown-header">Ações do veículo</h6>
                                    <a class="dropdown-item" href="editar.php?id=<?php echo $v['id']; ?>">
                                        <i data-lucide="pencil"></i> Editar
                                    </a>
                                    <form method="POST" action="excluir.php" id="excluirVeiculo<?php echo (int) $v['id']; ?>" onsubmit="return confirmarExclusao('excluirVeiculo<?php echo (int) $v['id']; ?>', 'Excluir o veículo <?php echo esc(addslashes($v['marca'])); ?> <?php echo esc(addslashes($v['modelo'])); ?> - <?php echo esc(addslashes($v['placa'])); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
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
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="<?php echo $tem_filtro ? 'filter' : 'car'; ?>"></i></div>
                                <h4><?php echo $tem_filtro ? 'Nenhum veículo encontrado' : 'Nenhum veículo cadastrado'; ?></h4>
                                <p><?php echo $tem_filtro ? 'Ajuste a busca ou limpe os filtros para ver todos os veículos.' : 'Cadastre o primeiro veículo da oficina para começar.'; ?></p>
                                <?php if (!$tem_filtro): ?>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Cadastrar Veículo
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
