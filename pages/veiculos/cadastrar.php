<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$titulo_pagina = "Novo Veículo";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="car-front" class="text-primary me-2"></i>Novo Veículo</h1>
        <span class="page-subtitle">Cadastre um veículo para um cliente</span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="processar.php?acao=cadastrar" onsubmit="return validarFormularioVeiculo()">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="cliente_id" class="form-label-with-icon"><i data-lucide="user"></i> Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">-- Selecione o cliente --</option>
                                <?php
                                $cliente_preselecionado = (int) ($_GET['cliente_id'] ?? 0);
                                $stmt = $conn->query("SELECT id, nome FROM clientes ORDER BY nome");
                                foreach ($stmt->fetchAll() as $c):
                                ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $cliente_preselecionado === (int) $c['id'] ? 'selected' : ''; ?>><?php echo limpar($c['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="placa" class="form-label-with-icon"><i data-lucide="hash"></i> Placa *</label>
                            <input type="text" class="form-control text-uppercase" id="placa" name="placa" placeholder="ABC-1234" oninput="formatarPlaca(this)" required>
                        </div>
                        <div class="col-md-4">
                            <label for="marca" class="form-label-with-icon"><i data-lucide="tag"></i> Marca *</label>
                            <input type="text" class="form-control" id="marca" name="marca" list="listaMarcas" placeholder="Ex: Volkswagen" required>
                            <datalist id="listaMarcas">
                                <option value="Fiat"><option value="Volkswagen"><option value="Chevrolet">
                                <option value="Ford"><option value="Toyota"><option value="Honda">
                                <option value="Hyundai"><option value="Renault"><option value="Jeep">
                                <option value="Nissan"><option value="Peugeot"><option value="Citroën">
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <label for="modelo" class="form-label-with-icon"><i data-lucide="car"></i> Modelo *</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" placeholder="Ex: Gol" required>
                        </div>
                        <div class="col-md-4">
                            <label for="ano" class="form-label-with-icon"><i data-lucide="calendar"></i> Ano</label>
                            <input type="number" class="form-control" id="ano" name="ano" min="1950" max="2030" placeholder="Ex: 2020">
                        </div>
                        <div class="col-md-8">
                            <label for="cor" class="form-label-with-icon"><i data-lucide="palette"></i> Cor</label>
                            <input type="text" class="form-control" id="cor" name="cor" placeholder="Escolha uma cor abaixo ou digite (ex: Preto, #b3261e)" autocomplete="off" maxlength="30">
                            <div class="color-palette" id="corPalette">
                                <?php foreach (cores_veiculo() as $nome => $hex): ?>
                                    <button type="button" class="color-swatch" data-cor="<?php echo esc($nome); ?>" data-hex="<?php echo esc($hex); ?>" title="<?php echo esc($nome); ?>" style="background-color:<?php echo esc($hex); ?>;"></button>
                                <?php endforeach; ?>
                                <label class="color-swatch color-picker-swatch" title="Cor personalizada (seletor de cor)">
                                    <input type="color" id="corPicker" value="#b3261e" aria-label="Cor personalizada">
                                    <i data-lucide="pipette" class="color-picker-icon"></i>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="km_atual" class="form-label-with-icon"><i data-lucide="gauge"></i> KM Atual</label>
                            <input type="number" class="form-control" id="km_atual" name="km_atual" min="0" placeholder="0">
                        </div>
                        <div class="col-md-6">
                            <label for="chassi" class="form-label-with-icon"><i data-lucide="fingerprint"></i> Chassi (opcional)</label>
                            <input type="text" class="form-control" id="chassi" name="chassi">
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">A cor fica vinculada ao veículo e aparece no quadro de veículos com o tom real.</small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="listar.php" class="btn btn-outline-secondary btn-icon"><i data-lucide="arrow-left"></i> Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i data-lucide="save"></i> Salvar Veículo
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
