<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit();
}
validar_csrf();

$nome = limpar($_POST['nome'] ?? '');
$telefone = limpar($_POST['telefone'] ?? '');
$marca = limpar($_POST['marca'] ?? '');
$modelo = limpar($_POST['modelo'] ?? '');
$placa = strtoupper(limpar($_POST['placa'] ?? ''));
$prioridade = $_POST['prioridade'] ?? 'normal';
$queixa = limpar($_POST['queixa_inicial'] ?? '');
$observacoes = limpar($_POST['observacoes_internas'] ?? '');

if ($nome === '' || $queixa === '' || !in_array($prioridade, ['baixa', 'normal', 'alta', 'urgente'], true)) {
    $_SESSION['mensagem'] = 'Informe o nome, a queixa e uma prioridade válida.';
    $_SESSION['mensagem_tipo'] = 'warning';
    header('Location: cadastrar.php');
    exit();
}

try {
    $conn->beginTransaction();
    $cliente_id = null;
    if ($telefone !== '') {
        $stmt = $conn->prepare('SELECT id FROM clientes WHERE telefone = :telefone LIMIT 1');
        $stmt->execute([':telefone' => $telefone]);
        $cliente_id = $stmt->fetchColumn() ?: null;
    }
    if (!$cliente_id) {
        $stmt = $conn->prepare('INSERT INTO clientes (nome, telefone) VALUES (:nome, :telefone)');
        $stmt->execute([':nome' => $nome, ':telefone' => $telefone ?: null]);
        $cliente_id = (int) $conn->lastInsertId();
    } else {
        $stmt = $conn->prepare('UPDATE clientes SET nome = :nome WHERE id = :id');
        $stmt->execute([':nome' => $nome, ':id' => $cliente_id]);
    }

    $veiculo_id = null;
    if ($placa !== '') {
        $stmt = $conn->prepare('SELECT id, cliente_id FROM veiculos WHERE placa = :placa LIMIT 1');
        $stmt->execute([':placa' => $placa]);
        $veiculo = $stmt->fetch();
        if ($veiculo && (int) $veiculo['cliente_id'] !== $cliente_id) {
            throw new RuntimeException('A placa informada já está vinculada a outro cliente.');
        }
        if ($veiculo) {
            $veiculo_id = (int) $veiculo['id'];
            $stmt = $conn->prepare('UPDATE veiculos SET marca = :marca, modelo = :modelo WHERE id = :id');
            $stmt->execute([':marca' => $marca ?: null, ':modelo' => $modelo ?: null, ':id' => $veiculo_id]);
        } else {
            $stmt = $conn->prepare('INSERT INTO veiculos (cliente_id, placa, marca, modelo) VALUES (:cliente_id, :placa, :marca, :modelo)');
            $stmt->execute([':cliente_id' => $cliente_id, ':placa' => $placa, ':marca' => $marca ?: null, ':modelo' => $modelo ?: null]);
            $veiculo_id = (int) $conn->lastInsertId();
        }
    }

    $conn->query("SELECT GET_LOCK('mecanica_os_numero', 5)");
    $numero = (int) $conn->query('SELECT COALESCE(MAX(numero), 0) + 1 FROM ordens_servico')->fetchColumn();
    $stmt = $conn->prepare("INSERT INTO ordens_servico (numero, cliente_id, veiculo_id, status, prioridade, queixa_inicial, observacoes_internas, responsavel_chefe_id, criado_por) VALUES (:numero, :cliente_id, :veiculo_id, 'recebida', :prioridade, :queixa, :observacoes, :chefe, :criado_por)");
    $stmt->execute([
        ':numero' => $numero,
        ':cliente_id' => $cliente_id,
        ':veiculo_id' => $veiculo_id,
        ':prioridade' => $prioridade,
        ':queixa' => $queixa,
        ':observacoes' => $observacoes ?: null,
        ':chefe' => $_SESSION['usuario_tipo'] === 'mecanico_chefe' ? $_SESSION['usuario_id'] : null,
        ':criado_por' => $_SESSION['usuario_id']
    ]);
    $os_id = (int) $conn->lastInsertId();
    $conn->query("SELECT RELEASE_LOCK('mecanica_os_numero')");

    $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_novo, descricao, criado_por) VALUES (:os_id, 'criacao', 'recebida', 'Atendimento criado', :criado_por)");
    $stmt->execute([':os_id' => $os_id, ':criado_por' => $_SESSION['usuario_id']]);
    $conn->commit();

    $_SESSION['mensagem'] = 'Ordem de serviço criada com sucesso.';
    $_SESSION['mensagem_tipo'] = 'success';
    header('Location: listar.php');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $conn->query("SELECT RELEASE_LOCK('mecanica_os_numero')");
    error_log($e->getMessage());
    $_SESSION['mensagem'] = 'Não foi possível criar a ordem de serviço.';
    $_SESSION['mensagem_tipo'] = 'danger';
    header('Location: cadastrar.php');
    exit();
}
