<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria']);

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

<div class="row mb-3 g-2">
    <div class="col-md-4">
        <div class="search-wrapper">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" class="form-control" id="buscarPeca" placeholder="Buscar peça...">
        </div>
    </div>
    <div class="col-md-3">
        <select class="form-select" id="filtroEstoque">
            <option value="todos">Todos os status</option>
            <option value="baixo">Somente estoque baixo</option>
            <option value="zerado">Zerados</option>
            <option value="normal">Em estoque</option>
        </select>
    </div>
</div>

<div class="card">
    <div class="card-body">
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
                    <?php
                    $stmt = $conn->query("SELECT * FROM pecas ORDER BY nome");
                    $pecas = $stmt->fetchAll();

                    if (count($pecas) > 0):
                        foreach ($pecas as $p):
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
                        <td><span class="badge bg-secondary"><?php echo limpar($p['codigo']); ?></span></td>
                        <td>
                            <strong><?php echo limpar($p['nome']); ?></strong>
                            <?php if ($p['descricao']): ?>
                                <br><small class="text-muted"><?php echo limpar(substr($p['descricao'], 0, 50)) . (strlen($p['descricao']) > 50 ? '...' : ''); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo limpar($p['categoria'] ?: '-'); ?></td>
                        <td>
                            <strong class="<?php echo ($p['quantidade'] <= $p['estoque_minimo']) ? 'text-warning' : ''; ?>">
                                <?php echo $p['quantidade']; ?>
                            </strong>
                        </td>
                        <td><?php echo $p['estoque_minimo']; ?></td>
                        <td><?php echo formatar_moeda($p['preco_compra']); ?></td>
                        <td class="fw-600"><?php echo formatar_moeda($p['preco_venda']); ?></td>
                        <td><span class="badge badge-<?php echo $status_badge; ?>"><?php echo $status; ?></span></td>
                        <td class="actions-cell">
                            <a href="editar.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                                <i data-lucide="pencil"></i>
                            </a>
                            <a href="entrada.php?peca_id=<?php echo $p['id']; ?>" class="btn btn-outline-success btn-sm" title="Dar entrada">
                                <i data-lucide="circle-plus"></i>
                            </a>
                            <form method="POST" action="excluir.php" id="excluirPeca<?php echo $p['id']; ?>" class="d-inline" onsubmit="return confirmarExclusao('excluirPeca<?php echo $p['id']; ?>', 'Excluir a peça <?php echo addslashes($p['nome']); ?>?');">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i data-lucide="package-open"></i>
                                <h4>Nenhuma peça cadastrada</h4>
                                <p>Cadastre as peças para controlar o estoque da oficina.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Cadastrar Peça
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
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

<script>
    document.getElementById('buscarPeca').addEventListener('keyup', function() {
        const termo = this.value.toLowerCase();
        const linhas = document.querySelectorAll('#tabelaPecas tbody tr');
        linhas.forEach(linha => {
            const texto = linha.textContent.toLowerCase();
            linha.style.display = texto.indexOf(termo) > -1 ? '' : 'none';
        });
    });

    document.getElementById('filtroEstoque').addEventListener('change', function() {
        const filtro = this.value;
        const linhas = document.querySelectorAll('#tabelaPecas tbody tr');
        linhas.forEach(linha => {
            const badge = linha.querySelector('.badge');
            if (!badge) return;
            const status = badge.textContent.trim();
            let mostrar = true;
            if (filtro === 'baixo' && status !== 'Estoque Baixo' && status !== 'Zerado') mostrar = false;
            if (filtro === 'zerado' && status !== 'Zerado') mostrar = false;
            if (filtro === 'normal' && (status === 'Zerado' || status === 'Estoque Baixo')) mostrar = false;
            linha.style.display = mostrar ? '' : 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
