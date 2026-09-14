<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/notificacoes.php';
require_once __DIR__ . '/../../includes/auditoria.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: listar.php'); exit(); }
validar_csrf();

$acao = $_GET['acao'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Atualizar status de uma OS vinculada a um orçamento
function atualizar_status_os_por_orcamento($conn, ?int $os_id, string $status_orc, int $usuario_id): void {
    if (!$os_id) return;
    $stmt = $conn->prepare("SELECT id, numero, status FROM ordens_servico WHERE id = :id");
    $stmt->execute([':id' => $os_id]);
    $os = $stmt->fetch();
    if (!$os) return;
    if ($status_orc === 'aprovado' && $os['status'] === 'aguardando_aprovacao') {
        $novo = 'pronta_para_execucao';
        $conn->prepare("UPDATE ordens_servico SET status = :status WHERE id = :id")
             ->execute([':status' => $novo, ':id' => $os_id]);
        $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, 'orcamento', :ant, :novo, 'Orçamento aprovado automaticamente', :u)")
             ->execute([':os_id' => $os_id, ':ant' => $os['status'], ':novo' => $novo, ':u' => $usuario_id]);
        notificar_responsaveis_os($conn, $os_id, 'os_status', 'O orçamento foi aprovado. Status da OS alterado para "' . str_replace('_', ' ', $novo) . '".', $usuario_id);
    } elseif (in_array($status_orc, ['reprovado', 'cancelado'], true) && $os['status'] === 'aguardando_aprovacao') {
        $novo = 'aguardando_orcamento';
        $conn->prepare("UPDATE ordens_servico SET status = :status WHERE id = :id")
             ->execute([':status' => $novo, ':id' => $os_id]);
        $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, 'orcamento', :ant, :novo, 'Orçamento rejeitado automaticamente', :u)")
             ->execute([':os_id' => $os_id, ':ant' => $os['status'], ':novo' => $novo, ':u' => $usuario_id]);
        notificar_responsaveis_os($conn, $os_id, 'os_status', 'O orçamento foi rejeitado. Status da OS alterado para "' . str_replace('_', ' ', $novo) . '".', $usuario_id);
    }
}

// Processar itens do orçamento com revalidação de estoque/preços
function processar_itens($conn, int $orcamento_id, array $itens_raw): array {
    $total_pecas = 0;
    $total_servicos = 0;
    foreach ($itens_raw as $item_json) {
        $item = json_decode($item_json, true);
        if (!$item) continue;
        $tipo = $item['tipo'];
        $item_id = (int)($item['item_id'] ?? 0);
        $quantidade = (int)($item['quantidade'] ?? 0);
        $preco = (float)($item['preco'] ?? 0);
        $peca_id = null;
        $servico_id = null;
        $descricao_item = '';
        if ($tipo === 'peca') {
            if ($quantidade <= 0 || $preco < 0) throw new Exception('Quantidade ou valor inválido na peça.');
            if ($item_id > 0) {
                $stmt = $conn->prepare("SELECT nome, preco_venda, quantidade FROM pecas WHERE id = :id FOR UPDATE");
                $stmt->execute([':id' => $item_id]);
                $p = $stmt->fetch();
                if (!$p) throw new Exception('Peça não encontrada.');
                $descricao_item = $p['nome'];
                $preco = (float)$p['preco_venda'];
                if ($p['quantidade'] < $quantidade) throw new Exception("Estoque insuficiente para: {$descricao_item}. Disponível: {$p['quantidade']}");
            } else {
                $descricao_item = limpar($item['descricao'] ?? '');
                if ($descricao_item === '') throw new Exception('A descrição da peça é obrigatória.');
                $preco = (float)$preco;
            }
            $subtotal = round($quantidade * $preco, 2);
            $total_pecas += $subtotal;
        } else {
            if ($item_id > 0) {
                $stmt = $conn->prepare("SELECT nome, preco_mao_obra FROM servicos WHERE id = :id AND ativo = 1");
                $stmt->execute([':id' => $item_id]);
                $s = $stmt->fetch();
                if (!$s) throw new Exception('Serviço não encontrado ou inativo.');
                $servico_id = $item_id;
                $descricao_item = $s['nome'];
                $preco = (float)$s['preco_mao_obra'];
                $quantidade = 1;
            } else {
                $descricao_item = limpar($item['descricao'] ?? '');
                if ($descricao_item === '') throw new Exception('A descrição do serviço é obrigatória.');
                $servico_id = null;
            }
            if ($quantidade <= 0 || $preco < 0) throw new Exception('Quantidade ou valor inválido no serviço.');
            $subtotal = round($quantidade * $preco, 2);
            $total_servicos += $subtotal;
        }
        $stmt = $conn->prepare("INSERT INTO orcamento_itens (orcamento_id, tipo, peca_id, servico_id, descricao_item, quantidade, preco_unitario, subtotal) VALUES (:orcamento_id, :tipo, :peca_id, :servico_id, :descricao_item, :quantidade, :preco, :subtotal)");
        $stmt->execute([':orcamento_id' => $orcamento_id, ':tipo' => $tipo, ':peca_id' => $peca_id, ':servico_id' => $servico_id, ':descricao_item' => $descricao_item, ':quantidade' => $quantidade, ':preco' => $preco, ':subtotal' => $subtotal]);
    }
    return [$total_pecas, $total_servicos];
}

