<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: listar.php'); exit(); }
validar_csrf();
$acao = $_GET['acao'] ?? '';
try {
    if ($acao === 'cadastrar') {
        $os_id = (int) ($_POST['os_id'] ?? 0);
        $peca_id = (int) ($_POST['peca_id'] ?? 0) ?: null;
        $descricao = limpar($_POST['descricao'] ?? '');
        $quantidade = (int) ($_POST['quantidade'] ?? 0);
        $fornecedor = limpar($_POST['fornecedor'] ?? '');
        $previsao = $_POST['previsao_entrega'] ?? null;
        $observacoes = limpar($_POST['observacoes'] ?? '');
        if ($os_id <= 0 || $descricao === '' || $quantidade <= 0) { throw new RuntimeException('Informe a OS, a descrição e uma quantidade válida.'); }
        $stmt = $conn->prepare("SELECT id FROM ordens_servico WHERE id = :id AND status NOT IN ('entregue', 'cancelada')");
        $stmt->execute([':id' => $os_id]);
        if (!$stmt->fetchColumn()) { throw new RuntimeException('A OS não está disponível para pedido.'); }
        $conn->beginTransaction();
        $stmt = $conn->prepare('INSERT INTO pedidos_pecas (os_id, fornecedor, previsao_entrega, observacoes, criado_por) VALUES (:os_id, :fornecedor, :previsao, :observacoes, :usuario)');
        $stmt->execute([':os_id' => $os_id, ':fornecedor' => $fornecedor ?: null, ':previsao' => $previsao ?: null, ':observacoes' => $observacoes ?: null, ':usuario' => $_SESSION['usuario_id']]);
        $pedido_id = (int) $conn->lastInsertId();
        $stmt = $conn->prepare('INSERT INTO pedido_pecas_itens (pedido_id, peca_id, descricao, quantidade) VALUES (:pedido, :peca, :descricao, :quantidade)');
        $stmt->execute([':pedido' => $pedido_id, ':peca' => $peca_id, ':descricao' => $descricao, ':quantidade' => $quantidade]);
        $stmt = $conn->prepare("UPDATE ordens_servico SET status = IF(status IN ('recebida', 'triagem', 'aguardando_diagnostico', 'aguardando_orcamento'), 'aguardando_peca', status) WHERE id = :id");
        $stmt->execute([':id' => $os_id]);
        $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_solicitada', 'Pedido de peça criado', :usuario)");
        $stmt->execute([':os_id' => $os_id, ':usuario' => $_SESSION['usuario_id']]);
        $conn->commit();
        $_SESSION['mensagem'] = 'Pedido de peça criado.';
        $_SESSION['mensagem_tipo'] = 'success';
        header('Location: ../ordens_servico/detalhes.php?id=' . $os_id);
        exit();
    }
    if ($acao === 'atualizar') {
        exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['solicitado', 'pedido', 'a_caminho', 'recebido', 'cancelado'], true)) { throw new RuntimeException('Status inválido.'); }
        $stmt = $conn->prepare('SELECT os_id, status FROM pedidos_pecas WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $pedido = $stmt->fetch();
        if (!$pedido) { throw new RuntimeException('Pedido não encontrado.'); }
        $conn->beginTransaction();
        $stmt = $conn->prepare('UPDATE pedidos_pecas SET status = :status, fornecedor = :fornecedor, previsao_entrega = :previsao, observacoes = :observacoes, recebido_por = IF(:status = \'recebido\', :usuario, recebido_por), recebido_em = IF(:status = \'recebido\', NOW(), recebido_em) WHERE id = :id');
        $stmt->execute([':status' => $status, ':fornecedor' => limpar($_POST['fornecedor'] ?? '') ?: null, ':previsao' => ($_POST['previsao_entrega'] ?? '') ?: null, ':observacoes' => limpar($_POST['observacoes'] ?? '') ?: null, ':usuario' => $_SESSION['usuario_id'], ':id' => $id]);
        $stmt = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_status', :descricao, :usuario)");
        $stmt->execute([':os_id' => $pedido['os_id'], ':descricao' => 'Pedido de peça alterado para ' . $status, ':usuario' => $_SESSION['usuario_id']]);
        if ($status === 'recebido') {
            $stmt = $conn->prepare("UPDATE ordens_servico SET status = IF(status = 'aguardando_peca', 'pronta_para_execucao', status) WHERE id = :id");
            $stmt->execute([':id' => $pedido['os_id']]);
            $stmt = $conn->prepare('SELECT usuario_id FROM os_responsaveis WHERE os_id = :os_id AND concluido_em IS NULL');
            $stmt->execute([':os_id' => $pedido['os_id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $usuario_id) {
                $notificacao = $conn->prepare("INSERT INTO notificacoes (usuario_id, os_id, tipo, mensagem) VALUES (:usuario_id, :os_id, 'peca_disponivel', 'A peça solicitada para uma ordem de serviço está disponível.')");
                $notificacao->execute([':usuario_id' => $usuario_id, ':os_id' => $pedido['os_id']]);
            }
        }
        $conn->commit();
        $_SESSION['mensagem'] = 'Pedido atualizado.';
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
