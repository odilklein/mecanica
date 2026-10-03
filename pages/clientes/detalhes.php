<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/crud.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT * FROM clientes WHERE id = :id');
$stmt->execute([':id' => $id]);
$cliente = $stmt->fetch();
if (!$cliente) {
    http_response_code(404);
    exit('Cliente não encontrado.');
}
$stmt = $conn->prepare('SELECT * FROM veiculos WHERE cliente_id = :id ORDER BY marca, modelo');
$stmt->execute([':id' => $id]);
$veiculos = $stmt->fetchAll();
$stmt = $conn->prepare("SELECT os.id, os.numero, os.status, os.data_entrada, os.queixa_inicial, v.placa, v.modelo FROM ordens_servico os LEFT JOIN veiculos v ON v.id = os.veiculo_id WHERE os.cliente_id = :id ORDER BY os.data_entrada DESC");
$stmt->execute([':id' => $id]);
$ordens = $stmt->fetchAll();

$titulo_pagina = 'Cliente: ' . $cliente['nome'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?php echo esc($cliente['nome']); ?></h1>
        <span class="page-subtitle">Dados, veículos e histórico de atendimentos</span>
    </div>
    <div class="d-flex gap-2">
        <a href="../veiculos/listar.php" class="btn btn-primary"><i class="icon-base ti tabler-car"></i>Novo veículo</a>
        <a href="listar.php" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i>Voltar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="icon-base ti tabler-user me-2"></i>Contato</div>
            <div class="card-body">
                <p><strong>Telefone</strong><br><?php echo esc($cliente['telefone'] ?: '—'); ?></p>
                <p><strong>E-mail</strong><br><?php echo esc($cliente['email'] ?: '—'); ?></p>
                <p><strong>CPF</strong><br><?php echo esc($cliente['cpf'] ?: '—'); ?></p>
                <p class="mb-0"><strong>Endereço</strong><br><?php echo esc(trim(($cliente['endereco'] ?? '') . ' ' . ($cliente['cidade'] ?? '')) ?: '—'); ?></p>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="icon-base ti tabler-car me-2"></i>Veículos</div>
            <div class="card-body">
                <?php foreach ($veiculos as $veiculo): ?>
                <div class="border-bottom pb-2 mb-2">
                    <strong><?php echo esc(trim(($veiculo['marca'] ?? '') . ' ' . ($veiculo['modelo'] ?? '')) ?: 'Veículo'); ?></strong><br>
                    <small><?php echo esc($veiculo['placa'] ?: 'Placa não informada'); ?><?php echo $veiculo['ano'] ? ' · ' . (int) $veiculo['ano'] : ''; ?></small>
                    <?php if (!empty($veiculo['valor_fipe'])): ?>
                    <br><small class="text-body-secondary">Valor FIPE: <?php echo formatar_moeda($veiculo['valor_fipe']); ?><?php echo !empty($veiculo['fipe_mes_referencia']) ? ' (' . esc($veiculo['fipe_mes_referencia']) . ')' : ''; ?></small>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (!$veiculos): ?><span class="text-body-secondary">Nenhum veículo cadastrado.</span><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="icon-base ti tabler-history me-2"></i>Serviços e ordens de serviço</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle responsive-card mb-0">
                        <thead>
                            <tr><th>OS</th><th>Veículo</th><th>Queixa / serviço</th><th>Data</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ordens as $ordem): ?>
                            <tr>
                                <td data-label="OS"><a href="../ordens_servico/detalhes.php?id=<?php echo (int) $ordem['id']; ?>">#<?php echo str_pad((int) $ordem['numero'], 4, '0', STR_PAD_LEFT); ?></a></td>
                                <td data-label="Veículo"><?php echo esc(trim(($ordem['modelo'] ?? '') . ' ' . ($ordem['placa'] ?? '')) ?: '—'); ?></td>
                                <td data-label="Queixa"><?php echo esc(mb_strimwidth($ordem['queixa_inicial'], 0, 70, '...')); ?></td>
                                <td data-label="Data"><?php echo date('d/m/Y', strtotime($ordem['data_entrada'])); ?></td>
                                <td data-label="Status"><span class="badge badge-status-<?php echo esc($ordem['status']); ?>"><?php echo esc(ucfirst(str_replace('_', ' ', $ordem['status']))); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$ordens): ?>
                            <tr class="row-empty"><td colspan="5"><?php echo crud_empty_state('clipboard-list', 'Nenhum atendimento', 'Este cliente ainda não possui ordens de serviço.'); ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
