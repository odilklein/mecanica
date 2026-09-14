<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria']);

$stmt = $conn->query("SELECT * FROM pecas ORDER BY nome");
$pecas = $stmt->fetchAll();
$categorias = $conn->query("SELECT DISTINCT categoria FROM pecas WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria")->fetchAll();

$titulo_pagina = "Estoque";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

// Contar peças com estoque baixo
$stmt = $conn->query("SELECT COUNT(*) as total FROM pecas WHERE quantidade <= estoque_minimo");
$estoque_baixo = $stmt->fetch()['total'];
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="boxes" class="text-primary me-2"></i>Estoque de Peças</h1>
        <span class="page-subtitle">Controle o estoque e as peças da oficina</span>
    </div>
    <div class="d-flex gap-2">
        <?php if ($estoque_baixo > 0): ?>
            <a href="listar.php#estoque-baixo" class="btn btn-outline-warning btn-icon">
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
        <div class="filter-bar">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" id="buscarPeca" data-filtro-busca="tabelaPecas" placeholder="Buscar por nome, código ou categoria...">
            </div>
            <div class="filter-group">
                <label for="filtroCategoria">Categoria</label>
                <select class="form-select" id="filtroCategoria" data-filtro-tabela="tabelaPecas" data-filtro-coluna="Categoria">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo esc($cat['categoria']); ?>"><?php echo esc($cat['categoria']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroEstoque">Status</label>
                <select class="form-select" id="filtroEstoque" data-filtro-tabela="tabelaPecas" data-filtro-coluna="Status">
                    <option value="">Todos</option>
                    <option value="Em estoque">Em estoque</option>
                    <option value="Estoque Baixo">Estoque baixo</option>
                    <option value="Zerado">Zerado</option>
                </select>
            </div>
            <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="limparFiltros('tabelaPecas')">
                <i data-lucide="filter-x"></i> Limpar
            </button>
        </div>

        <div class="filter-results-info">
            <span>Exibindo <strong data-filtro-total="tabelaPecas"><?php echo count($pecas); ?></strong> de <?php echo count($pecas); ?> peças</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tabelaPecas">
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
                        $pct = $p['estoque_minimo'] > 0 ? ($p['quantidade'] / $p['estoque_minimo']) * 100 : 100;
                        $status = '';
                        $status_badge = 'success';
                        if ($p['quantidade'] <= 0) {
                            $status = 'Zerado';
                            $status_badge = 'danger';
                        } elseif ($p['quantidade'] <= $p['estoque_minimo']) {
                            $status = 'Estoque Baixo';
                            $status_badge = 'warning';
                        } else {
                            $status = 'Em estoque';
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
                                <?php echo $p['quantidade']; ?>
                            </strong>
                        </td>
                        <td data-label="Estoque Mín."><?php echo $p['estoque_minimo']; ?></td>
                        <td data-label="Preço Compra"><?php echo formatar_moeda($p['preco_compra']); ?></td>
                        <td data-label="Preço Venda" class="fw-600"><?php echo formatar_moeda($p['preco_venda']); ?></td>
                        <td data-label="Status"><span class="badge badge-<?php echo $status_badge; ?>"><?php echo $status; ?></span></td>
                        <td data-label="Ações" class="actions-cell">
                            <div class="dropdown table-dropdown">
                                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da peça">
                                    <i data-lucide="more-horizontal"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                                    <h6 class="dropdown-header">Ações da peça</h6>
                                    <a class="dropdown-item" href="entrada.php?peca_id=<?php echo $p['id']; ?>">
                                        <i data-lucide="circle-plus"></i> Dar entrada
                                    </a>
                                    <a class="dropdown-item" href="editar.php?id=<?php echo $p['id']; ?>">
                                        <i data-lucide="pencil"></i> Editar
                                    </a>
                                    <form method="POST" action="excluir.php" id="excluirPeca<?php echo $p['id']; ?>" onsubmit="return confirmarExclusao('excluirPeca<?php echo $p['id']; ?>', 'Excluir a peça <?php echo addslashes($p['nome']); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
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
                                <div class="empty-state-icon"><i data-lucide="package-open"></i></div>
                                <h4>Nenhuma peça cadastrada</h4>
                                <p>Cadastre as peças para controlar o estoque da oficina.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Cadastrar Peça
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="row-filtro-vazio" data-filtro-vazio="tabelaPecas" style="display:none;">
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="filter"></i></div>
                                <h4>Nenhuma peça encontrada com esses filtros</h4>
                                <p>Ajuste a busca ou limpe os filtros para ver todas as peças.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.estoque-alert-row {
    background-color: rgba(255, 193, 7, 0.06);
}
.estoque-alert-row:hover {
    background-color: rgba(255, 193, 7, 0.12) !important;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>