<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/notificacoes.php';
require_once __DIR__ . '/../../includes/auditoria.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit();
}
validar_csrf();

$acao = $_GET['acao'] ?? '';
$os_id = (int) ($_POST['os_id'] ?? 0);

if ($acao !== 'cadastrar') {
    if ($os_id <= 0) {
        http_response_code(400);
        exit('Ordem de serviço inválida.');
    }

    $stmt = $conn->prepare('SELECT id, numero, status FROM ordens_servico WHERE id = :id');
    $stmt->execute([':id' => $os_id]);
    $os_atual = $stmt->fetch();
    if (!$os_atual) {
        http_response_code(404);
        exit('Ordem de serviço não encontrada.');
    }

    if ($_SESSION['usuario_tipo'] === 'mecanico') {
        $stmt = $conn->prepare('SELECT 1 FROM os_responsaveis WHERE os_id = :os_id AND usuario_id = :usuario_id AND concluido_em IS NULL');
        $stmt->execute([':os_id' => $os_id, ':usuario_id' => $_SESSION['usuario_id']]);
        if (!$stmt->fetchColumn()) {
            http_response_code(403);
            exit('Esta ordem de serviço não está atribuída a você.');
        }
    }

    if ($acao === 'atualizar_diagnostico') {
        $diagnostico = limpar($_POST['diagnostico'] ?? '');
        $observacoes = limpar($_POST['observacoes_internas'] ?? '');
        if ($diagnostico === '') {
            $_SESSION['mensagem'] = 'Informe o diagnóstico técnico.';
            $_SESSION['mensagem_tipo'] = 'warning';
            header('Location: detalhes.php?id=' . $os_id);
            exit();
        }
        $stmt = $conn->prepare("UPDATE ordens_servico SET diagnostico = :diagnostico, observacoes_internas = :observacoes, status = IF(status IN ('recebida', 'triagem', 'aguardando_diagnostico'), 'aguardando_orcamento', status) WHERE id = :id");
        $stmt->execute([':diagnostico' => $diagnostico, ':observacoes' => $observacoes ?: null, ':id' => $os_id]);
        $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, 'diagnostico', :anterior, 'aguardando_orcamento', 'Diagnóstico técnico atualizado', :usuario)");
        $stmt->execute([':os_id' => $os_id, ':anterior' => $os_atual['status'], ':usuario' => $_SESSION['usuario_id']]);
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'diagnostico', 'Diagnóstico da OS #' . $os_atual['numero'] . ' atualizado — aguardando orçamento.', (int) $_SESSION['usuario_id']);
        $_SESSION['mensagem'] = 'Diagnóstico atualizado.';
        $_SESSION['mensagem_tipo'] = 'success';
        header('Location: detalhes.php?id=' . $os_id);
        exit();
    }

    if ($acao === 'mudar_tarefa') {
        $tarefa_id = (int) ($_POST['tarefa_id'] ?? 0);
        $novo_status = $_POST['tarefa_status'] ?? '';
        if (!in_array($novo_status, ['pendente', 'em_execucao', 'concluida', 'bloqueada'], true)) {
            throw new RuntimeException('Status de tarefa inválido.');
        }
        $stmt = $conn->prepare('SELECT id FROM os_tarefas WHERE id = :tarefa AND os_id = :os_id');
        $stmt->execute([':tarefa' => $tarefa_id, ':os_id' => $os_id]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Tarefa não encontrada nesta OS.');
        $stmt = $conn->prepare("UPDATE os_tarefas SET status = :status, concluido_por = IF(:status = 'concluida', :usuario, concluido_por), concluido_em = IF(:status = 'concluida', NOW(), concluido_em) WHERE id = :id");
        $stmt->execute([':status' => $novo_status, ':usuario' => $_SESSION['usuario_id'], ':id' => $tarefa_id]);
        $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'tarefa_status', :descricao, :usuario)");
        $stmt->execute([':os_id' => $os_id, ':descricao' => 'Tarefa atualizada para ' . $novo_status, ':usuario' => $_SESSION['usuario_id']]);
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'tarefa', 'Uma tarefa da OS #' . $os_atual['numero'] . ' foi atualizada para "' . str_replace('_', ' ', $novo_status) . '".', (int) $_SESSION['usuario_id']);
        $_SESSION['mensagem'] = 'Tarefa atualizada.';
        $_SESSION['mensagem_tipo'] = 'success';
        header('Location: detalhes.php?id=' . $os_id);
        exit();
    }

    if ($acao === 'mudar_status') {
        exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
        $novo_status = $_POST['status'] ?? '';
        $status_validos = ['recebida', 'triagem', 'aguardando_diagnostico', 'aguardando_orcamento', 'aguardando_aprovacao', 'aguardando_peca', 'pronta_para_execucao', 'em_execucao', 'aguardando_retorno', 'pronta_para_entrega', 'entregue', 'cancelada'];
        if (!in_array($novo_status, $status_validos, true)) {
            http_response_code(400);
            exit('Status inválido.');
        }
        $transicoes = [
            'recebida' => ['triagem', 'aguardando_diagnostico', 'cancelada'],
            'triagem' => ['aguardando_diagnostico', 'aguardando_orcamento', 'cancelada'],
            'aguardando_diagnostico' => ['aguardando_orcamento', 'aguardando_peca', 'cancelada'],
            'aguardando_orcamento' => ['aguardando_aprovacao', 'aguardando_peca', 'cancelada'],
            'aguardando_aprovacao' => ['aguardando_peca', 'pronta_para_execucao', 'cancelada'],
            'aguardando_peca' => ['pronta_para_execucao', 'cancelada'],
            'pronta_para_execucao' => ['em_execucao', 'cancelada'],
            'em_execucao' => ['aguardando_retorno', 'pronta_para_entrega', 'aguardando_peca', 'cancelada'],
            'aguardando_retorno' => ['em_execucao', 'pronta_para_entrega', 'cancelada'],
            'pronta_para_entrega' => ['entregue', 'aguardando_retorno'],
            'entregue' => [],
            'cancelada' => []
        ];
        if ($novo_status !== $os_atual['status'] && !in_array($novo_status, $transicoes[$os_atual['status']] ?? [], true)) {
            throw new RuntimeException('Essa mudança de status não é permitida para a etapa atual.');
        }
        if ($novo_status === 'entregue') {
            $stmt = $conn->prepare("SELECT 1 FROM os_entregas WHERE os_id = :os_id AND status = 'concluida' LIMIT 1");
            $stmt->execute([':os_id' => $os_id]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Confirme a entrega ou retirada antes de encerrar a OS.');
        }
        $conn->beginTransaction();
        $stmt = $conn->prepare('UPDATE ordens_servico SET status = :status, data_conclusao = IF(:status = \'entregue\', NOW(), data_conclusao) WHERE id = :id');
        $stmt->execute([':status' => $novo_status, ':id' => $os_id]);
        // O evento de mudança de status é gravado automaticamente pela
        // trigger trg_os_status_historico (ver sql/mecanica_teste.sql).
        $num_os = str_pad((string) $os_atual['numero'], 4, '0', STR_PAD_LEFT);
        $msg_status = 'O status da OS #' . $num_os . ' mudou para "' . str_replace('_', ' ', $novo_status) . '".';
        notificar_responsaveis_os($conn, $os_id, 'os_status', $msg_status, (int) $_SESSION['usuario_id']);
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'os_status', $msg_status, (int) $_SESSION['usuario_id']);
        if ($novo_status === 'entregue') {
            $stmt = $conn->prepare("SELECT cliente_id, veiculo_id, COALESCE(diagnostico, queixa_inicial) AS descricao FROM ordens_servico WHERE id = :id");
            $stmt->execute([':id' => $os_id]);
            $dados_os = $stmt->fetch();
            $stmt = $conn->prepare('INSERT INTO cliente_servicos_historico (cliente_id, veiculo_id, os_id, descricao) VALUES (:cliente, :veiculo, :os, :descricao)');
            $stmt->execute([':cliente' => $dados_os['cliente_id'], ':veiculo' => $dados_os['veiculo_id'], ':os' => $os_id, ':descricao' => $dados_os['descricao']]);
        }
        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'status', 'ordem_servico', $os_id, 'Status alterado de ' . $os_atual['status'] . ' para ' . $novo_status, ['status_anterior' => $os_atual['status'], 'status_novo' => $novo_status]);
        $_SESSION['mensagem'] = 'Status da OS atualizado.';
        $_SESSION['mensagem_tipo'] = 'success';
        header('Location: detalhes.php?id=' . $os_id);
        exit();
    }

    if ($acao === 'atribuir') {
        exigir_papel(['admin', 'mecanico_chefe']);
        $usuario_id = (int) ($_POST['usuario_id'] ?? 0);
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = :id AND tipo IN ('mecanico', 'mecanico_chefe')");
        $stmt->execute([':id' => $usuario_id]);
        if (!$stmt->fetchColumn()) {
            http_response_code(400);
            exit('Mecânico inválido.');
        }
        $stmt = $conn->prepare('INSERT INTO os_responsaveis (os_id, usuario_id, atribuido_por) VALUES (:os_id, :usuario_id, :atribuido_por) ON DUPLICATE KEY UPDATE concluido_em = NULL');
        $stmt->execute([':os_id' => $os_id, ':usuario_id' => $usuario_id, ':atribuido_por' => $_SESSION['usuario_id']]);
        $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, os_id, tipo, mensagem) VALUES (:usuario_id, :os_id, 'os_atribuida', 'Uma nova ordem de serviço foi atribuída a você.')");
        $stmt->execute([':usuario_id' => $usuario_id, ':os_id' => $os_id]);
        $_SESSION['mensagem'] = 'Mecânico atribuído à OS.';
        $_SESSION['mensagem_tipo'] = 'success';
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'atribuir', 'ordem_servico', $os_id, 'Mecânico #' . $usuario_id . ' atribuído à OS', ['usuario_id' => $usuario_id]);
        header('Location: detalhes.php?id=' . $os_id);
        exit();
    }

    if ($acao === 'registrar_entrega') {
        exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
        $modalidade = $_POST['modalidade'] ?? '';
        $responsavel = limpar($_POST['responsavel'] ?? '');
        $destino = limpar($_POST['destino'] ?? '');
        $km_saida = (int) ($_POST['km_saida'] ?? 0);
        if (!in_array($modalidade, ['retirada', 'entrega'], true)) {
            http_response_code(400);
            exit('Modalidade de entrega inválida.');
        }
        if ($os_atual['status'] !== 'pronta_para_entrega') {
            throw new RuntimeException('A OS precisa estar pronta para entrega antes da confirmação.');
        }
        $stmt = $conn->prepare("INSERT INTO os_entregas (os_id, modalidade, responsavel, destino, km_saida, status, criado_por, concluido_por, concluido_em) VALUES (:os_id, :modalidade, :responsavel, :destino, :km_saida, 'concluida', :usuario, :usuario, NOW())");
        $stmt->execute([':os_id' => $os_id, ':modalidade' => $modalidade, ':responsavel' => $responsavel ?: null, ':destino' => $destino ?: null, ':km_saida' => $km_saida ?: null, ':usuario' => $_SESSION['usuario_id']]);
        $stmt = $conn->prepare("UPDATE ordens_servico SET status = 'entregue', data_conclusao = NOW() WHERE id = :id");
        $stmt->execute([':id' => $os_id]);
        $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'entrega_planejada', 'Entrega ou retirada registrada', :usuario)");
        $stmt->execute([':os_id' => $os_id, ':usuario' => $_SESSION['usuario_id']]);
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'entrega', 'Entrega da OS #' . str_pad((string) $os_atual['numero'], 4, '0', STR_PAD_LEFT) . ' registrada — aguardando confirmação.', (int) $_SESSION['usuario_id']);
        $stmt = $conn->prepare("SELECT cliente_id, veiculo_id, COALESCE(diagnostico, queixa_inicial) AS descricao FROM ordens_servico WHERE id = :id");
        $stmt->execute([':id' => $os_id]);
        $dados_os = $stmt->fetch();
        $stmt = $conn->prepare('INSERT INTO cliente_servicos_historico (cliente_id, veiculo_id, os_id, descricao) VALUES (:cliente, :veiculo, :os, :descricao)');
        $stmt->execute([':cliente' => $dados_os['cliente_id'], ':veiculo' => $dados_os['veiculo_id'], ':os' => $os_id, ':descricao' => $dados_os['descricao']]);
        $_SESSION['mensagem'] = 'Entrega ou retirada registrada.';
        $_SESSION['mensagem_tipo'] = 'success';
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'entrega', 'ordem_servico', $os_id, 'Entrega/retirada registrada (' . $modalidade . ')', ['modalidade' => $modalidade]);
        header('Location: detalhes.php?id=' . $os_id);
        exit();
    }
}

