<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria']);

$titulo_pagina = "Entrada de Estoque";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$peca_id = isset($_GET['peca_id']) ? (int)$_GET['peca_id'] : 0;
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="log-in" class="text-success me-2"></i>Entrada de Estoque</h1>
        <span class="page-subtitle">Registre entrada ou saída de peças</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar_movimentacao.php" onsubmit="return validarMovimentacao()">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="peca_id" class="form-label">Peça *</label>
                            <select class="form-select" id="peca_id" name="peca_id" required onchange="atualizarQuantidadeAtual()">
                                <option value="">-- Selecione a peça --</option>
                                <?php
                                $stmt = $conn->query("SELECT * FROM pecas ORDER BY nome");
                                foreach ($stmt->fetchAll() as $p):
                                    $selected = ($p['id'] == $peca_id) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $p['id']; ?>" data-qtd="<?php echo $p['quantidade']; ?>" <?php echo $selected; ?>>
                                        <?php echo limpar($p['nome']) . ' (' . $p['quantidade'] . ' em estoque)';
                                    ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="tipo" class="form-label">Tipo *</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="entrada">Entrada</option>
                                <option value="saida">Saída</option>
                                <option value="ajuste">Ajuste</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="quantidade" class="form-label">Quantidade *</label>
                            <input type="number" class="form-control" id="quantidade" name="quantidade" min="1" value="1" required>
                        </div>
                        <div class="col-md-8">
                            <label for="motivo" class="form-label">Motivo</label>
                            <input type="text" class="form-control" id="motivo" name="motivo" placeholder="Ex: Compra de fornecedor, uso em serviço, dano, etc.">
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0" id="infoEstoque">
                        <i data-lucide="info" class="me-2"></i>
                        Quantidade atual na peça selecionada: <strong id="qtdAtual">-</strong>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success btn-icon">
                            <i data-lucide="arrow-left-right"></i> Registrar Movimentação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<hr>

<div class="card">
    <div class="card-header">
        <i data-lucide="history" class="me-2 text-primary"></i>Últimas Movimentações
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Peça</th>
                        <th>Tipo</th>
                        <th>Quantidade</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->query("SELECT m.*, p.nome as peca_nome FROM movimentacoes_estoque m JOIN pecas p ON p.id = m.peca_id ORDER BY m.data_movimentacao DESC LIMIT 20");
                    $movs = $stmt->fetchAll();
                    if (count($movs) > 0):
                        foreach ($movs as $mov):
                            $badge = $mov['tipo'] == 'entrada' ? 'success' : ($mov['tipo'] == 'saida' ? 'danger' : 'warning');
                    ?>
                    <tr>
                        <td><small><?php echo date('d/m/Y H:i', strtotime($mov['data_movimentacao'])); ?></small></td>
                        <td><?php echo limpar($mov['peca_nome']); ?></td>
                        <td><span class="badge badge-<?php echo $badge; ?>"><?php echo ucfirst($mov['tipo']); ?></span></td>
                        <td class="<?php echo $mov['tipo'] == 'entrada' ? 'text-success' : 'text-danger'; ?> fw-600">
                            <?php echo $mov['tipo'] == 'entrada' ? '+' : '-'; ?><?php echo $mov['quantidade']; ?>
                        </td>
                        <td><?php echo limpar($mov['motivo'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma movimentação registrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function atualizarQuantidadeAtual() {
    const select = document.getElementById('peca_id');
    const opt = select.options[select.selectedIndex];
    const qtd = opt ? opt.dataset.qtd : null;
    document.getElementById('qtdAtual').textContent = qtd !== null ? qtd + ' unidade(s)' : '-';
}
atualizarQuantidadeAtual();

function validarMovimentacao() {
    const select = document.getElementById('peca_id');
    const opt = select.options[select.selectedIndex];
    const qtdAtual = parseInt(opt.dataset.qtd || 0);
    const tipo = document.getElementById('tipo').value;
    const qtd = parseInt(document.getElementById('quantidade').value);

    if (tipo === 'saida' && qtd > qtdAtual) {
        alert('Não é possível dar saída de ' + qtd + ' unidades pois só existem ' + qtdAtual + ' em estoque.');
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
