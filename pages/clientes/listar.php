<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

// ---------- Filtros (server-side) ----------
$busca = limpar($_GET['busca'] ?? '');
$cidade = limpar($_GET['cidade'] ?? '');
$periodo = $_GET['periodo'] ?? '';

$condicoes = [];
$params = [];
if ($busca !== '') {
    $condicoes[] = '(nome LIKE :busca OR cpf LIKE :busca OR telefone LIKE :busca OR email LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if ($cidade !== '') {
    $condicoes[] = 'cidade = :cidade';
    $params[':cidade'] = $cidade;
}
if ($periodo === 'hoje') {
    $condicoes[] = 'DATE(data_cadastro) = CURDATE()';
} elseif ($periodo === 'ultimos7') {
    $condicoes[] = 'data_cadastro >= (CURDATE() - INTERVAL 6 DAY)';
} elseif ($periodo === 'ultimos30') {
    $condicoes[] = 'data_cadastro >= (CURDATE() - INTERVAL 29 DAY)';
}
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

// ---------- Paginação ----------
$por_pagina = por_pagina_atual();
$pagina = pagina_atual();

$st = $conn->prepare("SELECT COUNT(*) FROM clientes $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$pagina, $offset, $total_paginas] = paginacao_calcular($total, $por_pagina, $pagina);

$st = $conn->prepare("SELECT * FROM clientes $where ORDER BY nome LIMIT :limite OFFSET :offset");
foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$clientes = $st->fetchAll();

$cidades = $conn->query("SELECT DISTINCT cidade FROM clientes WHERE cidade IS NOT NULL AND cidade <> '' ORDER BY cidade")->fetchAll();
$filtros_pag = array_filter(['busca' => $busca, 'cidade' => $cidade, 'periodo' => $periodo, 'por_pagina' => $por_pagina], fn($v) => $v !== '');
$tem_filtro = $busca !== '' || $cidade !== '' || $periodo !== '';

$titulo_pagina = "Clientes";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="users" class="text-primary me-2"></i>Clientes</h1>
        <span class="page-subtitle">Gerencie os clientes da oficina</span>
    </div>
    <a href="cadastrar.php" class="btn btn-primary btn-icon">
        <i data-lucide="plus"></i> Novo Cliente
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form class="filter-bar" method="get" action="listar.php">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" name="busca" value="<?php echo esc($busca); ?>" placeholder="Buscar por nome, CPF, telefone ou email...">
            </div>
            <div class="filter-group">
                <label for="filtroCidade">Cidade</label>
                <select class="form-select" id="filtroCidade" name="cidade" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($cidades as $c): ?>
                        <option value="<?php echo esc($c['cidade']); ?>" <?php echo $cidade === $c['cidade'] ? 'selected' : ''; ?>><?php echo esc($c['cidade']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroPeriodo">Cadastro</label>
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
            <span>Exibindo <strong><?php echo count($clientes); ?></strong> de <strong><?php echo $total; ?></strong> clientes<?php echo $tem_filtro ? ' (filtrados)' : ''; ?></span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle responsive-card">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Cidade</th>
                        <th>Cadastro</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clientes) > 0): foreach ($clientes as $cliente): ?>
                    <tr>
                        <td data-label="Cliente">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-2">
                                    <?php echo esc(strtoupper(substr($cliente['nome'], 0, 1))); ?>
                                </div>
                                <div>
                                    <strong><?php echo esc($cliente['nome']); ?></strong>
                                    <br>
                                    <small class="text-muted">Cliente #<?php echo (int) $cliente['id']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td data-label="CPF"><?php echo esc($cliente['cpf']); ?></td>
                        <td data-label="Telefone"><?php echo esc($cliente['telefone']); ?></td>
                        <td data-label="Email"><?php echo esc($cliente['email']); ?></td>
                        <td data-label="Cidade"><?php echo esc($cliente['cidade'] ?: '-'); ?></td>
                        <td data-label="Cadastro">
                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($cliente['data_cadastro'])); ?></small>
                        </td>
                        <td data-label="Ações" class="actions-cell">
                            <div class="dropdown table-dropdown">
                                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do cliente">
                                    <i data-lucide="more-horizontal"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                                    <h6 class="dropdown-header">Ações do cliente</h6>
                                    <a class="dropdown-item" href="detalhes.php?id=<?php echo (int) $cliente['id']; ?>">
                                        <i data-lucide="car"></i> Cliente, veículos e histórico
                                    </a>
                                    <a class="dropdown-item" href="editar.php?id=<?php echo (int) $cliente['id']; ?>">
                                        <i data-lucide="pencil"></i> Editar
                                    </a>
                                    <form method="POST" action="excluir.php" id="excluirCliente<?php echo (int) $cliente['id']; ?>" onsubmit="return confirmarExclusao('excluirCliente<?php echo (int) $cliente['id']; ?>', 'Excluir o cliente <?php echo addslashes($cliente['nome']); ?>? Todos os dados vinculados serão removidos.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) $cliente['id']; ?>">
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
                                <div class="empty-state-icon"><i data-lucide="<?php echo $tem_filtro ? 'filter' : 'users'; ?>"></i></div>
                                <h4><?php echo $tem_filtro ? 'Nenhum cliente encontrado' : 'Nenhum cliente cadastrado'; ?></h4>
                                <p><?php echo $tem_filtro ? 'Ajuste a busca ou limpe os filtros para ver todos os clientes.' : 'Cadastre o primeiro cliente para começar a usar o sistema.'; ?></p>
                                <?php if (!$tem_filtro): ?>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Cadastrar Cliente
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
