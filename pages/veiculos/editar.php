<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/fipe.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

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

fipe_garantir_schema($conn);
$fipe_marcas = $conn->query("SELECT id, nome_exibicao FROM fipe_marcas ORDER BY nome_exibicao")->fetchAll();
$catalogo_ok = count($fipe_marcas) > 0;

$valor_fipe_formatado = $veiculo['valor_fipe'] !== null
    ? formatar_moeda($veiculo['valor_fipe']) . ($veiculo['fipe_mes_referencia'] ? ' — ' . $veiculo['fipe_mes_referencia'] : '')
    : '';

$titulo_pagina = "Editar Veículo";
$head_extra = '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$veiculo_fipe_config = [
    'marcaId' => (int) ($veiculo['marca_id'] ?? 0),
    'baseId' => (int) ($veiculo['modelo_base_id'] ?? 0),
    'modeloId' => (int) ($veiculo['modelo_id'] ?? 0),
    'valorAtualFormatado' => $valor_fipe_formatado,
];
$scripts_extra = '<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>';
$scripts_extra .= '<script>window.VeiculoFipeConfig = ' . json_encode($veiculo_fipe_config) . ';</script>';
$scripts_extra .= '<script src="' . $caminho_base . '/js/veiculo-fipe.js"></script>';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="car-front" class="text-primary me-2"></i>Editar Veículo</h1>
        <span class="page-subtitle"><?php echo esc($veiculo['marca']) . ' ' . esc($veiculo['modelo']) . ' - ' . esc($veiculo['placa']); ?></span>
    </div>
    <a href="listar.php" class="btn btn-outline-secondary btn-icon">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body p-4">
                <?php if ($catalogo_ok && empty($veiculo['marca_id']) && !empty($veiculo['marca'])): ?>
                <div class="alert alert-info py-2"><i data-lucide="info"></i> Veículo cadastrado antes da integração FIPE. Valores atuais: <strong><?php echo esc($veiculo['marca']); ?> <?php echo esc($veiculo['modelo']); ?></strong>. Selecione marca/modelo para padronizar.</div>
                <?php endif; ?>
                <form method="POST" action="processar.php?acao=editar" onsubmit="return validarFormularioVeiculo()">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo $veiculo['id']; ?>">
                    <?php if ($catalogo_ok): ?>
                    <input type="hidden" name="marca_nome" id="marca_nome" value="<?php echo esc($veiculo['marca']); ?>">
                    <input type="hidden" name="modelo_nome" id="modelo_nome" value="<?php echo esc($veiculo['modelo']); ?>">
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="cliente_id" class="form-label-with-icon"><i data-lucide="user"></i> Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">-- Selecione o cliente --</option>
                                <?php
                                $stmt_clientes = $conn->query("SELECT id, nome FROM clientes ORDER BY nome");
                                foreach ($stmt_clientes->fetchAll() as $c):
                                    $selected = ($c['id'] == $veiculo['cliente_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $selected; ?>><?php echo esc($c['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="placa" class="form-label-with-icon"><i data-lucide="hash"></i> Placa *</label>
                            <input type="text" class="form-control text-uppercase" id="placa" name="placa" value="<?php echo esc($veiculo['placa']); ?>" oninput="formatarPlaca(this)" required>
                        </div>
                        <?php if ($catalogo_ok): ?>
                        <div class="col-md-4">
                            <label for="marca_id" class="form-label-with-icon"><i data-lucide="tag"></i> Marca *</label>
                            <select class="form-select" id="marca_id" name="marca_id" data-placeholder="Selecione a marca">
                                <option value="">Selecione a marca</option>
                                <?php foreach ($fipe_marcas as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>" <?php echo (int) ($veiculo['marca_id'] ?? 0) === (int) $m['id'] ? 'selected' : ''; ?>><?php echo esc($m['nome_exibicao']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="modelo_base_id" class="form-label-with-icon"><i data-lucide="car"></i> Modelo *</label>
                            <select class="form-select" id="modelo_base_id" name="modelo_base_id" data-placeholder="Selecione o modelo">
                                <option value="">Selecione a marca primeiro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="modelo_id" class="form-label-with-icon"><i data-lucide="git-branch"></i> Versão (opcional)</label>
                            <select class="form-select" id="modelo_id" name="modelo_id" data-placeholder="Selecione a versão">
                                <option value="">Selecione o modelo primeiro</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <div class="col-md-4">
                            <label for="marca_nome_input" class="form-label-with-icon"><i data-lucide="tag"></i> Marca *</label>
                            <input type="text" class="form-control" id="marca_nome_input" name="marca_nome" value="<?php echo esc($veiculo['marca']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="modelo_nome_input" class="form-label-with-icon"><i data-lucide="car"></i> Modelo *</label>
                            <input type="text" class="form-control" id="modelo_nome_input" name="modelo_nome" value="<?php echo esc($veiculo['modelo']); ?>" required>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label for="ano" class="form-label-with-icon"><i data-lucide="calendar"></i> Ano</label>
                            <input type="number" class="form-control" id="ano" name="ano" min="1950" max="2030" value="<?php echo $veiculo['ano']; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="valorFipe" class="form-label-with-icon"><i data-lucide="badge-dollar-sign"></i> Valor FIPE</label>
                            <input type="text" class="form-control" id="valorFipe" value="<?php echo esc($valor_fipe_formatado); ?>" readonly placeholder="Calculado ao informar versão e ano">
                        </div>
                        <div class="col-md-8">
                            <label for="cor" class="form-label-with-icon"><i data-lucide="palette"></i> Cor</label>
                            <input type="text" class="form-control" id="cor" name="cor" value="<?php echo esc($veiculo['cor']); ?>" placeholder="Escolha uma cor abaixo ou digite (ex: Preto, #b3261e)" autocomplete="off" maxlength="30">
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
                            <input type="number" class="form-control" id="km_atual" name="km_atual" min="0" value="<?php echo $veiculo['km_atual']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="chassi" class="form-label-with-icon"><i data-lucide="fingerprint"></i> Chassi (opcional)</label>
                            <input type="text" class="form-control" id="chassi" name="chassi" value="<?php echo esc($veiculo['chassi']); ?>">
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Dados de marcas/modelos: Tabela FIPE (base comunitária Parallelum).</small>
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
    if (!/^[A-Z]{3}-?\d[A-Z0-9]\d{2}$/.test(placa)) {
        alert('Formato de placa inválido. Use ABC-1234 ou ABC1D23 (Mercosul).');
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
