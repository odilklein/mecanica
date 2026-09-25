<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/auditoria.php';
require_once __DIR__ . '/../../includes/fipe.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: listar.php'); exit(); }
validar_csrf();

$acao = $_GET['acao'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$cliente_id = (int)($_POST['cliente_id'] ?? 0);
$placa = strtoupper(limpar($_POST['placa'] ?? ''));
$marca = limpar($_POST['marca_nome'] ?? '');
$modelo = limpar($_POST['modelo_nome'] ?? '');
$ano = (int)($_POST['ano'] ?? 0);
$cor = limpar($_POST['cor'] ?? '');
$km_atual = (int)($_POST['km_atual'] ?? 0);
$chassi = limpar($_POST['chassi'] ?? '');

// ---------- Resolve catálogo FIPE (quando enviado) ----------
$marca_id = 0;
$modelo_base_id = 0;
$modelo_id = 0;
$fipe_versao = null;
$valor_fipe = null;
$fipe_mes_referencia = null;

$marca_id_post = (int)($_POST['marca_id'] ?? 0);
if ($marca_id_post > 0) {
    $st = $conn->prepare("SELECT nome_exibicao FROM fipe_marcas WHERE id = :id");
    $st->execute([':id' => $marca_id_post]);
    if ($linha = $st->fetch()) {
        $marca_id = $marca_id_post;
        $marca = $linha['nome_exibicao'];
    }
}

$base_id_post = (int)($_POST['modelo_base_id'] ?? 0);
if ($base_id_post > 0) {
    $st = $conn->prepare("SELECT nome_base, marca_id FROM fipe_modelos_base WHERE id = :id");
    $st->execute([':id' => $base_id_post]);
    if (($linha = $st->fetch()) && ($marca_id === 0 || (int)$linha['marca_id'] === $marca_id)) {
        $modelo_base_id = $base_id_post;
        $modelo = $linha['nome_base'];
        if ($marca_id === 0) {
            $marca_id = (int)$linha['marca_id'];
        }
    }
}

$modelo_id_post = (int)($_POST['modelo_id'] ?? 0);
if ($modelo_id_post > 0) {
    $st = $conn->prepare("SELECT nome_versao, base_id FROM fipe_modelos WHERE id = :id");
    $st->execute([':id' => $modelo_id_post]);
    if (($linha = $st->fetch()) && ($modelo_base_id === 0 || (int)$linha['base_id'] === $modelo_base_id)) {
        $modelo_id = $modelo_id_post;
        $fipe_versao = $linha['nome_versao'];
        if ($modelo_base_id === 0) {
            $modelo_base_id = (int)$linha['base_id'];
        }
    }
}

// ---------- Validações ----------
if ($cliente_id <= 0 || empty($placa) || empty($marca) || empty($modelo)) {
    $_SESSION['mensagem'] = "Preencha todos os campos obrigatórios.";
    $_SESSION['mensagem_tipo'] = "warning";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}

if (!validar_placa($placa)) {
    $_SESSION['mensagem'] = "Placa inválida. Use o formato ABC-1234 ou ABC1D23 (Mercosul).";
    $_SESSION['mensagem_tipo'] = "warning";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}

if ($ano < 1886 || $ano > (int) date('Y') + 1) {
    $_SESSION['mensagem'] = "Ano do veículo inválido.";
    $_SESSION['mensagem_tipo'] = "warning";
    if ($acao == 'editar') header("Location: editar.php?id=" . $id);
    else header("Location: cadastrar.php");
    exit();
}

// ---------- Valor FIPE (quando há versão + ano) ----------
if ($modelo_id > 0 && $ano > 0) {
    $fipe = fipe_valor_por_modelo_ano($conn, $modelo_id, $ano);
    if ($fipe) {
        $valor_fipe = $fipe['valor'];
        $fipe_mes_referencia = mb_substr($fipe['mes_referencia'], 0, 20);
    }
}

try {
    if ($acao == 'cadastrar') {
        $stmt = $conn->prepare("SELECT id FROM veiculos WHERE placa = :placa");
        $stmt->bindParam(':placa', $placa);
        $stmt->execute();
        if ($stmt->fetch()) {
            $_SESSION['mensagem'] = "Já existe um veículo com esta placa.";
            $_SESSION['mensagem_tipo'] = "warning";
            header("Location: cadastrar.php");
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO veiculos
            (cliente_id, placa, marca, modelo, ano, cor, km_atual, chassi,
             marca_id, modelo_base_id, modelo_id, fipe_versao, valor_fipe, fipe_mes_referencia)
            VALUES (:cliente_id, :placa, :marca, :modelo, :ano, :cor, :km_atual, :chassi,
             :marca_id, :modelo_base_id, :modelo_id, :fipe_versao, :valor_fipe, :fipe_mes)");
        $stmt->execute([
            ':cliente_id' => $cliente_id, ':placa' => $placa, ':marca' => $marca, ':modelo' => $modelo,
            ':ano' => $ano, ':cor' => $cor, ':km_atual' => $km_atual, ':chassi' => $chassi,
            ':marca_id' => $marca_id ?: null, ':modelo_base_id' => $modelo_base_id ?: null,
            ':modelo_id' => $modelo_id ?: null, ':fipe_versao' => $fipe_versao,
            ':valor_fipe' => $valor_fipe, ':fipe_mes' => $fipe_mes_referencia,
        ]);

        $_SESSION['mensagem'] = "Veículo cadastrado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'veiculo', (int) $conn->lastInsertId(), 'Veículo cadastrado', ['placa' => $placa, 'cliente_id' => $cliente_id]);
    } elseif ($acao == 'editar') {
        $stmt = $conn->prepare("UPDATE veiculos SET
            cliente_id = :cliente_id, placa = :placa, marca = :marca, modelo = :modelo, ano = :ano,
            cor = :cor, km_atual = :km_atual, chassi = :chassi,
            marca_id = :marca_id, modelo_base_id = :modelo_base_id, modelo_id = :modelo_id,
            fipe_versao = :fipe_versao, valor_fipe = :valor_fipe, fipe_mes_referencia = :fipe_mes
            WHERE id = :id");
        $stmt->execute([
            ':cliente_id' => $cliente_id, ':placa' => $placa, ':marca' => $marca, ':modelo' => $modelo,
            ':ano' => $ano, ':cor' => $cor, ':km_atual' => $km_atual, ':chassi' => $chassi,
            ':marca_id' => $marca_id ?: null, ':modelo_base_id' => $modelo_base_id ?: null,
            ':modelo_id' => $modelo_id ?: null, ':fipe_versao' => $fipe_versao,
            ':valor_fipe' => $valor_fipe, ':fipe_mes' => $fipe_mes_referencia,
            ':id' => $id,
        ]);

        $_SESSION['mensagem'] = "Veículo atualizado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'veiculo', $id, 'Veículo atualizado', ['placa' => $placa, 'cliente_id' => $cliente_id]);
    }

    header("Location: listar.php");
    exit();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['mensagem'] = "Não foi possível salvar o veículo.";
    $_SESSION['mensagem_tipo'] = "danger";
    if ($acao == 'editar') {
        header("Location: editar.php?id=" . $id);
    } else {
        header("Location: cadastrar.php");
    }
    exit();
}
