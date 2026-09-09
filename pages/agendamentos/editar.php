<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone, v.marca, v.modelo, v.placa, s.nome as servico_nome FROM agendamentos a LEFT JOIN clientes c ON c.id = a.cliente_id LEFT JOIN veiculos v ON v.id = a.veiculo_id LEFT JOIN servicos s ON s.id = a.servico_id WHERE a.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$agendamento = $stmt->fetch();

if (!$agendamento) {
    $_SESSION['mensagem'] = "Agendamento não encontrado.";
    $_SESSION['mensagem_tipo'] = "danger";
    header("Location: listar.php");
    exit();
}

$titulo_pagina = "Editar Agendamento";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-edit text-primary me-2"></i>Editar Agendamento</h1>
        <span class="page-subtitle">Agendamento de <?php echo limpar($agendamento['cliente_nome']); ?></span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=editar" onsubmit="return validarAgendamento()">
                    <input type="hidden" name="id" value="<?php echo $agendamento['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cliente_id" class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required onchange="carregarVeiculos(this.value, 'veiculo_id', '<?php echo $agendamento['veiculo_id']; ?>')">
                                <option value="">-- Selecione o cliente --</option>
                                <?php
                                $stmt = $conn->query("SELECT id, nome FROM clientes ORDER BY nome");
                                foreach ($stmt->fetchAll() as $c):
                                    $selected = ($c['id'] == $agendamento['cliente_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $selected; ?>><?php echo limpar($c['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="veiculo_id" class="form-label">Veículo</label>
                            <select class="form-select" id="veiculo_id" name="veiculo_id">
                                <option value="">-- Selecione --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="servico_id" class="form-label">Serviço *</label>
                            <select class="form-select" id="servico_id" name="servico_id" required>
                                <option value="">-- Selecione o serviço --</option>
                                <?php
                                $stmt = $conn->query("SELECT id, nome FROM servicos WHERE ativo = 1 ORDER BY nome");
                                foreach ($stmt->fetchAll() as $s):
                                    $selected = ($s['id'] == $agendamento['servico_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $selected; ?>><?php echo limpar($s['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="data_agendamento" class="form-label">Data *</label>
                            <input type="date" class="form-control" id="data_agendamento" name="data_agendamento" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $agendamento['data_agendamento']; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="hora" class="form-label">Hora *</label>
                            <input type="time" class="form-control" id="hora" name="hora" min="08:00" max="18:00" value="<?php echo $agendamento['hora']; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="agendado" <?php echo $agendamento['status'] == 'agendado' ? 'selected' : ''; ?>>Agendado</option>
                                <option value="confirmado" <?php echo $agendamento['status'] == 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                                <option value="concluido" <?php echo $agendamento['status'] == 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                                <option value="cancelado" <?php echo $agendamento['status'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo limpar($agendamento['observacoes']); ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="fas fa-save"></i> Atualizar Agendamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Carregar veículos do cliente selecionado ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    const clienteId = document.getElementById('cliente_id').value;
    if (clienteId) {
        carregarVeiculos(clienteId, 'veiculo_id', '<?php echo $agendamento['veiculo_id']; ?>');
    }
});

function validarAgendamento() {
    const dia = new Date(document.getElementById('data_agendamento').value + 'T00:00:00');
    const hoje = new Date();
    hoje.setHours(0,0,0,0);
    if (dia < hoje) {
        alert('A data não pode estar no passado.');
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
