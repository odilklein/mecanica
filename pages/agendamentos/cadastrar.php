<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$titulo_pagina = "Novo Agendamento";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-calendar-plus text-primary me-2"></i>Novo Agendamento</h1>
        <span class="page-subtitle">Agende um atendimento para um cliente</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=cadastrar" onsubmit="return validarAgendamento()">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cliente_id" class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required onchange="carregarVeiculos(this.value, 'veiculo_id', null)">
                                <option value="">-- Selecione o cliente --</option>
                                <?php
                                $stmt = $conn->query("SELECT id, nome FROM clientes ORDER BY nome");
                                foreach ($stmt->fetchAll() as $c):
                                ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo limpar($c['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="veiculo_id" class="form-label">Veículo</label>
                            <select class="form-select" id="veiculo_id" name="veiculo_id">
                                <option value="">Selecione o cliente primeiro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="servico_id" class="form-label">Serviço *</label>
                            <select class="form-select" id="servico_id" name="servico_id" required>
                                <option value="">-- Selecione o serviço --</option>
                                <?php
                                $stmt = $conn->query("SELECT id, nome FROM servicos WHERE ativo = 1 ORDER BY nome");
                                foreach ($stmt->fetchAll() as $s):
                                ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo limpar($s['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="data_agendamento" class="form-label">Data *</label>
                            <input type="date" class="form-control" id="data_agendamento" name="data_agendamento" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="hora" class="form-label">Hora *</label>
                            <input type="time" class="form-control" id="hora" name="hora" min="08:00" max="18:00" required>
                        </div>
                        <div class="col-12">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Motivo do agendamento, sintomas relatados, etc."></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="fas fa-calendar-check"></i> Salvar Agendamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
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
