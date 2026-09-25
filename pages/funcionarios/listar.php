<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$roles = ['admin' => 'Administrador', 'secretaria' => 'Secretaria', 'mecanico_chefe' => 'Mecânico chefe', 'mecanico' => 'Mecânico'];

// ---------- Filtros (server-side) ----------
$busca = limpar($_GET['busca'] ?? '');
$perfil = $_GET['perfil'] ?? '';
$status_func = $_GET['status'] ?? '';

$condicoes = [];
$params = [];
if ($busca !== '') {
    $condicoes[] = '(nome LIKE :busca OR email LIKE :busca OR cargo LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if (array_key_exists($perfil, $roles)) {
    $condicoes[] = 'tipo = :perfil';
    $params[':perfil'] = $perfil;
}
if ($status_func !== '' && in_array($status_func, ['0', '1'], true)) {
    $condicoes[] = 'ativo = :ativo';
    $params[':ativo'] = (int) $status_func;
}
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

// ---------- Paginação ----------
$por_pagina = por_pagina_atual();
$pagina = pagina_atual();

$st = $conn->prepare("SELECT COUNT(*) FROM usuarios $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$pagina, $offset, $total_paginas] = paginacao_calcular($total, $por_pagina, $pagina);

$st = $conn->prepare("SELECT id, nome, email, tipo, cargo, telefone, salario, data_admissao, ativo FROM usuarios $where ORDER BY ativo DESC, nome LIMIT :limite OFFSET :offset");
foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$funcionarios = $st->fetchAll();

$filtros_pag = array_filter(['busca' => $busca, 'perfil' => $perfil, 'status' => $status_func, 'por_pagina' => $por_pagina], fn($v) => $v !== '');
$tem_filtro = $busca !== '' || $perfil !== '' || $status_func !== '';

$titulo_pagina = 'Funcionários';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header">
    <div>
        <h1><i data-lucide="users-round" class="text-primary me-2"></i>Funcionários</h1>
        <span class="page-subtitle">Gerencie pessoas, cargos e permissões</span>
    </div>
    <a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="user-plus"></i>Novo funcionário</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <form class="filter-bar mb-0 rounded-bottom-0" method="get" action="listar.php">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" name="busca" value="<?php echo esc($busca); ?>" placeholder="Buscar por nome, email ou cargo...">
            </div>
            <div class="filter-group">
                <label for="filtroPerfil">Perfil</label>
                <select class="form-select" id="filtroPerfil" name="perfil" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($roles as $tipo => $label): ?>
                        <option value="<?php echo esc($tipo); ?>" <?php echo $perfil === $tipo ? 'selected' : ''; ?>><?php echo esc($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroStatusFunc">Status</label>
                <select class="form-select" id="filtroStatusFunc" name="status" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="1" <?php echo $status_func === '1' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="0" <?php echo $status_func === '0' ? 'selected' : ''; ?>>Inativo</option>
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
            <span>Exibindo <strong><?php echo count($funcionarios); ?></strong> de <strong><?php echo $total; ?></strong> funcionários<?php echo $tem_filtro ? ' (filtrados)' : ''; ?></span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 responsive-card">
                <thead>
                    <tr><th>Funcionário</th><th>Cargo / perfil</th><th>Telefone</th><th>Salário</th><th>Na empresa desde</th><th>Status</th><th>Ações</th></tr>
                </thead>
                <tbody>
                <?php if (count($funcionarios) > 0): foreach ($funcionarios as $f): ?>
                <tr>
                    <td data-label="Funcionário"><strong><?php echo esc($f['nome']); ?></strong><br><small class="text-muted"><?php echo esc($f['email']); ?></small></td>
                    <td data-label="Cargo / perfil"><?php echo esc($f['cargo'] ?: ($roles[$f['tipo']] ?? $f['tipo'])); ?><br><small class="text-muted"><?php echo esc($roles[$f['tipo']] ?? $f['tipo']); ?></small></td>
                    <td data-label="Telefone"><?php echo esc($f['telefone'] ?: '-'); ?></td>
                    <td data-label="Salário"><?php echo $f['salario'] !== null ? formatar_moeda($f['salario']) : '-'; ?></td>
                    <td data-label="Na empresa desde"><?php echo $f['data_admissao'] ? date('d/m/Y', strtotime($f['data_admissao'])) : '-'; ?></td>
                    <td data-label="Status"><span class="badge <?php echo $f['ativo'] ? 'badge-success' : 'badge-secondary'; ?>"><?php echo $f['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                    <td data-label="Ações" class="actions-cell">
                    <div class="dropdown table-dropdown">
                        <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do funcionário"><i data-lucide="more-horizontal"></i></button>
                        <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                            <h6 class="dropdown-header">Ações do funcionário</h6>
                            <a class="dropdown-item" href="editar.php?id=<?php echo (int) $f['id']; ?>"><i data-lucide="pencil"></i> Editar</a>
                        </div>
                    </div>
                </td>
                </tr>
                <?php endforeach; else: ?>
                <tr class="row-empty"><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="<?php echo $tem_filtro ? 'filter' : 'users-round'; ?>"></i></div><h4><?php echo $tem_filtro ? 'Nenhum funcionário encontrado' : 'Nenhum funcionário cadastrado'; ?></h4><p><?php echo $tem_filtro ? 'Ajuste a busca ou limpe os filtros.' : 'Cadastre o primeiro funcionário para começar.'; ?></p><?php if (!$tem_filtro): ?><a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="user-plus"></i> Novo funcionário</a><?php endif; ?></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php echo paginacao_links($pagina, $total_paginas, $filtros_pag); ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
