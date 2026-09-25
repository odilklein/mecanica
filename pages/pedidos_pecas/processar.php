<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/notificacoes.php';
require_once __DIR__ . '/../../includes/auditoria.php';
require_once __DIR__ . '/../../includes/os_historico.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: listar.php'); exit(); }
validar_csrf();
$acao = $_GET['acao'] ?? '';
try {
    if ($acao === 'cadastrar') {
        $os_id = (int) ($_POST['os_id'] ?? 0);
        $descricao = limpar($_POST['descricao'] ?? '');
        $quantidade = (int) ($_POST['quantidade'] ?? 0);
        $fornecedor = $_SESSION['usuario_tipo'] === 'mecanico' ? null : (limpar($_POST['fornecedor'] ?? '') ?: null);
        $previsao = $_POST['previsao_entrega'] ?? null;
        $observacoes = limpar($_POST['observacoes'] ?? '');
        if ($os_id <= 0 || $descricao === '' || $quantidade <= 0) { throw new RuntimeException('Informe a OS, a descrição e uma quantidade válida.'); }
        $stmt = $conn->prepare("SELECT id, numero, status FROM ordens_servico WHERE id = :id AND status NOT IN ('entregue', 'cancelada')");
        $stmt->execute([':id' => $os_id]);
        $os_pedido = $stmt->fetch();
        if (!$os_pedido) { throw new RuntimeException('A OS não está disponível para pedido.'); }
        if ($_SESSION['usuario_tipo'] === 'mecanico') {
            $stmt = $conn->prepare('SELECT 1 FROM os_responsaveis WHERE os_id = :os_id AND usuario_id = :usuario_id AND concluido_em IS NULL');
            $stmt->execute([':os_id' => $os_id, ':usuario_id' => $_SESSION['usuario_id']]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Você só pode solicitar peças para OS atribuídas a você.');
        }
        $conn->beginTransaction();
        $stmt = $conn->prepare('INSERT INTO pedidos_pecas (os_id, fornecedor, status, previsao_entrega, observacoes, criado_por) VALUES (:os_id, :fornecedor, \'solicitado\', :previsao, :observacoes, :usuario)');
        $stmt->execute([':os_id' => $os_id, ':fornecedor' => $fornecedor ?: null, ':previsao' => $previsao ?: null, ':observacoes' => $observacoes ?: null, ':usuario' => $_SESSION['usuario_id']]);
        $pedido_id = (int) $conn->lastInsertId();
        $stmt = $conn->prepare('INSERT INTO pedido_pecas_itens (pedido_id, descricao, quantidade) VALUES (:pedido, :descricao, :quantidade)');
        $stmt->execute([':pedido' => $pedido_id, ':descricao' => $descricao, ':quantidade' => $quantidade]);
        $stmt = $conn->prepare("UPDATE ordens_servico SET status = IF(status IN ('recebida', 'triagem', 'aguardando_diagnostico', 'aguardando_orcamento'), 'aguardando_peca', status) WHERE id = :id");
        $stmt->execute([':id' => $os_id]);
        registrar_evento_status_os($conn, $os_id, $os_pedido['status'], (int) $_SESSION['usuario_id']);
        $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_solicitada', 'Pedido de peça criado', :usuario)");
        $stmt->execute([':os_id' => $os_id, ':usuario' => $_SESSION['usuario_id']]);
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'peca_pedido', 'Pedido de peça criado para a OS #' . str_pad((string) $os_pedido['numero'], 4, '0', STR_PAD_LEFT) . '.', (int) $_SESSION['usuario_id']);
        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'pedido_peca', $pedido_id, 'Pedido de peça criado para a OS #' . $os_pedido['numero']);
        $_SESSION['mensagem'] = 'Pedido de peça criado.';
        $_SESSION['mensagem_tipo'] = 'success';
        header('Location: ../ordens_servico/detalhes.php?id=' . $os_id);
        exit();
    }
    if ($acao === 'atualizar') {
        exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['solicitado', 'validado', 'pedido', 'a_caminho', 'recebido', 'cancelado'], true)) { throw new RuntimeException('Status inválido.'); }
        $stmt = $conn->prepare('SELECT os_id, status FROM pedidos_pecas WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $pedido = $stmt->fetch();
        if (!$pedido) { throw new RuntimeException('Pedido não encontrado.'); }
        $transicoes = ['solicitado' => ['validado', 'cancelado'], 'validado' => ['pedido', 'cancelado'], 'pedido' => ['a_caminho', 'cancelado'], 'a_caminho' => ['recebido'], 'recebido' => [], 'cancelado' => []];
        if ($status !== $pedido['status'] && !in_array($status, $transicoes[$pedido['status']] ?? [], true)) throw new RuntimeException('Essa alteração não é permitida para o status atual.');
        if ($status === 'pedido' && !tem_papel(['admin', 'secretaria'])) throw new RuntimeException('Somente a secretaria pode confirmar o pedido ao fornecedor.');
        $conn->beginTransaction();
        $stmt = $conn->prepare('UPDATE pedidos_pecas SET status = :status, fornecedor = :fornecedor, previsao_entrega = :previsao, observacoes = :observacoes, recebido_por = IF(:status = \'recebido\', :usuario, recebido_por), recebido_em = IF(:status = \'recebido\', NOW(), recebido_em) WHERE id = :id');
        $stmt->execute([':status' => $status, ':fornecedor' => limpar($_POST['fornecedor'] ?? '') ?: null, ':previsao' => ($_POST['previsao_entrega'] ?? '') ?: null, ':observacoes' => limpar($_POST['observacoes'] ?? '') ?: null, ':usuario' => $_SESSION['usuario_id'], ':id' => $id]);
        $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_status', :descricao, :usuario)");
        $stmt->execute([':os_id' => $pedido['os_id'], ':descricao' => 'Pedido de peça alterado para ' . $status, ':usuario' => $_SESSION['usuario_id']]);
        if ($status === 'recebido') {
            $stmt = $conn->prepare('UPDATE pedido_pecas_itens SET recebido = quantidade, recebido_em = NOW() WHERE pedido_id = :id AND recebido < quantidade');
            $stmt->execute([':id' => $id]);
            $stOs = $conn->prepare('SELECT status FROM ordens_servico WHERE id = :id');
            $stOs->execute([':id' => $pedido['os_id']]);
            $os_status_anterior = (string) $stOs->fetchColumn();
            $stmt = $conn->prepare("UPDATE ordens_servico SET status = IF(status = 'aguardando_peca', 'pronta_para_execucao', status) WHERE id = :id");
            $stmt->execute([':id' => $pedido['os_id']]);
            registrar_evento_status_os($conn, (int) $pedido['os_id'], $os_status_anterior, (int) $_SESSION['usuario_id']);
            $stmt = $conn->prepare('SELECT usuario_id FROM os_responsaveis WHERE os_id = :os_id AND concluido_em IS NULL');
            $stmt->execute([':os_id' => $pedido['os_id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $usuario_id) {
                $notificacao = $conn->prepare("INSERT INTO notificacoes (usuario_id, os_id, tipo, mensagem) VALUES (:usuario_id, :os_id, 'peca_disponivel', 'A peça solicitada para uma ordem de serviço está disponível.')");
                $notificacao->execute([':usuario_id' => $usuario_id, ':os_id' => $pedido['os_id']]);
            }
        }
        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'status', 'pedido_peca', $id, 'Pedido de peça alterado para ' . $status, ['status_anterior' => $pedido['status'], 'status_novo' => $status]);
        $_SESSION['mensagem'] = 'Pedido atualizado.';
        $_SESSION['mensagem_tipo'] = 'success';
        header('Location: editar.php?id=' . $id);
        exit();
    }
    if ($acao === 'receber_itens') {
        exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('SELECT p.id, p.os_id, p.status, os.numero FROM pedidos_pecas p JOIN ordens_servico os ON os.id = p.os_id WHERE p.id = :id');
        $stmt->execute([':id' => $id]);
        $pedido = $stmt->fetch();
        if (!$pedido) throw new RuntimeException('Pedido não encontrado.');
        if (!in_array($pedido['status'], ['pedido', 'a_caminho', 'validado'], true)) throw new RuntimeException('O recebimento só pode ser registrado em pedidos validados, pedidos ou a caminho.');
        $stmt = $conn->prepare('SELECT id, descricao, quantidade, recebido FROM pedido_pecas_itens WHERE pedido_id = :id ORDER BY id');
        $stmt->execute([':id' => $id]);
        $itens = $stmt->fetchAll();
        if (!$itens) throw new RuntimeException('Este pedido não possui itens.');
        $quantidades = $_POST['qtd'] ?? [];
        $recebidos_no_pedido = $_POST['recebido_no_pedido'] ?? false;
        $conn->beginTransaction();
        $todos_recebidos = true;
        $recebeu_algum = false;
        foreach ($itens as $item) {
            $chave = (string) $item['id'];
            $novo = $recebidos_no_pedido ? ((int) $item['quantidade'] - (int) $item['recebido']) : (isset($quantidades[$chave]) ? (int) $quantidades[$chave] : 0);
            if ($novo < 0 || $novo > ((int) $item['quantidade'] - (int) $item['recebido'])) {
                throw new RuntimeException('Quantidade de recebimento inválida para o item "' . $item['descricao'] . '".');
            }
            if ($novo > 0) {
                $recebeu_algum = true;
                $upd = $conn->prepare('UPDATE pedido_pecas_itens SET recebido = recebido + :qty, recebido_em = NOW() WHERE id = :item_id');
                $upd->execute([':qty' => $novo, ':item_id' => $item['id']]);
            }
            if (((int) $item['recebido'] + $novo) < (int) $item['quantidade']) {
                $todos_recebidos = false;
            }
        }
        if (!$recebeu_algum && !$todos_recebidos) throw new RuntimeException('Informe ao menos uma quantidade recebida.');
        if ($todos_recebidos) {
            $upd = $conn->prepare("UPDATE pedidos_pecas SET status = 'recebido', recebido_por = :usuario, recebido_em = NOW() WHERE id = :id");
            $upd->execute([':usuario' => $_SESSION['usuario_id'], ':id' => $id]);
            $stOs = $conn->prepare('SELECT status FROM ordens_servico WHERE id = :id');
            $stOs->execute([':id' => $pedido['os_id']]);
            $os_status_anterior = (string) $stOs->fetchColumn();
            $stmt = $conn->prepare("UPDATE ordens_servico SET status = IF(status = 'aguardando_peca', 'pronta_para_execucao', status) WHERE id = :id");
            $stmt->execute([':id' => $pedido['os_id']]);
            registrar_evento_status_os($conn, (int) $pedido['os_id'], $os_status_anterior, (int) $_SESSION['usuario_id']);
            $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_status', 'Todos os itens do pedido foram recebidos', :usuario)");
            $stmt->execute([':os_id' => $pedido['os_id'], ':usuario' => $_SESSION['usuario_id']]);
            $stmt = $conn->prepare('SELECT usuario_id FROM os_responsaveis WHERE os_id = :os_id AND concluido_em IS NULL');
            $stmt->execute([':os_id' => $pedido['os_id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $usuario_id) {
                $notif = $conn->prepare("INSERT INTO notificacoes (usuario_id, os_id, tipo, mensagem) VALUES (:usuario_id, :os_id, 'peca_disponivel', 'Todas as peças do pedido #" . $id . " chegaram e estão disponíveis.')");
                $notif->execute([':usuario_id' => $usuario_id, ':os_id' => $pedido['os_id']]);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_recebimento_parcial', 'Recebimento parcial de peças registrado', :usuario)");
            $stmt->execute([':os_id' => $pedido['os_id'], ':usuario' => $_SESSION['usuario_id']]);
        }
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'receber', 'pedido_peca', $id, $todos_recebidos ? 'Recebimento total do pedido' : 'Recebimento parcial do pedido', ['recebido_total' => $todos_recebidos]);
        $conn->commit();
        $_SESSION['mensagem'] = $todos_recebidos ? 'Todos os itens foram recebidos.' : 'Recebimento parcial registrado.';
        $_SESSION['mensagem_tipo'] = 'success';
        header('Location: editar.php?id=' . $id);
        exit();
    }
    header('Location: listar.php');
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log($e->getMessage());
    $_SESSION['mensagem'] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível processar o pedido.';
    $_SESSION['mensagem_tipo'] = 'danger';
    header('Location: listar.php');
    exit();
}
