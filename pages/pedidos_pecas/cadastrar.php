<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
$os_id = (int) ($_GET['os_id'] ?? 0);
$filtro_os = $_SESSION['usuario_tipo'] === 'mecanico' ? ' JOIN os_responsaveis r ON r.os_id = os.id AND r.usuario_id = ' . (int) $_SESSION['usuario_id'] . ' AND r.concluido_em IS NULL ' : '';
$ordens = $conn->query("SELECT os.id, os.numero, c.nome AS cliente, v.modelo, v.placa FROM ordens_servico os $filtro_os JOIN clientes c ON c.id = os.cliente_id LEFT JOIN veiculos v ON v.id = os.veiculo_id WHERE os.status NOT IN ('entregue', 'cancelada') ORDER BY os.data_entrada DESC")->fetchAll();
$pecas = $_SESSION['usuario_tipo'] === 'secretaria' ? $conn->query('SELECT id, nome, codigo FROM pecas ORDER BY nome')->fetchAll() : [];
$titulo_pagina = 'Novo pedido de peça';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header">
    <div>
        <h1><i data-lucide="package-plus" class="text-primary me-2"></i><?php echo $_SESSION['usuario_tipo'] === 'mecanico' ? 'Solicitar peça' : 'Novo pedido de peça'; ?></h1>
        <span class="page-subtitle"><?php echo $_SESSION['usuario_tipo'] === 'mecanico' ? 'A solicitação será validada antes de chegar à secretaria' : 'Registre uma compra aprovada'; ?></span>
    </div>
</div>
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=cadastrar">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-with-icon"><i data-lucide="clipboard-list"></i> Ordem de serviço *</label>
                            <select class="form-select" name="os_id" required>
                                <option value="">Selecione a OS</option>
                                <?php foreach ($ordens as $ordem): ?>
                                <option value="<?php echo (int) $ordem['id']; ?>" <?php echo $os_id === (int) $ordem['id'] ? 'selected' : ''; ?>>OS #<?php echo str_pad((int) $ordem['numero'], 4, '0', STR_PAD_LEFT); ?><?php if ($_SESSION['usuario_tipo'] !== 'mecanico'): ?> - <?php echo esc($ordem['cliente']); ?><?php endif; ?> - <?php echo esc($ordem['placa'] ?: $ordem['modelo'] ?: 'veículo'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($_SESSION['usuario_tipo'] !== 'mecanico'): ?>
                        <div class="col-md-6">
                            <label class="form-label-with-icon"><i data-lucide="store"></i> Fornecedor</label>
                            <input class="form-control" name="fornecedor" maxlength="120" placeholder="Nome do fornecedor">
                        </div>
                        <?php endif; ?>
                        <?php if ($_SESSION['usuario_tipo'] !== 'mecanico' && $pecas): ?>
                        <div class="col-md-6">
                            <label class="form-label-with-icon"><i data-lucide="package"></i> Peça cadastrada</label>
                            <select class="form-select" name="peca_id">
                                <option value="">Outra peça (digitar descrição)</option>
                                <?php foreach ($pecas as $peca): ?>
                                <option value="<?php echo (int) $peca['id']; ?>"><?php echo esc($peca['nome']); ?><?php echo $peca['codigo'] ? ' (' . esc($peca['codigo']) . ')' : ''; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-<?php echo $_SESSION['usuario_tipo'] === 'mecanico' ? '8' : '6'; ?>">
                            <label class="form-label-with-icon"><i data-lucide="text-cursor-input"></i> Descrição da peça *</label>
                            <input class="form-control" name="descricao" maxlength="200" required placeholder="Ex.: Filtro de óleo, Pastilha de freio...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-with-icon"><i data-lucide="hash"></i> Quantidade *</label>
                            <input class="form-control" type="number" name="quantidade" min="1" value="1" required>
                        </div>
                        <?php if ($_SESSION['usuario_tipo'] !== 'mecanico'): ?>
                        <div class="col-md-3">
                            <label class="form-label-with-icon"><i data-lucide="calendar-clock"></i> Previsão entrega</label>
                            <input class="form-control" type="date" name="previsao_entrega">
                        </div>
                        <div class="col-12">
                            <label class="form-label-with-icon"><i data-lucide="notebook-pen"></i> Observações</label>
                            <textarea class="form-control" name="observacoes" rows="2" maxlength="1000" placeholder="Detalhes adicionais sobre o pedido..."></textarea>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary btn-icon"><i data-lucide="arrow-left"></i> Voltar</a>
                        <button type="submit" class="btn btn-primary btn-icon"><i data-lucide="save"></i> <?php echo $_SESSION['usuario_tipo'] === 'mecanico' ? 'Enviar solicitação' : 'Criar pedido'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
