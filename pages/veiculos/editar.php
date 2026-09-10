<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT v.*, c.nome as cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id WHERE v.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$veiculo = $stmt->fetch();

if (!$veiculo) {
    $_SESSION['mensagem'] = "Veículo não encontrado.";
    $_SESSION['mensagem_tipo'] = "danger";
    header("Location: listar.php");
    exit();
}

$titulo_pagina = "Editar Veículo";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="car-front" class="text-primary me-2"></i>Editar Veículo</h1>
        <span class="page-subtitle"><?php echo limpar($veiculo['marca']) . ' ' . limpar($veiculo['modelo']) . ' - ' . limpar($veiculo['placa']); ?></span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=editar" onsubmit="return validarFormularioVeiculo()">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo $veiculo['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="cliente_id" class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">-- Selecione o cliente --</option>
                                <?php
                                $stmt_clientes = $conn->query("SELECT id, nome FROM clientes ORDER BY nome");
                                foreach ($stmt_clientes->fetchAll() as $c):
                                    $selected = ($c['id'] == $veiculo['cliente_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $selected; ?>><?php echo limpar($c['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="placa" class="form-label">Placa *</label>
                            <input type="text" class="form-control text-uppercase" id="placa" name="placa" value="<?php echo limpar($veiculo['placa']); ?>" oninput="formatarPlaca(this)" required>
                        </div>
                        <div class="col-md-4">
                            <label for="marca" class="form-label">Marca *</label>
                            <input type="text" class="form-control" id="marca" name="marca" value="<?php echo limpar($veiculo['marca']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="modelo" class="form-label">Modelo *</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" value="<?php echo limpar($veiculo['modelo']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="ano" class="form-label">Ano</label>
                            <input type="number" class="form-control" id="ano" name="ano" min="1950" max="2030" value="<?php echo $veiculo['ano']; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="cor" class="form-label">Cor</label>
                            <input type="text" class="form-control" id="cor" name="cor" value="<?php echo limpar($veiculo['cor']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="km_atual" class="form-label">KM Atual</label>
                            <input type="number" class="form-control" id="km_atual" name="km_atual" min="0" value="<?php echo $veiculo['km_atual']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="chassi" class="form-label">Chassi (opcional)</label>
                            <input type="text" class="form-control" id="chassi" name="chassi" value="<?php echo limpar($veiculo['chassi']); ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i data-lucide="save"></i> Atualizar Veículo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function validarFormularioVeiculo() {
    const placa = document.getElementById('placa').value;
    if (!/^[A-Z]{3}-\d{4}$/.test(placa)) {
        alert('Formato de placa inválido. Use o padrão ABC-1234');
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
