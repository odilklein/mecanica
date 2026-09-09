<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT o.*, c.nome as cliente_nome, c.cpf, c.telefone, c.email, c.endereco, c.cidade, v.marca, v.modelo, v.placa, v.ano, v.km_atual, u.nome as criado_por_nome FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id LEFT JOIN veiculos v ON v.id = o.veiculo_id LEFT JOIN usuarios u ON u.id = o.criado_por WHERE o.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$orcamento = $stmt->fetch();

if (!$orcamento) {
    $_SESSION['mensagem'] = "Orçamento não encontrado.";
    $_SESSION['mensagem_tipo'] = "danger";
    header("Location: listar.php");
    exit();
}

// Buscar itens
$stmt = $conn->prepare("SELECT oi.*, p.nome as peca_nome, s.nome as servico_nome FROM orcamento_itens oi LEFT JOIN pecas p ON p.id = oi.peca_id LEFT JOIN servicos s ON s.id = oi.servico_id WHERE oi.orcamento_id = :id ORDER BY oi.id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$itens = $stmt->fetchAll();

$titulo_pagina = "Orçamento #" . str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$badge_status = [
    'aberto' => 'info',
    'aprovado' => 'success',
    'reprovado' => 'danger',
    'cancelado' => 'secondary'
];
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-file-invoice text-primary me-2"></i>Orçamento #<?php echo str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT); ?></h1>
        <span class="page-subtitle">Detalhes do orçamento</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="listar.php" class="btn btn-outline-secondary btn-icon">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
        <a href="imprimir.php?id=<?php echo $orcamento['id']; ?>" target="_blank" class="btn btn-dark btn-icon">
            <i class="fas fa-print"></i> Imprimir
        </a>
        <?php if ($orcamento['status'] == 'aberto'): ?>
            <a href="editar.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-primary btn-icon">
                <i class="fas fa-edit"></i> Editar
            </a>
        <?php endif; ?>
        <?php if ($orcamento['status'] == 'aberto' || $orcamento['status'] == 'reprovado'): ?>
            <form method="POST" action="processar.php?acao=mudar_status" class="d-inline">
                <input type="hidden" name="id" value="<?php echo $orcamento['id']; ?>">
                <input type="hidden" name="status" value="aprovado">
                <button type="submit" class="btn btn-success btn-icon" onclick="return confirm('Aprovar este orçamento?');">
                    <i class="fas fa-check"></i> Aprovar
                </button>
            </form>
            <form method="POST" action="processar.php?acao=mudar_status" class="d-inline">
                <input type="hidden" name="id" value="<?php echo $orcamento['id']; ?>">
                <input type="hidden" name="status" value="reprovado">
                <button type="submit" class="btn btn-danger btn-icon" onclick="return confirm('Reprovar este orçamento?');">
                    <i class="fas fa-times"></i> Reprovar
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-user me-2 text-primary"></i>Cliente</div>
            <div class="card-body">
                <h5 class="mb-1"><?php echo limpar($orcamento['cliente_nome']); ?></h5>
                <div class="text-muted small">
                    <div><i class="fas fa-file-alt me-2"></i>CPF: <?php echo limpar($orcamento['cpf'] ?: '-'); ?></div>
                    <div><i class="fas fa-phone me-2"></i><?php echo limpar($orcamento['telefone'] ?: '-'); ?></div>
                    <div><i class="fas fa-envelope me-2"></i><?php echo limpar($orcamento['email'] ?: '-'); ?></div>
                    <div><i class="fas fa-map-marker-alt me-2"></i><?php echo limpar($orcamento['endereco'] ?: '-') . ' - ' . limpar($orcamento['cidade'] ?: ''); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-car me-2 text-primary"></i>Veículo</div>
            <div class="card-body">
                <?php if ($orcamento['marca']): ?>
                    <h5 class="mb-1"><?php echo limpar($orcamento['marca']) . ' ' . limpar($orcamento['modelo']); ?></h5>
                    <div class="text-muted small">
                        <div><span class="badge bg-dark"><?php echo limpar($orcamento['placa']); ?></span></div>
                        <div><i class="fas fa-calendar me-2"></i>Ano: <?php echo $orcamento['ano'] ?: '-'; ?></div>
                        <div><i class="fas fa-tachometer-alt me-2"></i>KM: <?php echo number_format($orcamento['km_atual'], 0, ',', '.'); ?></div>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Nenhum veículo informado</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2 text-primary"></i>Itens do Orçamento</span>
        <span class="badge badge-<?php echo $badge_status[$orcamento['status']]; ?>"><?php echo ucfirst($orcamento['status']); ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th class="text-center">Qtd</th>
                        <th class="text-end">Preço Unitário</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $item): ?>
                        <tr>
                            <td>
                                <?php if ($item['tipo'] == 'peca'): ?>
                                    <span class="badge badge-info">Peça</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Mão de obra</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo limpar($item['tipo'] == 'peca' ? $item['peca_nome'] : $item['servico_nome']); ?></td>
                            <td class="text-center"><?php echo $item['quantidade']; ?></td>
                            <td class="text-end"><?php echo formatar_moeda($item['preco_unitario']); ?></td>
                            <td class="text-end fw-600"><?php echo formatar_moeda($item['subtotal']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row justify-content-end">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Peças:</span>
                    <strong><?php echo formatar_moeda($orcamento['total_pecas']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Mão de Obra:</span>
                    <strong><?php echo formatar_moeda($orcamento['total_mao_obra']); ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="h5 mb-0">Total Geral:</span>
                    <span class="h3 mb-0 text-success"><?php echo formatar_moeda($orcamento['total_geral']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($orcamento['observacoes']): ?>
<div class="card mt-3">
    <div class="card-header"><i class="fas fa-sticky-note me-2 text-primary"></i>Observações</div>
    <div class="card-body">
        <?php echo nl2br(limpar($orcamento['observacoes'])); ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
