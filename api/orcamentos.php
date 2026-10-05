<?php
// =============================================
// API DE ORÇAMENTOS (JSON)
// -------------------------------------------------
// Ações: listar, obter, salvar (criar/editar),
// mudar_status, excluir, excluir_lote.
// Mantém a sincronização do status da OS vinculada.
// =============================================

require_once dirname(__DIR__) . '/includes/sessao.php';
require_once dirname(__DIR__) . '/config/conexao.php';
require_once dirname(__DIR__) . '/includes/notificacoes.php';
require_once dirname(__DIR__) . '/includes/auditoria.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('or_api_saida')) {
    function or_api_saida(array $dados, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if (!function_exists('or_api_atualizar_status_os')) {
    // Espelha o status do orçamento no status da OS vinculada.
    function or_api_atualizar_status_os(PDO $conn, ?int $os_id, string $status_orc, int $usuario_id): void
    {
        if (!$os_id) {
            return;
        }
        $st = $conn->prepare("SELECT id, numero, status FROM ordens_servico WHERE id = :id");
        $st->execute([':id' => $os_id]);
        $os = $st->fetch();
        if (!$os) {
            return;
        }
        if ($status_orc === 'aprovado' && $os['status'] === 'aguardando_aprovacao') {
            $novo = 'em_execucao';
            $conn->prepare("UPDATE ordens_servico SET status = :status WHERE id = :id")->execute([':status' => $novo, ':id' => $os_id]);
            $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, 'orcamento', :ant, :novo, 'Orçamento aprovado automaticamente', :u)")
                ->execute([':os_id' => $os_id, ':ant' => $os['status'], ':novo' => $novo, ':u' => $usuario_id]);
            notificar_responsaveis_os($conn, $os_id, 'os_status', 'O orçamento foi aprovado. Status da OS alterado para "' . str_replace('_', ' ', $novo) . '".', $usuario_id);
        } elseif (in_array($status_orc, ['reprovado', 'cancelado'], true) && $os['status'] === 'aguardando_aprovacao') {
            $novo = 'em_analise';
            $conn->prepare("UPDATE ordens_servico SET status = :status WHERE id = :id")->execute([':status' => $novo, ':id' => $os_id]);
            $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, 'orcamento', :ant, :novo, 'Orçamento rejeitado automaticamente', :u)")
                ->execute([':os_id' => $os_id, ':ant' => $os['status'], ':novo' => $novo, ':u' => $usuario_id]);
            notificar_responsaveis_os($conn, $os_id, 'os_status', 'O orçamento foi rejeitado. Status da OS alterado para "' . str_replace('_', ' ', $novo) . '".', $usuario_id);
        }
    }
}

if (!function_exists('or_api_processar_itens')) {
    // Grava os itens e devolve [total_pecas, total_servicos].
    function or_api_processar_itens(PDO $conn, int $orcamento_id, array $itens_raw): array
    {
        $total_pecas = 0.0;
        $total_servicos = 0.0;
        foreach ($itens_raw as $item_json) {
            $item = is_array($item_json) ? $item_json : json_decode((string) $item_json, true);
            if (!$item) {
                continue;
            }
            $tipo = ($item['tipo'] ?? '') === 'servico' ? 'servico' : 'peca';
            $descricao_item = limpar($item['descricao'] ?? '');
            $quantidade = (int) ($item['quantidade'] ?? 0);
            $preco = (float) ($item['preco'] ?? 0);
            if ($descricao_item === '') {
                throw new RuntimeException('A descrição do item é obrigatória.');
            }
            if ($quantidade <= 0 || $preco < 0) {
                throw new RuntimeException('Quantidade ou valor inválido no item.');
            }
            $subtotal = round($quantidade * $preco, 2);
            if ($tipo === 'peca') {
                $total_pecas += $subtotal;
            } else {
                $total_servicos += $subtotal;
            }
            $st = $conn->prepare("INSERT INTO orcamento_itens (orcamento_id, tipo, descricao_item, quantidade, preco_unitario, subtotal) VALUES (:oid, :tipo, :desc, :qtd, :preco, :subtotal)");
            $st->execute([':oid' => $orcamento_id, ':tipo' => $tipo, ':desc' => $descricao_item, ':qtd' => $quantidade, ':preco' => $preco, ':subtotal' => $subtotal]);
        }
        return [$total_pecas, $total_servicos];
    }
}

