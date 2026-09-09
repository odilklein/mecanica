<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$acao = $_GET['acao'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

try {
    if ($acao == 'cadastrar') {
        // Dados do orçamento
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
            if (!$stmt->fetchColumn()) {
                throw new Exception('A ordem de serviço não pertence ao cliente informado.');
            }
        }

        // Gerar número sequencial
        $stmt = $conn->query("SELECT COALESCE(MAX(numero), 0) + 1 as proximo FROM orcamentos");
        $numero = $stmt->fetch()['proximo'];

        // Receber itens
        $itens = $_POST['itens'] ?? [];

        if (empty($itens)) {
            $_SESSION['mensagem'] = "Adicione pelo menos um item ao orçamento.";
            $_SESSION['mensagem_tipo'] = "warning";
            header("Location: cadastrar.php");
            exit();
        }

        $conn->beginTransaction();

        $total_pecas = 0;
        $total_servicos = 0;

        // Inserir orçamento
        $stmt = $conn->prepare("INSERT INTO orcamentos (numero, cliente_id, veiculo_id, os_id, data_orcamento, data_validade, status, observacoes, criado_por) VALUES (:numero, :cliente_id, :veiculo_id, :os_id, CURDATE(), :data_validade, 'aberto', :observacoes, :criado_por)");
        $stmt->bindParam(':numero', $numero);
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':veiculo_id', $veiculo_id);
        $stmt->bindParam(':os_id', $os_id);
        $stmt->bindParam(':data_validade', $data_validade);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':criado_por', $_SESSION['usuario_id']);
        $stmt->execute();

        $orcamento_id = $conn->lastInsertId();

        // Processar itens
        foreach ($itens as $item_json) {
            $item = json_decode($item_json, true);
            if (!$item) continue;

            $tipo = $item['tipo'];
            $item_id = (int)$item['item_id'];
            $quantidade = (int)$item['quantidade'];
            $preco = (float)$item['preco'];
            $subtotal = (float)$item['subtotal'];

            $peca_id = null;
            $servico_id = null;
            $descricao_item = '';

            if ($tipo == 'peca') {
                $peca_id = $item_id;
                // Buscar nome da peça
                $stmt = $conn->prepare("SELECT nome FROM pecas WHERE id = :id");
                $stmt->bindParam(':id', $peca_id);
                $stmt->execute();
                $p = $stmt->fetch();
                $descricao_item = $p ? $p['nome'] : 'Peça';

                // Verificar estoque
                $stmt = $conn->prepare("SELECT quantidade FROM pecas WHERE id = :id FOR UPDATE");
                $stmt->bindParam(':id', $peca_id);
                $stmt->execute();
                $peca_atual = $stmt->fetch();
                if (!$peca_atual || $peca_atual['quantidade'] < $quantidade) {
                    throw new Exception("Estoque insuficiente para a peça: " . $descricao_item);
                }

                $total_pecas += $subtotal;
            } else {
                $servico_id = $item_id;
                $stmt = $conn->prepare("SELECT nome FROM servicos WHERE id = :id");
                $stmt->bindParam(':id', $servico_id);
                $stmt->execute();
                $s = $stmt->fetch();
                $descricao_item = $s ? $s['nome'] : 'Serviço';

                $total_servicos += $subtotal;
            }

            // Inserir item
            $stmt = $conn->prepare("INSERT INTO orcamento_itens (orcamento_id, tipo, peca_id, servico_id, descricao_item, quantidade, preco_unitario, subtotal) VALUES (:orcamento_id, :tipo, :peca_id, :servico_id, :descricao_item, :quantidade, :preco, :subtotal)");
            $stmt->bindParam(':orcamento_id', $orcamento_id);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':peca_id', $peca_id);
            $stmt->bindParam(':servico_id', $servico_id);
            $stmt->bindParam(':descricao_item', $descricao_item);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':preco', $preco);
            $stmt->bindParam(':subtotal', $subtotal);
            $stmt->execute();
        }

        $total_geral = $total_pecas + $total_servicos;

        // Atualizar totais do orçamento
        $stmt = $conn->prepare("UPDATE orcamentos SET total_pecas = :tp, total_mao_obra = :tm, total_geral = :tg WHERE id = :id");
        $stmt->bindParam(':tp', $total_pecas);
        $stmt->bindParam(':tm', $total_servicos);
        $stmt->bindParam(':tg', $total_geral);
        $stmt->bindParam(':id', $orcamento_id);
        $stmt->execute();

        $conn->commit();

        $_SESSION['mensagem'] = "Orçamento criado com sucesso! Nº " . str_pad($numero, 4, '0', STR_PAD_LEFT);
        $_SESSION['mensagem_tipo'] = "success";
        header("Location: detalhes.php?id=" . $orcamento_id);
        exit();
    } elseif ($acao == 'editar') {
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

        // Atualizar cabeçalho
        $stmt = $conn->prepare("UPDATE orcamentos SET cliente_id = :cliente_id, veiculo_id = :veiculo_id, data_validade = :data_validade, observacoes = :observacoes WHERE id = :id");
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':veiculo_id', $veiculo_id);
        $stmt->bindParam(':data_validade', $data_validade);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Remover itens antigos
        $stmt = $conn->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $total_pecas = 0;
        $total_servicos = 0;

        foreach ($itens as $item_json) {
            $item = json_decode($item_json, true);
            if (!$item) continue;

            $tipo = $item['tipo'];
            $item_id = (int)$item['item_id'];
            $quantidade = (int)$item['quantidade'];
            $preco = (float)$item['preco'];
            $subtotal = (float)$item['subtotal'];

            $peca_id = null;
            $servico_id = null;
            $descricao_item = '';

            if ($tipo == 'peca') {
                $peca_id = $item_id;
                $stmt = $conn->prepare("SELECT nome FROM pecas WHERE id = :id");
                $stmt->bindParam(':id', $peca_id);
                $stmt->execute();
                $p = $stmt->fetch();
                $descricao_item = $p ? $p['nome'] : 'Peça';
                $total_pecas += $subtotal;
            } else {
                $servico_id = $item_id;
                $stmt = $conn->prepare("SELECT nome FROM servicos WHERE id = :id");
                $stmt->bindParam(':id', $servico_id);
                $stmt->execute();
                $s = $stmt->fetch();
                $descricao_item = $s ? $s['nome'] : 'Serviço';
                $total_servicos += $subtotal;
            }

            $stmt = $conn->prepare("INSERT INTO orcamento_itens (orcamento_id, tipo, peca_id, servico_id, descricao_item, quantidade, preco_unitario, subtotal) VALUES (:orcamento_id, :tipo, :peca_id, :servico_id, :descricao_item, :quantidade, :preco, :subtotal)");
            $stmt->bindParam(':orcamento_id', $id);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':peca_id', $peca_id);
            $stmt->bindParam(':servico_id', $servico_id);
            $stmt->bindParam(':descricao_item', $descricao_item);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':preco', $preco);
            $stmt->bindParam(':subtotal', $subtotal);
            $stmt->execute();
        }

        $total_geral = $total_pecas + $total_servicos;

        $stmt = $conn->prepare("UPDATE orcamentos SET total_pecas = :tp, total_mao_obra = :tm, total_geral = :tg WHERE id = :id");
        $stmt->bindParam(':tp', $total_pecas);
        $stmt->bindParam(':tm', $total_servicos);
        $stmt->bindParam(':tg', $total_geral);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $conn->commit();

        $_SESSION['mensagem'] = "Orçamento atualizado com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
        header("Location: detalhes.php?id=" . $id);
        exit();
    } elseif ($acao == 'mudar_status') {
        $novo_status = $_POST['status'] ?? '';
        $status_validos = ['aberto', 'aprovado', 'reprovado', 'cancelado'];
        if (!in_array($novo_status, $status_validos)) {
            throw new Exception("Status inválido.");
        }

        $stmt = $conn->prepare("UPDATE orcamentos SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $novo_status);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensagem'] = "Status do orçamento alterado para '" . ucfirst($novo_status) . "'!";
        $_SESSION['mensagem_tipo'] = "success";
        header("Location: detalhes.php?id=" . $id);
        exit();
    } elseif ($acao == 'excluir') {
        $stmt = $conn->prepare("DELETE FROM orcamentos WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensagem'] = "Orçamento excluído com sucesso!";
        $_SESSION['mensagem_tipo'] = "success";
        header("Location: listar.php");
        exit();
    }

    header("Location: listar.php");
    exit();
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    $_SESSION['mensagem'] = "Erro ao processar: " . $e->getMessage();
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
?>
