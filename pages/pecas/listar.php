<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';
exigir_papel(['admin', 'secretaria']);

// ---------- Filtros (server-side) ----------
$busca = limpar($_GET['busca'] ?? '');
$categoria = limpar($_GET['categoria'] ?? '');
$status = $_GET['status'] ?? '';

$condicoes = [];
$params = [];
if ($busca !== '') {
    $condicoes[] = '(nome LIKE :busca OR codigo LIKE :busca OR descricao LIKE :busca OR categoria LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if ($categoria !== '') {
    $condicoes[] = 'categoria = :categoria';
    $params[':categoria'] = $categoria;
}
if ($status === 'em_estoque') {
    $condicoes[] = 'quantidade > estoque_minimo';
} elseif ($status === 'baixo') {
    $condicoes[] = 'quantidade <= estoque_minimo';
} elseif ($status === 'zerado') {
    $condicoes[] = 'quantidade <= 0';
}
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

// ---------- Paginação ----------
$por_pagina = por_pagina_atual();
$pagina = pagina_atual();

$st = $conn->prepare("SELECT COUNT(*) FROM pecas $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$pagina, $offset, $total_paginas] = paginacao_calcular($total, $por_pagina, $pagina);

$st = $conn->prepare("SELECT * FROM pecas $where ORDER BY nome LIMIT :limite OFFSET :offset");
foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$pecas = $st->fetchAll();

$categorias = $conn->query("SELECT DISTINCT categoria FROM pecas WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria")->fetchAll();
$estoque_baixo = (int) $conn->query("SELECT COUNT(*) FROM pecas WHERE quantidade <= estoque_minimo")->fetchColumn();
$filtros_pag = array_filter(['busca' => $busca, 'categoria' => $categoria, 'status' => $status, 'por_pagina' => $por_pagina], fn($v) => $v !== '');
$tem_filtro = $busca !== '' || $categoria !== '' || $status !== '';

$titulo_pagina = "Estoque";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="boxes" class="text-primary me-2"></i>Estoque de Peças</h1>
        <span class="page-subtitle">Controle o estoque e as peças da oficina</span>
    </div>
    <div class="d-flex gap-2">
        <?php if ($estoque_baixo > 0): ?>
            <a href="listar.php?status=baixo" class="btn btn-outline-warning btn-icon">
                <i data-lucide="triangle-alert"></i> Estoque Baixo (<?php echo $estoque_baixo; ?>)
            </a>
        <?php endif; ?>
        <a href="entrada.php" class="btn btn-outline-success btn-icon">
            <i data-lucide="log-in"></i> Entrada de Estoque
        </a>
        <a href="cadastrar.php" class="btn btn-primary btn-icon">
            <i data-lucide="plus"></i> Nova Peça
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form class="filter-bar" method="get" action="listar.php">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" name="busca" value="<?php echo esc($busca); ?>" placeholder="Buscar por nome, código ou categoria...">
            </div>
            <div class="filter-group">
                <label for="filtroCategoria">Categoria</label>
                <select class="form-select" id="filtroCategoria" name="categoria" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo esc($cat['categoria']); ?>" <?php echo $categoria === $cat['categoria'] ? 'selected' : ''; ?>><?php echo esc($cat['categoria']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroEstoque">Status</label>
                <select class="form-select" id="filtroEstoque" name="status" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="em_estoque" <?php echo $status === 'em_estoque' ? 'selected' : ''; ?>>Em estoque</option>
                    <option value="baixo" <?php echo $status === 'baixo' ? 'selected' : ''; ?>>Estoque baixo</option>
                    <option value="zerado" <?php echo $status === 'zerado' ? 'selected' : ''; ?>>Zerado</option>
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
            <span>Exibindo <strong><?php echo count($pecas); ?></strong> de <strong><?php echo $total; ?></strong> peças<?php echo $tem_filtro ? ' (filtradas)' : ''; ?></span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle responsive-card">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Peça</th>
                        <th>Categoria</th>
                        <th>Qtd</th>
                        <th>Estoque Mín.</th>
                        <th>Preço Compra</th>
                        <th>Preço Venda</th>
                        <th>Status</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pecas) > 0): foreach ($pecas as $p):
                        $status_txt = '';
                        $status_badge = 'success';
                        if ($p['quantidade'] <= 0) {
                            $status_txt = 'Zerado';
                            $status_badge = 'danger';
                        } elseif ($p['quantidade'] <= $p['estoque_minimo']) {
                            $status_txt = 'Estoque Baixo';
                            $status_badge = 'warning';
                        } else {
                            $status_txt = 'Em estoque';
                            $status_badge = 'success';
                        }
                    ?>
                    <tr class="<?php echo ($p['quantidade'] <= $p['estoque_minimo']) ? 'estoque-alert-row' : ''; ?>">
                        <td data-label="Código"><span class="badge bg-secondary"><?php echo esc($p['codigo']); ?></span></td>
                        <td data-label="Peça">
                            <strong><?php echo esc($p['nome']); ?></strong>
                            <?php if ($p['descricao']): ?>
                                <br><small class="text-muted"><?php echo esc(substr($p['descricao'], 0, 50)) . (strlen($p['descricao']) > 50 ? '...' : ''); ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Categoria"><?php echo esc($p['categoria'] ?: '-'); ?></td>
                        <td data-label="Qtd">
                            <strong class="<?php echo ($p['quantidade'] <= $p['estoque_minimo']) ? 'text-warning' : ''; ?>">
                                <?php echo (int) $p['quantidade']; ?>
                            </strong>
                        </td>
                        <td data-label="Estoque Mín."><?php echo (int) $p['estoque_minimo']; ?></td>
                        <td data-label="Preço Compra"><?php echo formatar_moeda($p['preco_compra']); ?></td>
                        <td data-label="Preço Venda" class="fw-600"><?php echo formatar_moeda($p['preco_venda']); ?></td>
                        <td data-label="Status"><span class="badge badge-<?php echo $status_badge; ?>"><?php echo $status_txt; ?></span></td>
                        <td data-label="Ações" class="actions-cell">
                            <div class="dropdown table-dropdown">
                                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da peça">
                                    <i data-lucide="more-horizontal"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                                    <h6 class="dropdown-header">Ações da peça</h6>
                                    <a class="dropdown-item" href="entrada.php?peca_id=<?php echo (int) $p['id']; ?>">
                                        <i data-lucide="circle-plus"></i> Dar entrada
                                    </a>
                                    <a class="dropdown-item" href="editar.php?id=<?php echo (int) $p['id']; ?>">
                                        <i data-lucide="pencil"></i> Editar
                                    </a>
                                    <form method="POST" action="excluir.php" id="excluirPeca<?php echo (int) $p['id']; ?>" onsubmit="return confirmarExclusao('excluirPeca<?php echo (int) $p['id']; ?>', 'Excluir a peça <?php echo addslashes($p['nome']); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
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
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="<?php echo $tem_filtro ? 'filter' : 'package-open'; ?>"></i></div>
                                <h4><?php echo $tem_filtro ? 'Nenhuma peça encontrada' : 'Nenhuma peça cadastrada'; ?></h4>
                                <p><?php echo $tem_filtro ? 'Ajuste a busca ou limpe os filtros para ver todas as peças.' : 'Cadastre as peças para controlar o estoque da oficina.'; ?></p>
                                <?php if (!$tem_filtro): ?>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Cadastrar Peça
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
