<?php
// =============================================
// API DE PEDIDOS DE PEÇAS (JSON)
// -------------------------------------------------
// Consumida por js/crud.js + js/pedidos_pecas.js.
// Ações: listar, obter, criar, atualizar, receber.
// Mantém as regras de transição de status e o
// recebimento parcial/total com eventos e notificações.
// =============================================

require_once dirname(__DIR__) . '/includes/sessao.php';
require_once dirname(__DIR__) . '/config/conexao.php';
require_once dirname(__DIR__) . '/includes/auditoria.php';
require_once dirname(__DIR__) . '/includes/notificacoes.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('pp_api_saida')) {
    function pp_api_saida(array $dados, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

$status_nomes = [
    'solicitado' => 'Aguardando validação',
    'validado' => 'Validado',
    'pedido' => 'Pedido realizado',
    'a_caminho' => 'A caminho',
    'recebido' => 'Recebido',
    'cancelado' => 'Cancelado',
];

if (!function_exists('pp_api_pedido')) {
    function pp_api_pedido(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'os_id' => (int) $linha['os_id'],
            'os_numero' => (int) ($linha['numero'] ?? 0),
            'cliente' => (string) ($linha['cliente'] ?? ''),
            'modelo' => (string) ($linha['modelo'] ?? ''),
            'placa' => (string) ($linha['placa'] ?? ''),
            'status' => (string) $linha['status'],
            'fornecedor' => (string) ($linha['fornecedor'] ?? ''),
            'previsao_entrega' => (string) ($linha['previsao_entrega'] ?? ''),
            'itens' => (string) ($linha['itens'] ?? ''),
            'criado_em' => (string) ($linha['criado_em'] ?? ''),
            'observacoes' => (string) ($linha['observacoes'] ?? ''),
        ];
    }
}

if (!isset($_SESSION['usuario_id'])) {
    pp_api_saida(['ok' => false, 'erro' => 'Não autenticado.'], 401);
}
$papeis_permitidos = ['admin', 'secretaria', 'mecanico_chefe', 'mecanico'];
if (!in_array($_SESSION['usuario_tipo'] ?? '', $papeis_permitidos, true)) {
    pp_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
}

$eh_mecanico = ($_SESSION['usuario_tipo'] ?? '') === 'mecanico';
$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

if ($metodo === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        pp_api_saida(['ok' => false, 'erro' => 'Requisição inválida.'], 403);
    }
}

