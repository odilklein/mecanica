<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
$roles = ['admin' => 'Administrador', 'secretaria' => 'Secretaria', 'mecanico_chefe' => 'Mecânico chefe', 'mecanico' => 'Mecânico'];
$funcionarios = $conn->query("SELECT id, nome, email, tipo, cargo, telefone, salario, data_admissao, ativo FROM usuarios ORDER BY ativo DESC, nome")->fetchAll();
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
        <div class="filter-bar">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" id="buscarFuncionario" data-filtro-busca="tabelaFuncionarios" placeholder="Buscar por nome, email ou cargo...">
            </div>
            <div class="filter-group">
                <label for="filtroPerfil">Perfil</label>
                <select class="form-select" id="filtroPerfil" data-filtro-tabela="tabelaFuncionarios" data-filtro-coluna="Cargo / perfil">
                    <option value="">Todos</option>
                    <?php foreach ($roles as $tipo => $label): ?>
                        <option value="<?php echo esc($label); ?>"><?php echo esc($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroStatusFunc">Status</label>
                <select class="form-select" id="filtroStatusFunc" data-filtro-tabela="tabelaFuncionarios" data-filtro-coluna="Status">
                    <option value="">Todos</option>
                    <option value="Ativo">Ativo</option>
                    <option value="Inativo">Inativo</option>
                </select>
            </div>
            <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="limparFiltros('tabelaFuncionarios')"><i data-lucide="filter-x"></i> Limpar</button>
        </div>
        <div class="filter-results-info">
            <span>Exibindo <strong data-filtro-total="tabelaFuncionarios"><?php echo count($funcionarios); ?></strong> de <?php echo count($funcionarios); ?> funcionários</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 responsive-card" id="tabelaFuncionarios">
                <thead>
                    <tr><th>Funcionário</th><th>Cargo / perfil</th><th>Telefone</th><th>Salário</th><th>Na empresa desde</th><th>Status</th><th>Ações</th></tr>
                </thead>
                <tbody>
                <?php if (count($funcionarios) > 0): foreach ($funcionarios as $f): ?>
                <tr>
                    <td data-label="Funcionário"><strong><?php echo esc($f['nome']); ?></strong><br><small class="text-muted"><?php echo esc($f['email']); ?></small></td>
                    <td data-label="Cargo / perfil" data-filtro-normalizado="<?php echo esc($roles[$f['tipo']] ?? $f['tipo']); ?>"><?php echo esc($f['cargo'] ?: ($roles[$f['tipo']] ?? $f['tipo'])); ?><br><small class="text-muted"><?php echo esc($roles[$f['tipo']] ?? $f['tipo']); ?></small></td>
                    <td data-label="Telefone"><?php echo esc($f['telefone'] ?: '-'); ?></td>
                    <td data-label="Salário"><?php echo $f['salario'] !== null ? formatar_moeda($f['salario']) : '-'; ?></td>
                    <td data-label="Na empresa desde"><?php echo $f['data_admissao'] ? date('d/m/Y', strtotime($f['data_admissao'])) : '-'; ?></td>
                    <td data-label="Status" data-filtro-normalizado="<?php echo $f['ativo'] ? 'Ativo' : 'Inativo'; ?>"><span class="badge <?php echo $f['ativo'] ? 'badge-success' : 'badge-secondary'; ?>"><?php echo $f['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                    <td data-label="Ações" class="actions-cell"><a class="btn btn-outline-primary btn-sm" href="editar.php?id=<?php echo (int) $f['id']; ?>" title="Editar"><i data-lucide="pencil"></i></a></td>
                </tr>
                <?php endforeach; else: ?>
                <tr class="row-empty"><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="users-round"></i></div><h4>Nenhum funcionário cadastrado</h4><p>Cadastre o primeiro funcionário para começar.</p><a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="user-plus"></i> Novo funcionário</a></div></td></tr>
                <?php endif; ?>
                <tr class="row-filtro-vazio" data-filtro-vazio="tabelaFuncionarios" style="display:none;">
                    <td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="filter"></i></div><h4>Nenhum funcionário encontrado</h4><p>Ajuste a busca ou limpe os filtros.</p></div></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>