<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$stmt = $conn->query("SELECT * FROM clientes ORDER BY nome");
$clientes = $stmt->fetchAll();
$cidades = $conn->query("SELECT DISTINCT cidade FROM clientes WHERE cidade IS NOT NULL AND cidade <> '' ORDER BY cidade")->fetchAll();

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
        <div class="filter-bar">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" id="buscarCliente" data-filtro-busca="tabelaClientes" placeholder="Buscar por nome, CPF, telefone ou email...">
            </div>
            <div class="filter-group">
                <label for="filtroCidade">Cidade</label>
                <select class="form-select" id="filtroCidade" data-filtro-tabela="tabelaClientes" data-filtro-coluna="Cidade">
                    <option value="">Todas</option>
                    <?php foreach ($cidades as $c): ?>
                        <option value="<?php echo esc($c['cidade']); ?>"><?php echo esc($c['cidade']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroCadastro">Cadastro</label>
                <select class="form-select" id="filtroCadastro" data-filtro-tabela="tabelaClientes" data-filtro-coluna="Cadastro" data-filtro-data-range="date">
                    <option value="">Todos</option>
                    <option value="hoje">Hoje</option>
                    <option value="ultimos7">Últimos 7 dias</option>
                    <option value="ultimos30">Últimos 30 dias</option>
                </select>
            </div>
            <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="limparFiltros('tabelaClientes')">
                <i data-lucide="filter-x"></i> Limpar
            </button>
        </div>

        <div class="filter-results-info">
            <span>Exibindo <strong data-filtro-total="tabelaClientes"><?php echo count($clientes); ?></strong> de <?php echo count($clientes); ?> clientes</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle responsive-card" id="tabelaClientes">
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
                                    <?php echo strtoupper(substr($cliente['nome'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo limpar($cliente['nome']); ?></strong>
                                    <br>
                                    <small class="text-muted">Cliente #<?php echo $cliente['id']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td data-label="CPF"><?php echo limpar($cliente['cpf']); ?></td>
                        <td data-label="Telefone"><?php echo limpar($cliente['telefone']); ?></td>
                        <td data-label="Email"><?php echo limpar($cliente['email']); ?></td>
                        <td data-label="Cidade"><?php echo limpar($cliente['cidade'] ?: '-'); ?></td>
                        <td data-label="Cadastro" data-filtro-normalizado="<?php echo date('Y-m-d', strtotime($cliente['data_cadastro'])); ?>">
                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($cliente['data_cadastro'])); ?></small>
                        </td>
                        <td data-label="Ações" class="actions-cell">
                            <a href="detalhes.php?id=<?php echo $cliente['id']; ?>" class="btn btn-outline-info btn-sm" title="Cliente, veículos e histórico">
                                <i data-lucide="car"></i>
                            </a>
                            <a href="editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                                <i data-lucide="pencil"></i>
                            </a>
                            <form method="POST" action="excluir.php" id="excluirCliente<?php echo $cliente['id']; ?>" class="d-inline" onsubmit="return confirmarExclusao('excluirCliente<?php echo $cliente['id']; ?>', 'Excluir o cliente <?php echo addslashes($cliente['nome']); ?>? Todos os dados vinculados serão removidos.');">
                                <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr class="row-empty">
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="users"></i></div>
                                <h4>Nenhum cliente cadastrado</h4>
                                <p>Cadastre o primeiro cliente para começar a usar o sistema.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Cadastrar Cliente
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="row-filtro-vazio" data-filtro-vazio="tabelaClientes" style="display:none;">
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="filter"></i></div>
                                <h4>Nenhum cliente encontrado com esses filtros</h4>
                                <p>Ajuste a busca ou limpe os filtros para ver todos os clientes.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>