if (!isset($_SESSION['usuario_id'])) {
    or_api_saida(['ok' => false, 'erro' => 'Não autenticado.'], 401);
}
$papeis_permitidos = ['admin', 'secretaria', 'mecanico_chefe'];
if (!in_array($_SESSION['usuario_tipo'] ?? '', $papeis_permitidos, true)) {
    or_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
}

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

if ($metodo === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        or_api_saida(['ok' => false, 'erro' => 'Requisição inválida.'], 403);
    }
}

// A lista de itens pode chegar como JSON no corpo (editor envia JSON).
$corpo_json = null;
if ($metodo === 'POST' && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $corpo_json = json_decode(file_get_contents('php://input'), true) ?: [];
    $acao = $corpo_json['acao'] ?? $acao;
}

if (!function_exists('or_api_entrada')) {
    function or_api_entrada(?array $json, string $chave, $padrao = null)
    {
        if ($json !== null && array_key_exists($chave, $json)) {
            return $json[$chave];
        }
        return $_POST[$chave] ?? $padrao;
    }
}

try {
    // =========================================
    // READ - listagem
    // =========================================
    if ($acao === 'listar') {
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $periodo = trim((string) ($_GET['periodo'] ?? ''));
        $status_validos = ['aberto', 'aprovado', 'reprovado', 'cancelado'];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $por_pagina = (int) ($_GET['por_pagina'] ?? 15);
        if (!in_array($por_pagina, [10, 15, 25, 50], true)) {
            $por_pagina = 15;
        }

        $condicoes = [];
        $params = [];
        if ($busca !== '') {
            $condicoes[] = '(CAST(o.numero AS CHAR) LIKE :busca OR c.nome LIKE :busca OR v.marca LIKE :busca OR v.modelo LIKE :busca OR v.placa LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }
        if (in_array($status, $status_validos, true)) {
            $condicoes[] = 'o.status = :status';
            $params[':status'] = $status;
        }
        if ($periodo === 'hoje') {
            $condicoes[] = 'DATE(o.data_orcamento) = CURDATE()';
        } elseif ($periodo === 'ultimos7') {
            $condicoes[] = 'o.data_orcamento >= (CURDATE() - INTERVAL 6 DAY)';
        } elseif ($periodo === 'ultimos30') {
            $condicoes[] = 'o.data_orcamento >= (CURDATE() - INTERVAL 29 DAY)';
        }
        $where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

        $st = $conn->prepare("SELECT COUNT(*) FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id LEFT JOIN veiculos v ON v.id = o.veiculo_id $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $total_paginas = max(1, (int) ceil($total / $por_pagina));
        $pagina = min($pagina, $total_paginas);
        $offset = ($pagina - 1) * $por_pagina;

        $st = $conn->prepare("SELECT o.*, c.nome AS cliente_nome, v.marca, v.modelo, v.placa FROM orcamentos o
            LEFT JOIN clientes c ON c.id = o.cliente_id LEFT JOIN veiculos v ON v.id = o.veiculo_id
            $where ORDER BY o.id DESC LIMIT :limite OFFSET :offset");
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        $itens = array_map(function ($o) {
            $expirado = !empty($o['data_validade']) && strtotime($o['data_validade']) < strtotime(date('Y-m-d')) && $o['status'] === 'aberto';
            return [
                'id' => (int) $o['id'],
                'numero' => (int) $o['numero'],
                'cliente_nome' => (string) ($o['cliente_nome'] ?? ''),
                'marca' => (string) ($o['marca'] ?? ''),
                'modelo' => (string) ($o['modelo'] ?? ''),
                'placa' => (string) ($o['placa'] ?? ''),
                'data_orcamento' => (string) $o['data_orcamento'],
                'data_validade' => (string) ($o['data_validade'] ?? ''),
                'total_geral' => (float) $o['total_geral'],
                'status' => (string) $o['status'],
                'expirado' => $expirado,
            ];
        }, $st->fetchAll());

        or_api_saida([
            'ok' => true,
            'itens' => $itens,
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total' => $total,
            'total_paginas' => $total_paginas,
        ]);
    }

    // =========================================
    // READ - um orçamento (com itens)
    // =========================================
    if ($acao === 'obter') {
        $id = (int) ($_GET['id'] ?? 0);
        $st = $conn->prepare("SELECT o.*, c.nome AS cliente_nome FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id WHERE o.id = :id");
        $st->execute([':id' => $id]);
        $o = $st->fetch();
        if (!$o) {
            or_api_saida(['ok' => false, 'erro' => 'Orçamento não encontrado.'], 404);
        }
        $it = $conn->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = :id ORDER BY id');
        $it->execute([':id' => $id]);
        $o['itens'] = $it->fetchAll();
        or_api_saida(['ok' => true, 'item' => $o]);
    }

    // =========================================
    // CREATE / UPDATE
    // =========================================
    if ($acao === 'salvar') {
        $id = (int) or_api_entrada($corpo_json, 'id', 0);
        $cliente_id = (int) or_api_entrada($corpo_json, 'cliente_id', 0);
        $veiculo_id = (int) or_api_entrada($corpo_json, 'veiculo_id', 0) ?: null;
        $os_id = (int) or_api_entrada($corpo_json, 'os_id', 0) ?: null;
        $data_validade = or_api_entrada($corpo_json, 'data_validade', null) ?: null;
        $observacoes = limpar(or_api_entrada($corpo_json, 'observacoes', ''));
        $itens = or_api_entrada($corpo_json, 'itens', []);
        if (!is_array($itens) || empty($itens)) {
            or_api_saida(['ok' => false, 'erro' => 'Adicione pelo menos um item ao orçamento.'], 422);
        }
        if ($cliente_id <= 0) {
            or_api_saida(['ok' => false, 'erro' => 'Selecione o cliente.'], 422);
        }
        if ($os_id) {
            $st = $conn->prepare('SELECT id FROM ordens_servico WHERE id = :id AND cliente_id = :cliente_id');
            $st->execute([':id' => $os_id, ':cliente_id' => $cliente_id]);
            if (!$st->fetchColumn()) {
                or_api_saida(['ok' => false, 'erro' => 'A ordem de serviço não pertence ao cliente informado.'], 422);
            }
        }

        $conn->beginTransaction();

        if ($id > 0) {
            $st = $conn->prepare('SELECT id, status FROM orcamentos WHERE id = :id');
            $st->execute([':id' => $id]);
            $orc = $st->fetch();
            if (!$orc || $orc['status'] !== 'aberto') {
                throw new RuntimeException('Este orçamento não pode ser editado.');
            }
            $conn->prepare('UPDATE orcamentos SET cliente_id = :c, veiculo_id = :v, data_validade = :d, observacoes = :o WHERE id = :id')
                ->execute([':c' => $cliente_id, ':v' => $veiculo_id, ':d' => $data_validade, ':o' => $observacoes, ':id' => $id]);
            $conn->prepare('DELETE FROM orcamento_itens WHERE orcamento_id = :id')->execute([':id' => $id]);
            $numero = null;
        } else {
            $numero = (int) $conn->query("SELECT COALESCE(MAX(numero), 0) + 1 FROM orcamentos")->fetchColumn();
            $st = $conn->prepare("INSERT INTO orcamentos (numero, cliente_id, veiculo_id, os_id, data_orcamento, data_validade, status, observacoes, criado_por) VALUES (:numero, :cliente_id, :veiculo_id, :os_id, CURDATE(), :data_validade, 'aberto', :obs, :criado_por)");
            $st->execute([':numero' => $numero, ':cliente_id' => $cliente_id, ':veiculo_id' => $veiculo_id, ':os_id' => $os_id, ':data_validade' => $data_validade, ':obs' => $observacoes, ':criado_por' => $_SESSION['usuario_id']]);
            $id = (int) $conn->lastInsertId();
        }

        [$total_pecas, $total_servicos] = or_api_processar_itens($conn, $id, $itens);
        $total_geral = $total_pecas + $total_servicos;
        $conn->prepare('UPDATE orcamentos SET total_pecas = :tp, total_mao_obra = :tm, total_geral = :tg WHERE id = :id')
            ->execute([':tp' => $total_pecas, ':tm' => $total_servicos, ':tg' => $total_geral, ':id' => $id]);

        if ($os_id && $numero !== null) {
            $st = $conn->prepare('SELECT status FROM ordens_servico WHERE id = :id');
            $st->execute([':id' => $os_id]);
            $os = $st->fetch();
            if ($os && in_array($os['status'], ['recebida', 'em_analise'], true)) {
                $conn->prepare("UPDATE ordens_servico SET status = 'aguardando_aprovacao' WHERE id = :id")->execute([':id' => $os_id]);
                $desc = 'Orçamento #' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT) . ' vinculado a OS';
                $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, 'orcamento', :ant, 'aguardando_aprovacao', :desc, :u)")
                    ->execute([':os_id' => $os_id, ':ant' => $os['status'], ':desc' => $desc, ':u' => $_SESSION['usuario_id']]);
                notificar_responsaveis_os($conn, $os_id, 'orcamento', 'Um novo orçamento foi criado para sua ordem de serviço. Aguardando aprovação.', (int) $_SESSION['usuario_id']);
            }
        }

        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], $numero !== null ? 'cadastrar' : 'editar', 'orcamento', $id, 'Orçamento ' . ($numero !== null ? 'criado' : 'atualizado'), ['total_geral' => $total_geral]);
        or_api_saida(['ok' => true, 'mensagem' => $numero !== null ? 'Orçamento criado com sucesso!' : 'Orçamento atualizado com sucesso!', 'id' => $id]);
    }

    // =========================================
    // STATUS
    // =========================================
    if ($acao === 'mudar_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $novo_status = $_POST['status'] ?? '';
        $status_validos = ['aberto', 'aprovado', 'reprovado', 'cancelado'];
        if (!in_array($novo_status, $status_validos, true)) {
            or_api_saida(['ok' => false, 'erro' => 'Status inválido.'], 422);
        }
        $conn->prepare('UPDATE orcamentos SET status = :status WHERE id = :id')->execute([':status' => $novo_status, ':id' => $id]);
        $st = $conn->prepare('SELECT os_id, numero FROM orcamentos WHERE id = :id');
        $st->execute([':id' => $id]);
        $orc_info = $st->fetch();
        $os_id_orc = $orc_info && $orc_info['os_id'] ? (int) $orc_info['os_id'] : null;
        $rotulos = ['aberto' => 'Aberto', 'aprovado' => 'Aprovado', 'reprovado' => 'Reprovado', 'cancelado' => 'Cancelado'];
        if ($orc_info) {
            notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id_orc, 'orcamento', 'O orçamento #' . str_pad((string) $orc_info['numero'], 4, '0', STR_PAD_LEFT) . ' teve o status alterado para "' . ($rotulos[$novo_status] ?? $novo_status) . '".', (int) $_SESSION['usuario_id']);
        }
        or_api_atualizar_status_os($conn, $os_id_orc, $novo_status, (int) $_SESSION['usuario_id']);
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'status', 'orcamento', $id, 'Orçamento alterado para ' . $novo_status, ['status' => $novo_status]);
        or_api_saida(['ok' => true, 'mensagem' => "Status do orçamento alterado para '" . ucfirst($novo_status) . "'!"]);
    }

    // =========================================
    // DELETE
    // =========================================
    if ($acao === 'excluir' || $acao === 'excluir_lote') {
        $ids = $acao === 'excluir'
            ? [(int) ($_POST['id'] ?? 0)]
            : array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($v) => $v > 0));
        $ids = array_values(array_filter($ids, fn($v) => $v > 0));
        if (!$ids) {
            or_api_saida(['ok' => false, 'erro' => 'Nenhum orçamento selecionado.'], 422);
        }
        $conn->beginTransaction();
        foreach ($ids as $or_id) {
            $st = $conn->prepare('SELECT id, status, os_id FROM orcamentos WHERE id = :id');
            $st->execute([':id' => $or_id]);
            $orc = $st->fetch();
            if (!$orc) {
                continue;
            }
            if ($orc['status'] !== 'aberto') {
                throw new RuntimeException('Apenas orçamentos abertos podem ser excluídos.');
            }
            $conn->prepare('DELETE FROM orcamento_itens WHERE orcamento_id = :id')->execute([':id' => $or_id]);
            $conn->prepare('DELETE FROM orcamentos WHERE id = :id')->execute([':id' => $or_id]);
            if ($orc['os_id']) {
                $st = $conn->prepare('SELECT status FROM ordens_servico WHERE id = :id');
                $st->execute([':id' => $orc['os_id']]);
                $os = $st->fetch();
                if ($os && $os['status'] === 'aguardando_aprovacao') {
                    $conn->prepare("UPDATE ordens_servico SET status = 'em_analise' WHERE id = :id")->execute([':id' => $orc['os_id']]);
                    $conn->prepare("INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, 'orcamento', 'aguardando_aprovacao', 'em_analise', 'Orçamento excluído', :u)")
                        ->execute([':os_id' => $orc['os_id'], ':u' => $_SESSION['usuario_id']]);
                }
            }
        }
        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'excluir', 'orcamento', null, 'Orçamento(s) excluído(s)', ['ids' => $ids]);
        or_api_saida(['ok' => true, 'mensagem' => count($ids) . ' orçamento(s) excluído(s) com sucesso!']);
    }

    or_api_saida(['ok' => false, 'erro' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('API orcamentos: ' . $e->getMessage());
    $status = $e instanceof RuntimeException ? 422 : 500;
    or_api_saida(['ok' => false, 'erro' => $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.'], $status);
}