try {
    if ($acao == 'cadastrar') {
        $cliente_id = (int)($_POST['cliente_id'] ?? 0);
        $veiculo_id = (int)($_POST['veiculo_id'] ?? 0);
        $os_id = (int)($_POST['os_id'] ?? 0) ?: null;
        $data_validade = $_POST['data_validade'] ?? null;
        $observacoes = limpar($_POST['observacoes'] ?? '');

        if ($cliente_id <= 0) {
            $_SESSION['mensagem'] = "Selecione o cliente.";
            $_SESSION['mensagem_tipo'] = "warning";
            header("Location: cadastrar.php");
            exit();
        }

        if ($os_id) {
            $stmt = $conn->prepare('SELECT id FROM ordens_servico WHERE id = :id AND cliente_id = :cliente_id');
            $stmt->execute([':id' => $os_id, ':cliente_id' => $cliente_id]);
            if (!$stmt->fetchColumn()) throw new Exception('A ordem de serviço não pertence ao cliente informado.');
        }

        $stmt = $conn->query("SELECT COALESCE(MAX(numero), 0) + 1 as proximo FROM orcamentos");
        $numero = $stmt->fetch()['proximo'];
        $itens = $_POST['itens'] ?? [];
        if (empty($itens)) {
            $_SESSION['mensagem'] = "Adicione pelo menos um item ao orçamento.";
            $_SESSION['mensagem_tipo'] = "warning";
            header("Location: cadastrar.php");
            exit();
        }

        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO orcamentos (numero, cliente_id, veiculo_id, os_id, data_orcamento, data_validade, status, observacoes, criado_por) VALUES (:numero, :cliente_id, :veiculo_id, :os_id, CURDATE(), :data_validade, 'aberto', :observacoes, :criado_por)");
        $stmt->execute([':numero' => $numero, ':cliente_id' => $cliente_id, ':veiculo_id' => $veiculo_id, ':os_id' => $os_id, ':data_validade' => $data_validade, ':observacoes' => $observacoes, ':criado_por' => $_SESSION['usuario_id']]);
        $orcamento_id = $conn->lastInsertId();

        [$total_pecas, $total_servicos] = processar_itens($conn, $orcamento_id, $itens);
        $total_geral = $total_pecas + $total_servicos;
        $conn->prepare("UPDATE orcamentos SET total_pecas = :tp, total_mao_obra = :tm, total_geral = :tg WHERE id = :id")
             ->execute([':tp' => $total_pecas, ':tm' => $total_servicos, ':tg' => $total_geral, ':id' => $orcamento_id]);

        if ($os_id) {
            $stmt = $conn->prepare("SELECT status FROM ordens_servico WHERE id = :id");
            $stmt->execute([':id' => $os_id]);
            $os = $stmt->fetch();
            if ($os && $os['status'] === 'aguardando_orcamento') {
                $conn->prepare("UPDATE ordens_servico SET status = 'aguardando_aprovacao' WHERE id = :id")
                     ->execute([':id' => $os_id]);
                $desc = 'Orçamento #' . str_pad((string)$numero, 4, '0', STR_PAD_LEFT) . ' vinculado a OS';
                $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, 'orcamento', 'aguardando_orcamento', 'aguardando_aprovacao', :desc, :u)")
                     ->execute([':os_id' => $os_id, ':desc' => $desc, ':u' => $_SESSION['usuario_id']]);
                notificar_responsaveis_os($conn, $os_id, 'orcamento', 'Um novo orçamento foi criado para sua ordem de serviço. Aguardando aprovação.', (int)$_SESSION['usuario_id']);
            }
        }

        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'orcamento', $orcamento_id, 'Orçamento #' . str_pad($numero, 4, '0', STR_PAD_LEFT) . ' criado', ['total_geral' => $total_geral]);
        $_SESSION['mensagem'] = "Orçamento criado com sucesso! Nº " . str_pad($numero, 4, '0', STR_PAD_LEFT);
        $_SESSION['mensagem_tipo'] = "success";
        header("Location: detalhes.php?id=" . $orcamento_id);
        exit();

    } elseif ($acao == 'editar') {
        $stmt = $conn->prepare("SELECT id, status, cliente_id, os_id FROM orcamentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $orc = $stmt->fetch();
        if (!$orc || $orc['status'] !== 'aberto') throw new Exception('Este orçamento não pode ser editado.');

        $cliente_id = (int)($_POST['cliente_id'] ?? 0);
        $veiculo_id = (int)($_POST['veiculo_id'] ?? 0);
        $data_validade = $_POST['data_validade'] ?? null;
        $observacoes = limpar($_POST['observacoes'] ?? '');
        $itens = $_POST['itens'] ?? [];
        if (empty($itens)) {
            $_SESSION['mensagem'] = "Adicione pelo menos um item ao orçamento.";
            $_SESSION['mensagem_tipo'] = "warning";
            header("Location: editar.php?id=" . $id);
            exit();
        }

        $conn->beginTransaction();
        $conn->prepare("UPDATE orcamentos SET cliente_id = :c, veiculo_id = :v, data_validade = :d, observacoes = :o WHERE id = :id")
             ->execute([':c' => $cliente_id, ':v' => $veiculo_id, ':d' => $data_validade, ':o' => $observacoes, ':id' => $id]);
        $conn->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = :id")->execute([':id' => $id]);

        [$total_pecas, $total_servicos] = processar_itens($conn, $id, $itens);
        $total_geral = $total_pecas + $total_servicos;
        $conn->prepare("UPDATE orcamentos SET total_pecas = :tp, total_mao_obra = :tm, total_geral = :tg WHERE id = :id")
             ->execute([':tp' => $total_pecas, ':tm' => $total_servicos, ':tg' => $total_geral, ':id' => $id]);
        $conn->commit();

        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'orcamento', $id, 'Orçamento atualizado');
        $_SESSION['mensagem'] = "Orçamento atualizado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
        header("Location: detalhes.php?id=" . $id);
        exit();

    } elseif ($acao == 'mudar_status') {
        $novo_status = $_POST['status'] ?? '';
        $status_validos = ['aberto', 'aprovado', 'reprovado', 'cancelado'];
        if (!in_array($novo_status, $status_validos)) throw new Exception("Status inválido.");

        $stmt = $conn->prepare("UPDATE orcamentos SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $novo_status, ':id' => $id]);

        $stmt = $conn->prepare("SELECT os_id, numero FROM orcamentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $orc_info = $stmt->fetch();
        $os_id_orc = $orc_info && $orc_info['os_id'] ? (int)$orc_info['os_id'] : null;

        $rotulos = ['aberto' => 'Aberto', 'aprovado' => 'Aprovado', 'reprovado' => 'Reprovado', 'cancelado' => 'Cancelado'];
        if ($orc_info) notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id_orc, 'orcamento', 'O orçamento #' . str_pad((string)$orc_info['numero'], 4, '0', STR_PAD_LEFT) . ' teve o status alterado para "' . ($rotulos[$novo_status] ?? $novo_status) . '".', (int)$_SESSION['usuario_id']);

        atualizar_status_os_por_orcamento($conn, $os_id_orc, $novo_status, (int)$_SESSION['usuario_id']);

        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'status', 'orcamento', $id, 'Orçamento alterado para ' . $novo_status, ['status' => $novo_status]);
        $_SESSION['mensagem'] = "Status do orçamento alterado para '" . ucfirst($novo_status) . "'!";
        $_SESSION['mensagem_tipo'] = "success";
        header("Location: detalhes.php?id=" . $id);
        exit();

    } elseif ($acao == 'excluir') {
        $stmt = $conn->prepare("SELECT id, status, os_id FROM orcamentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $orc = $stmt->fetch();
        if (!$orc) throw new Exception("Orçamento não encontrado.");
        if ($orc['status'] !== 'aberto') throw new Exception("Apenas orçamentos abertos podem ser excluídos.");

        $conn->beginTransaction();
        $conn->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = :id")->execute([':id' => $id]);
        $conn->prepare("DELETE FROM orcamentos WHERE id = :id")->execute([':id' => $id]);

        if ($orc['os_id']) {
            $stmt = $conn->prepare("SELECT status FROM ordens_servico WHERE id = :id");
            $stmt->execute([':id' => $orc['os_id']]);
            $os = $stmt->fetch();
            if ($os && $os['status'] === 'aguardando_aprovacao') {
                $conn->prepare("UPDATE ordens_servico SET status = 'aguardando_orcamento' WHERE id = :id")->execute([':id' => $orc['os_id']]);
                $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, 'orcamento', 'aguardando_aprovacao', 'aguardando_orcamento', 'Orçamento excluído', :u)")
                     ->execute([':os_id' => $orc['os_id'], ':u' => $_SESSION['usuario_id']]);
            }
        }

        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'excluir', 'orcamento', $id, 'Orçamento excluído');
        $_SESSION['mensagem'] = "Orçamento excluído com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
        header("Location: listar.php");
        exit();
    }

    header("Location: listar.php");
    exit();
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    if ($e instanceof PDOException) {
        error_log($e->getMessage());
        $_SESSION['mensagem'] = "Não foi possível salvar o orçamento.";
    } else {
        $_SESSION['mensagem'] = $e->getMessage();
    }
    $_SESSION['mensagem_tipo'] = "danger";
    if ($acao == 'editar') {
        header("Location: editar.php?id=" . $id);
    } elseif ($acao == 'cadastrar') {
        header("Location: cadastrar.php");
    } else {
        header("Location: listar.php");
    }
    exit();
}