$nome = limpar($_POST['nome'] ?? '');
$telefone = limpar($_POST['telefone'] ?? '');
$marca = limpar($_POST['marca'] ?? '');
$modelo = limpar($_POST['modelo'] ?? '');
$placa = strtoupper(limpar($_POST['placa'] ?? ''));
$prioridade = $_POST['prioridade'] ?? 'normal';
$queixa = limpar($_POST['queixa_inicial'] ?? '');
$observacoes = limpar($_POST['observacoes_internas'] ?? '');
$tarefas = array_values(array_filter(array_map('trim', explode("\n", $_POST['tarefas'] ?? ''))));
$funcionarios_ids = array_values(array_unique(array_map('intval', $_POST['funcionarios_ids'] ?? [])));
$pecas_linhas = array_values(array_filter(array_map('trim', explode("\n", $_POST['pecas'] ?? ''))));

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

    foreach ($funcionarios_ids as $funcionario_id) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = :id AND ativo = 1 AND tipo IN ('mecanico', 'mecanico_chefe')");
        $stmt->execute([':id' => $funcionario_id]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Um dos funcionários selecionados não está disponível.');
        }
        $stmt = $conn->prepare('INSERT INTO os_responsaveis (os_id, usuario_id, atribuido_por) VALUES (:os_id, :usuario_id, :atribuido_por)');
        $stmt->execute([':os_id' => $os_id, ':usuario_id' => $funcionario_id, ':atribuido_por' => $_SESSION['usuario_id']]);
    }

    $num_formatado = str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    notificar_usuarios($conn, $funcionarios_ids, $os_id, 'os_atribuida', 'Nova OS #' . $num_formatado . ' atribuída a você.');
    notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'os_criada', 'Nova ordem de serviço #' . $num_formatado . ' criada.', (int) $_SESSION['usuario_id']);

    foreach ($tarefas as $tarefa) {
        if (mb_strlen($tarefa) > 255) {
            throw new RuntimeException('Cada tarefa deve ter no máximo 255 caracteres.');
        }
        $stmt = $conn->prepare('INSERT INTO os_tarefas (os_id, descricao, criado_por) VALUES (:os_id, :descricao, :criado_por)');
        $stmt->execute([':os_id' => $os_id, ':descricao' => $tarefa, ':criado_por' => $_SESSION['usuario_id']]);
    }

    if ($pecas_linhas) {
        $stmt = $conn->prepare("INSERT INTO pedidos_pecas (os_id, status, observacoes, criado_por) VALUES (:os_id, 'solicitado', 'Pedido criado automaticamente na abertura da OS.', :criado_por)");
        $stmt->execute([':os_id' => $os_id, ':criado_por' => $_SESSION['usuario_id']]);
        $pedido_id = (int) $conn->lastInsertId();
        foreach ($pecas_linhas as $peca_linha) {
            $partes = array_map('trim', explode('|', $peca_linha, 2));
            $descricao = $partes[0] ?? '';
            $quantidade = isset($partes[1]) ? (int) $partes[1] : 1;
            if ($descricao === '' || $quantidade <= 0 || mb_strlen($descricao) > 200) {
                throw new RuntimeException('Use o formato "Peça | quantidade" para cada peça.');
            }
            $stmt = $conn->prepare('INSERT INTO pedido_pecas_itens (pedido_id, descricao, quantidade) VALUES (:pedido_id, :descricao, :quantidade)');
            $stmt->execute([':pedido_id' => $pedido_id, ':descricao' => $descricao, ':quantidade' => $quantidade]);
        }
        $stmt = $conn->prepare("UPDATE ordens_servico SET status = 'aguardando_peca' WHERE id = :id");
        $stmt->execute([':id' => $os_id]);
    }

    $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_novo, descricao, criado_por) VALUES (:os_id, 'criacao', 'recebida', 'Atendimento criado', :criado_por)");
    $stmt->execute([':os_id' => $os_id, ':criado_por' => $_SESSION['usuario_id']]);
    $conn->commit();

    $_SESSION['mensagem'] = 'Ordem de serviço criada com sucesso.';
    $_SESSION['mensagem_tipo'] = 'success';
    registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'ordem_servico', $os_id, 'OS #' . $num_formatado . ' criada', ['numero' => $numero, 'cliente_id' => $cliente_id, 'veiculo_id' => $veiculo_id]);
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