if (!function_exists('pp_api_notificar_responsaveis')) {
    function pp_api_notificar_responsaveis(PDO $conn, int $os_id, string $tipo, string $mensagem): void
    {
        $st = $conn->prepare('SELECT usuario_id FROM os_responsaveis WHERE os_id = :os_id AND concluido_em IS NULL');
        $st->execute([':os_id' => $os_id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $n = $conn->prepare('INSERT INTO notificacoes (usuario_id, os_id, tipo, mensagem) VALUES (:uid, :os_id, :tipo, :msg)');
            $n->execute([':uid' => $uid, ':os_id' => $os_id, ':tipo' => $tipo, ':msg' => $mensagem]);
        }
    }
}

try {
    // =========================================
    // READ - listagem paginada
    // =========================================
    if ($acao === 'listar') {
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $por_pagina = (int) ($_GET['por_pagina'] ?? 15);
        if (!in_array($por_pagina, [10, 15, 25, 50], true)) {
            $por_pagina = 15;
        }

        $from = 'FROM pedidos_pecas p
            JOIN ordens_servico os ON os.id = p.os_id
            JOIN clientes c ON c.id = os.cliente_id
            LEFT JOIN veiculos v ON v.id = os.veiculo_id';

        $condicoes = [];
        $params = [];
        if ($eh_mecanico) {
            $condicoes[] = 'EXISTS (SELECT 1 FROM os_responsaveis r WHERE r.os_id = os.id AND r.usuario_id = :uid_scope AND r.concluido_em IS NULL)';
            $condicoes[] = "(p.criado_por = :uid_criado OR p.status IN ('validado', 'pedido', 'a_caminho', 'recebido'))";
            $params[':uid_scope'] = (int) $_SESSION['usuario_id'];
            $params[':uid_criado'] = (int) $_SESSION['usuario_id'];
        }
        if ($busca !== '') {
            $condicoes[] = '(p.id LIKE :busca OR CAST(os.numero AS CHAR) LIKE :busca OR c.nome LIKE :busca OR p.fornecedor LIKE :busca
                OR EXISTS (SELECT 1 FROM pedido_pecas_itens pi WHERE pi.pedido_id = p.id AND pi.descricao LIKE :busca))';
            $params[':busca'] = '%' . $busca . '%';
        }
        if (array_key_exists($status, $status_nomes)) {
            $condicoes[] = 'p.status = :status';
            $params[':status'] = $status;
        }
        $where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

        $st = $conn->prepare("SELECT COUNT(*) $from $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $total_paginas = max(1, (int) ceil($total / $por_pagina));
        $pagina = min($pagina, $total_paginas);
        $offset = ($pagina - 1) * $por_pagina;

        $st = $conn->prepare("SELECT p.id, p.os_id, p.status, p.fornecedor, p.previsao_entrega, p.criado_em, p.observacoes,
                os.numero, c.nome AS cliente, v.modelo, v.placa,
                GROUP_CONCAT(pi.descricao SEPARATOR ', ') AS itens
            $from
            LEFT JOIN pedido_pecas_itens pi ON pi.pedido_id = p.id
            $where
            GROUP BY p.id
            ORDER BY p.criado_em DESC
            LIMIT :limite OFFSET :offset");
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        pp_api_saida([
            'ok' => true,
            'itens' => array_map('pp_api_pedido', $st->fetchAll()),
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total' => $total,
            'total_paginas' => $total_paginas,
        ]);
    }

    // =========================================
    // READ - um pedido (com itens)
    // =========================================
    if ($acao === 'obter') {
        $id = (int) ($_GET['id'] ?? 0);
        $st = $conn->prepare("SELECT p.*, os.numero, c.nome AS cliente FROM pedidos_pecas p
            JOIN ordens_servico os ON os.id = p.os_id JOIN clientes c ON c.id = os.cliente_id WHERE p.id = :id");
        $st->execute([':id' => $id]);
        $linha = $st->fetch();
        if (!$linha) {
            pp_api_saida(['ok' => false, 'erro' => 'Pedido não encontrado.'], 404);
        }
        $itens = $conn->prepare('SELECT id, descricao, quantidade, recebido FROM pedido_pecas_itens WHERE pedido_id = :id ORDER BY id');
        $itens->execute([':id' => $id]);
        $pedido = pp_api_pedido($linha);
        $pedido['itens_lista'] = $itens->fetchAll();
        pp_api_saida(['ok' => true, 'item' => $pedido]);
    }

    // =========================================
    // CREATE - solicitar pedido
    // =========================================
    if ($acao === 'criar') {
        $os_id = (int) ($_POST['os_id'] ?? 0);
        $descricao = limpar($_POST['descricao'] ?? '');
        $quantidade = (int) ($_POST['quantidade'] ?? 0);
        $fornecedor = $eh_mecanico ? null : (limpar($_POST['fornecedor'] ?? '') ?: null);
        $previsao = ($_POST['previsao_entrega'] ?? '') ?: null;
        $observacoes = limpar($_POST['observacoes'] ?? '');

        if ($os_id <= 0 || $descricao === '' || $quantidade <= 0) {
            pp_api_saida(['ok' => false, 'erro' => 'Informe a OS, a descrição e uma quantidade válida.'], 422);
        }

        $st = $conn->prepare("SELECT id, numero FROM ordens_servico WHERE id = :id AND status NOT IN ('entregue', 'cancelada')");
        $st->execute([':id' => $os_id]);
        $os_pedido = $st->fetch();
        if (!$os_pedido) {
            pp_api_saida(['ok' => false, 'erro' => 'A OS não está disponível para pedido.'], 422);
        }
        if ($eh_mecanico) {
            $st = $conn->prepare('SELECT 1 FROM os_responsaveis WHERE os_id = :os_id AND usuario_id = :uid AND concluido_em IS NULL');
            $st->execute([':os_id' => $os_id, ':uid' => $_SESSION['usuario_id']]);
            if (!$st->fetchColumn()) {
                pp_api_saida(['ok' => false, 'erro' => 'Você só pode solicitar peças para OS atribuídas a você.'], 403);
            }
        }

        $conn->beginTransaction();
        $st = $conn->prepare("INSERT INTO pedidos_pecas (os_id, fornecedor, status, previsao_entrega, observacoes, criado_por) VALUES (:os_id, :fornecedor, 'solicitado', :previsao, :obs, :usuario)");
        $st->execute([':os_id' => $os_id, ':fornecedor' => $fornecedor, ':previsao' => $previsao, ':obs' => $observacoes ?: null, ':usuario' => $_SESSION['usuario_id']]);
        $pedido_id = (int) $conn->lastInsertId();
        $st = $conn->prepare('INSERT INTO pedido_pecas_itens (pedido_id, descricao, quantidade) VALUES (:pedido, :descricao, :quantidade)');
        $st->execute([':pedido' => $pedido_id, ':descricao' => $descricao, ':quantidade' => $quantidade]);
        $st = $conn->prepare("UPDATE ordens_servico SET status = IF(status IN ('recebida', 'em_analise'), 'aguardando_peca', status) WHERE id = :id");
        $st->execute([':id' => $os_id]);
        $st = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_solicitada', 'Pedido de peça criado', :usuario)");
        $st->execute([':os_id' => $os_id, ':usuario' => $_SESSION['usuario_id']]);
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'peca_pedido', 'Pedido de peça criado para a OS #' . str_pad((string) $os_pedido['numero'], 4, '0', STR_PAD_LEFT) . '.', (int) $_SESSION['usuario_id']);
        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'pedido_peca', $pedido_id, 'Pedido de peça criado para a OS #' . $os_pedido['numero']);
        pp_api_saida(['ok' => true, 'mensagem' => 'Pedido de peça criado.', 'id' => $pedido_id]);
    }

    // =========================================
    // UPDATE - transição de status / dados
    // =========================================
    if ($acao === 'atualizar') {
        if ($eh_mecanico) {
            pp_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
        }
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!array_key_exists($status, $status_nomes)) {
            pp_api_saida(['ok' => false, 'erro' => 'Status inválido.'], 422);
        }
        $st = $conn->prepare('SELECT os_id, status FROM pedidos_pecas WHERE id = :id');
        $st->execute([':id' => $id]);
        $pedido = $st->fetch();
        if (!$pedido) {
            pp_api_saida(['ok' => false, 'erro' => 'Pedido não encontrado.'], 404);
        }
        $transicoes = ['solicitado' => ['validado', 'cancelado'], 'validado' => ['pedido', 'cancelado'], 'pedido' => ['a_caminho', 'cancelado'], 'a_caminho' => ['recebido'], 'recebido' => [], 'cancelado' => []];
        if ($status !== $pedido['status'] && !in_array($status, $transicoes[$pedido['status']] ?? [], true)) {
            pp_api_saida(['ok' => false, 'erro' => 'Essa alteração não é permitida para o status atual.'], 422);
        }
        if ($status === 'pedido' && !tem_papel(['admin', 'secretaria'])) {
            pp_api_saida(['ok' => false, 'erro' => 'Somente a secretaria pode confirmar o pedido ao fornecedor.'], 403);
        }

        $conn->beginTransaction();
        $st = $conn->prepare("UPDATE pedidos_pecas SET status = :status, fornecedor = :fornecedor, previsao_entrega = :previsao, observacoes = :obs,
            recebido_por = IF(:status = 'recebido', :usuario, recebido_por), recebido_em = IF(:status = 'recebido', NOW(), recebido_em) WHERE id = :id");
        $st->execute([
            ':status' => $status,
            ':fornecedor' => limpar($_POST['fornecedor'] ?? '') ?: null,
            ':previsao' => ($_POST['previsao_entrega'] ?? '') ?: null,
            ':obs' => limpar($_POST['observacoes'] ?? '') ?: null,
            ':usuario' => $_SESSION['usuario_id'],
            ':id' => $id,
        ]);
        $st = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_status', :descricao, :usuario)");
        $st->execute([':os_id' => $pedido['os_id'], ':descricao' => 'Pedido de peça alterado para ' . $status, ':usuario' => $_SESSION['usuario_id']]);

        if ($status === 'recebido') {
            $st = $conn->prepare('UPDATE pedido_pecas_itens SET recebido = quantidade, recebido_em = NOW() WHERE pedido_id = :id AND recebido < quantidade');
            $st->execute([':id' => $id]);
            $st = $conn->prepare("UPDATE ordens_servico SET status = IF(status = 'aguardando_peca', 'em_execucao', status) WHERE id = :id");
            $st->execute([':id' => $pedido['os_id']]);
            pp_api_notificar_responsaveis($conn, (int) $pedido['os_id'], 'peca_disponivel', 'A peça solicitada para uma ordem de serviço está disponível.');
        }
        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'status', 'pedido_peca', $id, 'Pedido de peça alterado para ' . $status, ['status_anterior' => $pedido['status'], 'status_novo' => $status]);
        pp_api_saida(['ok' => true, 'mensagem' => 'Pedido atualizado.']);
    }

    // =========================================
    // RECEBER - itens (parcial/total)
    // =========================================
    if ($acao === 'receber') {
        if ($eh_mecanico) {
            pp_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
        }
        $id = (int) ($_POST['id'] ?? 0);
        $st = $conn->prepare('SELECT p.id, p.os_id, p.status, os.numero FROM pedidos_pecas p JOIN ordens_servico os ON os.id = p.os_id WHERE p.id = :id');
        $st->execute([':id' => $id]);
        $pedido = $st->fetch();
        if (!$pedido) {
            pp_api_saida(['ok' => false, 'erro' => 'Pedido não encontrado.'], 404);
        }
        if (!in_array($pedido['status'], ['pedido', 'a_caminho', 'validado'], true)) {
            pp_api_saida(['ok' => false, 'erro' => 'O recebimento só pode ser registrado em pedidos validados, pedidos ou a caminho.'], 422);
        }
        $st = $conn->prepare('SELECT id, descricao, quantidade, recebido FROM pedido_pecas_itens WHERE pedido_id = :id ORDER BY id');
        $st->execute([':id' => $id]);
        $itens = $st->fetchAll();
        if (!$itens) {
            pp_api_saida(['ok' => false, 'erro' => 'Este pedido não possui itens.'], 422);
        }

        $quantidades = $_POST['qtd'] ?? [];
        $receber_todos = !empty($_POST['recebido_no_pedido']);
        $conn->beginTransaction();
        $todos_recebidos = true;
        $recebeu_algum = false;
        foreach ($itens as $item) {
            $chave = (string) $item['id'];
            $pendencias = (int) $item['quantidade'] - (int) $item['recebido'];
            $novo = $receber_todos ? $pendencias : (isset($quantidades[$chave]) ? (int) $quantidades[$chave] : 0);
            if ($novo < 0 || $novo > $pendencias) {
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
        if (!$recebeu_algum && !$todos_recebidos) {
            throw new RuntimeException('Informe ao menos uma quantidade recebida.');
        }
        if ($todos_recebidos) {
            $upd = $conn->prepare("UPDATE pedidos_pecas SET status = 'recebido', recebido_por = :usuario, recebido_em = NOW() WHERE id = :id");
            $upd->execute([':usuario' => $_SESSION['usuario_id'], ':id' => $id]);
            $st = $conn->prepare("UPDATE ordens_servico SET status = IF(status = 'aguardando_peca', 'em_execucao', status) WHERE id = :id");
            $st->execute([':id' => $pedido['os_id']]);
            $st = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_status', 'Todos os itens do pedido foram recebidos', :usuario)");
            $st->execute([':os_id' => $pedido['os_id'], ':usuario' => $_SESSION['usuario_id']]);
            pp_api_notificar_responsaveis($conn, (int) $pedido['os_id'], 'peca_disponivel', 'Todas as peças do pedido #' . $id . ' chegaram e estão disponíveis.');
        } else {
            $st = $conn->prepare("INSERT INTO os_eventos (os_id, tipo, descricao, criado_por) VALUES (:os_id, 'peca_recebimento_parcial', 'Recebimento parcial de peças registrado', :usuario)");
            $st->execute([':os_id' => $pedido['os_id'], ':usuario' => $_SESSION['usuario_id']]);
        }
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'receber', 'pedido_peca', $id, $todos_recebidos ? 'Recebimento total do pedido' : 'Recebimento parcial do pedido', ['recebido_total' => $todos_recebidos]);
        $conn->commit();
        pp_api_saida(['ok' => true, 'mensagem' => $todos_recebidos ? 'Todos os itens foram recebidos.' : 'Recebimento parcial registrado.']);
    }

    pp_api_saida(['ok' => false, 'erro' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('API pedidos_pecas: ' . $e->getMessage());
    $status = $e instanceof RuntimeException ? 422 : 500;
    pp_api_saida(['ok' => false, 'erro' => $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.'], $status);
}
