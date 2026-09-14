<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$stmt = $conn->query("SELECT * FROM servicos ORDER BY nome");
$servicos = $stmt->fetchAll();

$titulo_pagina = "Serviços";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="hammer" class="text-primary me-2"></i>Serviços</h1>
        <span class="page-subtitle">Tipos de serviços oferecidos pela oficina</span>
    </div>
    <a href="cadastrar.php" class="btn btn-primary btn-icon">
        <i data-lucide="plus"></i> Novo Serviço
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="filter-bar">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" id="buscarServico" data-filtro-busca="tabelaServicos" placeholder="Buscar por nome, descrição ou valor...">
            </div>
            <div class="filter-group">
                <label for="filtroStatusServico">Status</label>
                <select class="form-select" id="filtroStatusServico" data-filtro-tabela="tabelaServicos" data-filtro-coluna="Status">
                    <option value="">Todos</option>
                    <option value="Ativo">Ativo</option>
                    <option value="Inativo">Inativo</option>
                </select>
            </div>
            <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="limparFiltros('tabelaServicos')">
                <i data-lucide="filter-x"></i> Limpar
            </button>
        </div>

        <div class="filter-results-info">
            <span>Exibindo <strong data-filtro-total="tabelaServicos"><?php echo count($servicos); ?></strong> de <?php echo count($servicos); ?> serviços</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle responsive-card" id="tabelaServicos">
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Descrição</th>
                        <th>Preço de Mão de Obra</th>
                        <th>Tempo Estimado</th>
                        <th>Status</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($servicos) > 0): foreach ($servicos as $s): ?>
                    <tr>
                        <td data-label="Serviço"><strong><?php echo esc($s['nome']); ?></strong></td>
                        <td data-label="Descrição"><?php echo esc(substr($s['descricao'] ?: '-', 0, 60)); ?></td>
                        <td data-label="Preço de Mão de Obra" class="fw-600 text-success"><?php echo formatar_moeda($s['preco_mao_obra']); ?></td>
                        <td data-label="Tempo Estimado">
                            <?php
                            $horas = floor($s['tempo_estimado'] / 60);
                            $min = $s['tempo_estimado'] % 60;
                            if ($horas > 0) echo $horas . 'h ';
                            if ($min > 0) echo $min . 'min';
                            if ($s['tempo_estimado'] == 0) echo '-';
                            ?>
                        </td>
                        <td data-label="Status">
                            <?php if ($s['ativo']): ?>
                                <span class="badge badge-success">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Ações" class="actions-cell">
                            <div class="dropdown table-dropdown">
                                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do serviço">
                                    <i data-lucide="more-horizontal"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                                    <h6 class="dropdown-header">Ações do serviço</h6>
                                    <a class="dropdown-item" href="editar.php?id=<?php echo $s['id']; ?>">
                                        <i data-lucide="pencil"></i> Editar
                                    </a>
                                    <form method="POST" action="excluir.php" id="excluirServico<?php echo $s['id']; ?>" onsubmit="return confirmarExclusao('excluirServico<?php echo $s['id']; ?>', 'Excluir o serviço <?php echo esc(addslashes($s['nome'])); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
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
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="hammer"></i></div>
                                <h4>Nenhum serviço cadastrado</h4>
                                <p>Cadastre os serviços que a oficina oferece para usar nos orçamentos.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Cadastrar Serviço
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="row-filtro-vazio" data-filtro-vazio="tabelaServicos" style="display:none;">
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="filter"></i></div>
                                <h4>Nenhum serviço encontrado com esses filtros</h4>
                                <p>Ajuste a busca ou limpe os filtros para ver todos os serviços.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>