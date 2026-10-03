<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT o.*, c.nome as cliente_nome, c.cpf, c.telefone, c.email, c.endereco, c.cidade, v.marca, v.modelo, v.placa, v.ano, v.km_atual, u.nome as criado_por_nome, os.numero as os_numero FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id LEFT JOIN veiculos v ON v.id = o.veiculo_id LEFT JOIN usuarios u ON u.id = o.criado_por LEFT JOIN ordens_servico os ON os.id = o.os_id WHERE o.id = :id");
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
$stmt = $conn->prepare("SELECT oi.* FROM orcamento_itens oi WHERE oi.orcamento_id = :id ORDER BY oi.id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$itens = $stmt->fetchAll();

$titulo_pagina = "Orçamento #" . str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$scripts_extra = '<script>window.OrcamentoDetalheApi = ' . json_encode($caminho_base . '/api/orcamentos.php') . ';</script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/orcamento-detalhes.js"></script>';

$badge_status = [
    'aberto' => 'info',
    'aprovado' => 'success',
    'reprovado' => 'danger',
    'cancelado' => 'secondary'
];
?>

<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-file-text text-primary me-2"></i>Orçamento #<?php echo str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT); ?></h1>
        <span class="page-subtitle">Detalhes do orçamento</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="listar.php" class="btn btn-outline-secondary">
            <i class="icon-base ti tabler-arrow-left"></i> Voltar
        </a>
        <a href="imprimir.php?id=<?php echo $orcamento['id']; ?>" target="_blank" class="btn btn-dark">
            <i class="icon-base ti tabler-printer"></i> Imprimir
        </a>
        <?php if ($orcamento['status'] == 'aberto'): ?>
            <a href="editor.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-primary">
                <i class="icon-base ti tabler-pencil"></i> Editar
            </a>
        <?php endif; ?>
        <?php if ($orcamento['status'] == 'aberto' || $orcamento['status'] == 'reprovado'): ?>
            <button type="button" class="btn btn-success" data-orc-status="aprovado" data-orc-id="<?php echo $orcamento['id']; ?>">
                <i class="icon-base ti tabler-check"></i> Aprovar
            </button>
            <button type="button" class="btn btn-danger" data-orc-status="reprovado" data-orc-id="<?php echo $orcamento['id']; ?>">
                <i class="icon-base ti tabler-x"></i> Reprovar
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-4">
    <?php
    $expirado_orc = !empty($orcamento['data_validade']) && strtotime($orcamento['data_validade']) < strtotime(date('Y-m-d')) && $orcamento['status'] == 'aberto';
    ?>
    <div class="col-12 col-xl-8 mb-3 mb-xl-0">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="badge badge-<?php echo $badge_status[$orcamento['status']]; ?>"><?php echo ucfirst($orcamento['status']); ?></span>
                    <?php if ($expirado_orc): ?><span class="badge badge-warning"><i class="icon-base ti tabler-alert-triangle"></i> Expirado</span><?php endif; ?>
                    <div class="text-muted small">
                        <div><i class="icon-base ti tabler-calendar me-1"></i>Data: <?php echo date('d/m/Y', strtotime($orcamento['data_orcamento'])); ?></div>
                        <div><i class="icon-base ti tabler-clock me-1"></i>Validade: <?php echo $orcamento['data_validade'] ? date('d/m/Y', strtotime($orcamento['data_validade'])) : '---'; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">
                    <?php if (!empty($orcamento['os_numero'])): ?>
                        <div class="mb-1">
                            <i class="icon-base ti tabler-clipboard-list me-1"></i>Ordem de serviço:
                            <a href="../ordens_servico/detalhes.php?id=<?php echo (int)$orcamento['os_id']; ?>"><strong>OS #<?php echo str_pad($orcamento['os_numero'], 4, '0', STR_PAD_LEFT); ?></strong></a>
                        </div>
                    <?php endif; ?>
                    <div class="mb-1"><i class="icon-base ti tabler-user me-1"></i>Criado por: <strong><?php echo esc($orcamento['criado_por_nome'] ?: '—'); ?></strong></div>
                    <div><i class="icon-base ti tabler-file-plus me-1"></i>Nº: <strong>#<?php echo str_pad($orcamento['numero'], 4, '0', STR_PAD_LEFT); ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="icon-base ti tabler-user me-2 text-primary"></i>Cliente</div>
            <div class="card-body">
                <h5 class="mb-1"><?php echo esc($orcamento['cliente_nome']); ?></h5>
                <div class="text-muted small">
                    <div><i class="icon-base ti tabler-file-text me-2"></i>CPF: <?php echo esc($orcamento['cpf'] ?: '-'); ?></div>
                    <div><i class="icon-base ti tabler-phone me-2"></i><?php echo esc($orcamento['telefone'] ?: '-'); ?></div>
                    <div><i class="icon-base ti tabler-mail me-2"></i><?php echo esc($orcamento['email'] ?: '-'); ?></div>
                    <div><i class="icon-base ti tabler-map-pin me-2"></i><?php echo esc($orcamento['endereco'] ?: '-') . ' - ' . esc($orcamento['cidade'] ?: ''); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="icon-base ti tabler-car me-2 text-primary"></i>Veículo</div>
            <div class="card-body">
                <?php if ($orcamento['marca']): ?>
                    <h5 class="mb-1"><?php echo esc($orcamento['marca']) . ' ' . esc($orcamento['modelo']); ?></h5>
                    <div class="text-muted small">
                        <div><span class="badge bg-dark"><?php echo esc($orcamento['placa']); ?></span></div>
                        <div><i class="icon-base ti tabler-calendar me-2"></i>Ano: <?php echo $orcamento['ano'] ?: '-'; ?></div>
                        <div><i class="icon-base ti tabler-gauge me-2"></i>KM: <?php echo number_format($orcamento['km_atual'], 0, ',', '.'); ?></div>
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
        <span><i class="icon-base ti tabler-list me-2 text-primary"></i>Itens do Orçamento</span>
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
                        <td><?php echo esc($item['descricao_item']); ?></td>
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
    <div class="card-header"><i class="icon-base ti tabler-note me-2 text-primary"></i>Observações</div>
    <div class="card-body">
        <?php echo nl2br(esc($orcamento['observacoes'])); ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
