<?php
// =============================================
// API DE ORDENS DE SERVIÇO (JSON)
// -------------------------------------------------
// Ações: listar, obter, criar, mudar_status,
// atribuir, atualizar_diagnostico, mudar_tarefa,
// registrar_entrega, excluir.
// Preserva a lógica de lock de número, eventos,
// histórico e notificações do fluxo original.
// =============================================

require_once dirname(__DIR__) . '/includes/sessao.php';
require_once dirname(__DIR__) . '/config/conexao.php';
require_once dirname(__DIR__) . '/includes/notificacoes.php';
require_once dirname(__DIR__) . '/includes/auditoria.php';
require_once dirname(__DIR__) . '/includes/clientes.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('os_api_saida')) {
    function os_api_saida(array $dados, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

$status_nomes = [
    'recebida' => 'Recebida', 'triagem' => 'Triagem', 'aguardando_diagnostico' => 'Aguardando diagnóstico',
    'aguardando_orcamento' => 'Aguardando orçamento', 'aguardando_aprovacao' => 'Aguardando aprovação',
    'aguardando_peca' => 'Aguardando peça', 'pronta_para_execucao' => 'Pronta para execução',
    'em_execucao' => 'Em execução', 'aguardando_retorno' => 'Aguardando retorno',
    'pronta_para_entrega' => 'Pronta para entrega', 'entregue' => 'Entregue', 'cancelada' => 'Cancelada',
];

if (!isset($_SESSION['usuario_id'])) {
    os_api_saida(['ok' => false, 'erro' => 'Não autenticado.'], 401);
}
$papeis_permitidos = ['admin', 'secretaria', 'mecanico_chefe', 'mecanico'];
if (!in_array($_SESSION['usuario_tipo'] ?? '', $papeis_permitidos, true)) {
    os_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
}
$eh_mecanico = ($_SESSION['usuario_tipo'] ?? '') === 'mecanico';

clientes_garantir_schema($conn);

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

if ($metodo === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        os_api_saida(['ok' => false, 'erro' => 'Requisição inválida.'], 403);
    }
}

if (!function_exists('os_api_evento')) {
    function os_api_evento(PDO $conn, int $os_id, string $tipo, string $descricao, ?string $anterior, ?string $novo): void
    {
        $st = $conn->prepare('INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por) VALUES (:os_id, :tipo, :ant, :novo, :desc, :u)');
        $st->execute([':os_id' => $os_id, ':tipo' => $tipo, ':ant' => $anterior, ':novo' => $novo, ':desc' => $descricao, ':u' => $_SESSION['usuario_id']]);
    }
}

try {
    // =========================================
    // READ - listagem
    // =========================================
    if ($acao === 'listar') {
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $prioridade = trim((string) ($_GET['prioridade'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $prioridades = ['urgente' => 'Urgente', 'alta' => 'Alta', 'normal' => 'Normal', 'baixa' => 'Baixa'];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $por_pagina = (int) ($_GET['por_pagina'] ?? 15);
        if (!in_array($por_pagina, [10, 15, 25, 50], true)) {
            $por_pagina = 15;
        }

        $from = 'FROM vw_ordens_servico_detalhada v';
        $condicoes = ["v.status NOT IN ('entregue', 'cancelada')"];
        $params = [];
        if ($eh_mecanico) {
            $condicoes[] = 'EXISTS (SELECT 1 FROM os_responsaveis r WHERE r.os_id = v.id AND r.usuario_id = :uid AND r.concluido_em IS NULL)';
            $params[':uid'] = (int) $_SESSION['usuario_id'];
        }
        if ($busca !== '') {
            $condicoes[] = '(CAST(v.numero AS CHAR) LIKE :busca OR v.cliente LIKE :busca OR v.placa LIKE :busca OR v.marca LIKE :busca OR v.modelo LIKE :busca OR v.queixa_inicial LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }
        if (array_key_exists($prioridade, $prioridades)) {
            $condicoes[] = 'v.prioridade = :prioridade';
            $params[':prioridade'] = $prioridade;
        }
        if (array_key_exists($status, $status_nomes)) {
            $condicoes[] = 'v.status = :status';
            $params[':status'] = $status;
        }
        $where = 'WHERE ' . implode(' AND ', $condicoes);

        $st = $conn->prepare("SELECT COUNT(*) $from $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $total_paginas = max(1, (int) ceil($total / $por_pagina));
        $pagina = min($pagina, $total_paginas);
        $offset = ($pagina - 1) * $por_pagina;

        $st = $conn->prepare("SELECT v.id, v.numero, v.status, v.status_label, v.prioridade, v.queixa_inicial, v.data_entrada, v.cliente, v.marca, v.modelo, v.placa
            $from $where
            ORDER BY FIELD(v.prioridade, 'urgente', 'alta', 'normal', 'baixa'), v.data_entrada ASC
            LIMIT :limite OFFSET :offset");
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        $itens = array_map(function ($o) {
            return [
                'id' => (int) $o['id'],
                'numero' => (int) $o['numero'],
                'status' => (string) $o['status'],
                'status_label' => (string) ($o['status_label'] ?? $o['status']),
                'prioridade' => (string) $o['prioridade'],
                'queixa_inicial' => (string) $o['queixa_inicial'],
                'data_entrada' => (string) $o['data_entrada'],
                'cliente' => (string) ($o['cliente'] ?? ''),
                'marca' => (string) ($o['marca'] ?? ''),
                'modelo' => (string) ($o['modelo'] ?? ''),
                'placa' => (string) ($o['placa'] ?? ''),
            ];
        }, $st->fetchAll());

        os_api_saida([
            'ok' => true,
            'itens' => $itens,
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total' => $total,
            'total_paginas' => $total_paginas,
        ]);
    }

    // =========================================
    // READ - uma OS
    // =========================================
    if ($acao === 'obter') {
        $id = (int) ($_GET['id'] ?? 0);
        $st = $conn->prepare('SELECT * FROM ordens_servico WHERE id = :id');
        $st->execute([':id' => $id]);
        $os = $st->fetch();
        if (!$os) {
            os_api_saida(['ok' => false, 'erro' => 'Ordem de serviço não encontrada.'], 404);
        }
        os_api_saida(['ok' => true, 'item' => $os]);
    }

    // =========================================
    // CREATE - novo atendimento (wizard)
    // =========================================
    if ($acao === 'criar') {
        if (!tem_papel(['admin', 'secretaria', 'mecanico_chefe'])) {
            os_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
        }
        $nome = limpar($_POST['nome'] ?? '');
        $telefone = limpar($_POST['telefone'] ?? '');
        // O wizard envia marca/modelo pelos campos ocultos populados pela cascata FIPE.
        $marca = limpar($_POST['marca_nome'] ?? '') ?: limpar($_POST['marca'] ?? '');
        $modelo = limpar($_POST['modelo_nome'] ?? '') ?: limpar($_POST['modelo'] ?? '');
        $placa = strtoupper(limpar($_POST['placa'] ?? ''));
        $ano = (int) ($_POST['ano'] ?? 0);
        $cor = limpar($_POST['cor'] ?? '');
        $km_atual = (int) ($_POST['km_atual'] ?? 0);
        $chassi = limpar($_POST['chassi'] ?? '');
        $prioridade = $_POST['prioridade'] ?? 'normal';
        // Endereço (opcional)
        $cep = limpar($_POST['cep'] ?? '');
        $endereco = limpar($_POST['endereco'] ?? '');
        $numero = limpar($_POST['numero'] ?? '');
        $complemento = limpar($_POST['complemento'] ?? '');
        $bairro = limpar($_POST['bairro'] ?? '');
        $cidade = limpar($_POST['cidade'] ?? '');
        $uf = mb_strtoupper(limpar($_POST['uf'] ?? ''));
        $queixa = limpar($_POST['queixa_inicial'] ?? '');
        $observacoes = limpar($_POST['observacoes_internas'] ?? '');
        $tarefas = array_values(array_filter(array_map('trim', explode("\n", $_POST['tarefas'] ?? ''))));
        $funcionarios_ids = array_values(array_unique(array_map('intval', (array) ($_POST['funcionarios_ids'] ?? []))));
        $pecas_linhas = array_values(array_filter(array_map('trim', explode("\n", $_POST['pecas'] ?? ''))));

        if ($nome === '' || $queixa === '' || !in_array($prioridade, ['baixa', 'normal', 'alta', 'urgente'], true)) {
            os_api_saida(['ok' => false, 'erro' => 'Informe o nome, a queixa e uma prioridade válida.'], 422);
        }

        // Cliente e veículo podem vir já selecionados no wizard (autocomplete).
        $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
        $veiculo_id = (int) ($_POST['veiculo_id'] ?? 0);

        $conn->beginTransaction();

        if ($cliente_id > 0) {
            $st = $conn->prepare('SELECT id, nome, telefone FROM clientes WHERE id = :id');
            $st->execute([':id' => $cliente_id]);
            $cliente = $st->fetch();
            if (!$cliente) {
                throw new RuntimeException('Cliente informado não existe.');
            }
            if ($telefone === '') {
                $telefone = (string) ($cliente['telefone'] ?? '');
            }
        }

        $tem_endereco = $cep !== '' || $endereco !== '' || $numero !== ''
            || $complemento !== '' || $bairro !== '' || $cidade !== '' || $uf !== '';
        $endereco_completo = trim($endereco . ' ' . $numero . ($complemento !== '' ? ' - ' . $complemento : ''));

        if (!$cliente_id) {
            if ($telefone !== '') {
                $st = $conn->prepare('SELECT id FROM clientes WHERE telefone = :telefone LIMIT 1');
                $st->execute([':telefone' => $telefone]);
                $cliente_id = $st->fetchColumn() ?: null;
            }

            if (!$cliente_id) {
                $st = $conn->prepare('INSERT INTO clientes
                    (nome, telefone, cep, endereco, numero, complemento, bairro, cidade, uf)
                    VALUES (:nome, :telefone, :cep, :endereco, :numero, :complemento, :bairro, :cidade, :uf)');
                $st->execute([
                    ':nome' => $nome,
                    ':telefone' => $telefone ?: null,
                    ':cep' => $cep ?: null,
                    ':endereco' => $endereco_completo ?: null,
                    ':numero' => $numero ?: null,
                    ':complemento' => $complemento ?: null,
                    ':bairro' => $bairro ?: null,
                    ':cidade' => $cidade ?: null,
                    ':uf' => $uf ?: null,
                ]);
                $cliente_id = (int) $conn->lastInsertId();
            } else {
                // Cliente existente: só completa o endereço ainda não informado.
                $sql_endereco = "UPDATE clientes SET nome = :nome,
                        cep = COALESCE(NULLIF(:cep, ''), cep),
                        endereco = COALESCE(NULLIF(:endereco, ''), endereco),
                        numero = COALESCE(NULLIF(:numero, ''), numero),
                        complemento = COALESCE(NULLIF(:complemento, ''), complemento),
                        bairro = COALESCE(NULLIF(:bairro, ''), bairro),
                        cidade = COALESCE(NULLIF(:cidade, ''), cidade),
                        uf = COALESCE(NULLIF(:uf, ''), uf)
                    WHERE id = :id";
                $conn->prepare($sql_endereco)
                    ->execute([
                        ':nome' => $nome,
                        ':cep' => $cep,
                        ':endereco' => $endereco_completo,
                        ':numero' => $numero,
                        ':complemento' => $complemento,
                        ':bairro' => $bairro,
                        ':cidade' => $cidade,
                        ':uf' => $uf,
                        ':id' => $cliente_id,
                    ]);
            }
        } elseif ($tem_endereco) {
            // Cliente escolhido no autocomplete: completa o endereço em branco.
            $sql_endereco = "UPDATE clientes SET
                    cep = COALESCE(NULLIF(:cep, ''), cep),
                    endereco = COALESCE(NULLIF(:endereco, ''), endereco),
                    numero = COALESCE(NULLIF(:numero, ''), numero),
                    complemento = COALESCE(NULLIF(:complemento, ''), complemento),
                    bairro = COALESCE(NULLIF(:bairro, ''), bairro),
                    cidade = COALESCE(NULLIF(:cidade, ''), cidade),
                    uf = COALESCE(NULLIF(:uf, ''), uf)
                WHERE id = :id";
            $conn->prepare($sql_endereco)->execute([
                ':cep' => $cep,
                ':endereco' => $endereco_completo,
                ':numero' => $numero,
                ':complemento' => $complemento,
                ':bairro' => $bairro,
                ':cidade' => $cidade,
                ':uf' => $uf,
                ':id' => $cliente_id,
            ]);
        }

        if ($veiculo_id > 0) {
            $st = $conn->prepare('SELECT id, cliente_id, placa FROM veiculos WHERE id = :id');
            $st->execute([':id' => $veiculo_id]);
            $veiculo = $st->fetch();
            if ($veiculo && (int) $veiculo['cliente_id'] !== (int) $cliente_id) {
                throw new RuntimeException('O veículo informado pertence a outro cliente.');
            }
            if (!$veiculo) {
                $veiculo_id = 0;
            } else {
                // Atualiza os campos editados no wizard.
                $st = $conn->prepare('UPDATE veiculos SET marca = COALESCE(NULLIF(:marca, \'\'), marca), modelo = COALESCE(NULLIF(:modelo, \'\'), modelo), cor = COALESCE(NULLIF(:cor, \'\'), cor), km_atual = :km, chassi = COALESCE(NULLIF(:chassi, \'\'), chassi), ano = COALESCE(NULLIF(:ano, 0), ano) WHERE id = :id');
                $st->execute([
                    ':marca' => $marca,
                    ':modelo' => $modelo,
                    ':cor' => $cor,
                    ':km' => $km_atual,
                    ':chassi' => $chassi,
                    ':ano' => $ano,
                    ':id' => $veiculo_id,
                ]);
            }
        }

        // Sem veículo escolhido, gravamos NULL (e nunca 0, para não violar a FK).
        $veiculo_id = $veiculo_id > 0 ? $veiculo_id : null;

        if (!$veiculo_id && $placa !== '') {
            $st = $conn->prepare('SELECT id, cliente_id FROM veiculos WHERE placa = :placa LIMIT 1');
            $st->execute([':placa' => $placa]);
            $veiculo = $st->fetch();
            if ($veiculo && (int) $veiculo['cliente_id'] !== $cliente_id) {
                throw new RuntimeException('A placa informada já está vinculada a outro cliente.');
            }
            if ($veiculo) {
                $veiculo_id = (int) $veiculo['id'];
                $st = $conn->prepare('UPDATE veiculos SET marca = :marca, modelo = :modelo WHERE id = :id');
                $st->execute([':marca' => $marca ?: null, ':modelo' => $modelo ?: null, ':id' => $veiculo_id]);
            } else {
                $st = $conn->prepare('INSERT INTO veiculos (cliente_id, placa, marca, modelo, cor, km_atual, chassi, ano, marca_id, modelo_base_id, modelo_id) VALUES (:cliente_id, :placa, :marca, :modelo, :cor, :km, :chassi, :ano, :marca_id, :base_id, :modelo_id)');
                $st->execute([
                    ':cliente_id' => $cliente_id,
                    ':placa' => $placa,
                    ':marca' => $marca ?: null,
                    ':modelo' => $modelo ?: null,
                    ':cor' => $cor ?: null,
                    ':km' => $km_atual,
                    ':chassi' => $chassi ?: null,
                    ':ano' => $ano ?: null,
                    ':marca_id' => (int) ($_POST['marca_id'] ?? 0) ?: null,
                    ':base_id' => (int) ($_POST['modelo_base_id'] ?? 0) ?: null,
                    ':modelo_id' => (int) ($_POST['modelo_id'] ?? 0) ?: null,
                ]);
                $veiculo_id = (int) $conn->lastInsertId();
            }
        }

        $conn->query("SELECT GET_LOCK('mecanica_os_numero', 5)");
        $numero = (int) $conn->query('SELECT COALESCE(MAX(numero), 0) + 1 FROM ordens_servico')->fetchColumn();
        $st = $conn->prepare("INSERT INTO ordens_servico (numero, cliente_id, veiculo_id, status, prioridade, queixa_inicial, observacoes_internas, responsavel_chefe_id, criado_por) VALUES (:numero, :cliente_id, :veiculo_id, 'recebida', :prioridade, :queixa, :observacoes, :chefe, :criado_por)");
        $st->execute([
            ':numero' => $numero, ':cliente_id' => $cliente_id, ':veiculo_id' => $veiculo_id,
            ':prioridade' => $prioridade, ':queixa' => $queixa, ':observacoes' => $observacoes ?: null,
            ':chefe' => $_SESSION['usuario_tipo'] === 'mecanico_chefe' ? $_SESSION['usuario_id'] : null,
            ':criado_por' => $_SESSION['usuario_id'],
        ]);
        $os_id = (int) $conn->lastInsertId();
        $conn->query("SELECT RELEASE_LOCK('mecanica_os_numero')");

        foreach ($funcionarios_ids as $funcionario_id) {
            $st = $conn->prepare("SELECT id FROM usuarios WHERE id = :id AND ativo = 1 AND tipo IN ('mecanico', 'mecanico_chefe')");
            $st->execute([':id' => $funcionario_id]);
            if (!$st->fetchColumn()) {
                throw new RuntimeException('Um dos funcionários selecionados não está disponível.');
            }
            $conn->prepare('INSERT INTO os_responsaveis (os_id, usuario_id, atribuido_por) VALUES (:os_id, :usuario_id, :atribuido_por)')
                ->execute([':os_id' => $os_id, ':usuario_id' => $funcionario_id, ':atribuido_por' => $_SESSION['usuario_id']]);
        }

        $num_formatado = str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
        notificar_usuarios($conn, $funcionarios_ids, $os_id, 'os_atribuida', 'Nova OS #' . $num_formatado . ' atribuída a você.');
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'os_criada', 'Nova ordem de serviço #' . $num_formatado . ' criada.', (int) $_SESSION['usuario_id']);

        foreach ($tarefas as $tarefa) {
            if (mb_strlen($tarefa) > 255) {
                throw new RuntimeException('Cada tarefa deve ter no máximo 255 caracteres.');
            }
            $conn->prepare('INSERT INTO os_tarefas (os_id, descricao, criado_por) VALUES (:os_id, :descricao, :criado_por)')
                ->execute([':os_id' => $os_id, ':descricao' => $tarefa, ':criado_por' => $_SESSION['usuario_id']]);
        }

        if ($pecas_linhas) {
            $st = $conn->prepare("INSERT INTO pedidos_pecas (os_id, status, observacoes, criado_por) VALUES (:os_id, 'solicitado', 'Pedido criado automaticamente na abertura da OS.', :criado_por)");
            $st->execute([':os_id' => $os_id, ':criado_por' => $_SESSION['usuario_id']]);
            $pedido_id = (int) $conn->lastInsertId();
            foreach ($pecas_linhas as $peca_linha) {
                $partes = array_map('trim', explode('|', $peca_linha, 2));
                $descricao = $partes[0] ?? '';
                $quantidade = isset($partes[1]) ? (int) $partes[1] : 1;
                if ($descricao === '' || $quantidade <= 0 || mb_strlen($descricao) > 200) {
                    throw new RuntimeException('Use o formato "Peça | quantidade" para cada peça.');
                }
                $conn->prepare('INSERT INTO pedido_pecas_itens (pedido_id, descricao, quantidade) VALUES (:pedido_id, :descricao, :quantidade)')
                    ->execute([':pedido_id' => $pedido_id, ':descricao' => $descricao, ':quantidade' => $quantidade]);
            }
            $conn->prepare("UPDATE ordens_servico SET status = 'aguardando_peca' WHERE id = :id")->execute([':id' => $os_id]);
        }

        os_api_evento($conn, $os_id, 'criacao', 'Atendimento criado', null, 'recebida');
        $conn->commit();

        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'ordem_servico', $os_id, 'OS #' . $num_formatado . ' criada', ['numero' => $numero, 'cliente_id' => $cliente_id, 'veiculo_id' => $veiculo_id]);
        os_api_saida(['ok' => true, 'mensagem' => 'Ordem de serviço criada com sucesso.', 'id' => $os_id, 'numero' => $numero]);
    }

    // Ações sobre uma OS exigem id e validação de escopo do mecânico.
    $os_id = (int) ($_POST['os_id'] ?? 0);
    if (!$os_id) {
        os_api_saida(['ok' => false, 'erro' => 'Ordem de serviço inválida.'], 422);
    }
    $st = $conn->prepare('SELECT id, numero, status FROM ordens_servico WHERE id = :id');
    $st->execute([':id' => $os_id]);
    $os_atual = $st->fetch();
    if (!$os_atual) {
        os_api_saida(['ok' => false, 'erro' => 'Ordem de serviço não encontrada.'], 404);
    }
    if ($eh_mecanico) {
        $st = $conn->prepare('SELECT 1 FROM os_responsaveis WHERE os_id = :os_id AND usuario_id = :uid AND concluido_em IS NULL');
        $st->execute([':os_id' => $os_id, ':uid' => $_SESSION['usuario_id']]);
        if (!$st->fetchColumn()) {
            os_api_saida(['ok' => false, 'erro' => 'Esta ordem de serviço não está atribuída a você.'], 403);
        }
    }

    // =========================================
    // UPDATE - diagnóstico
    // =========================================
    if ($acao === 'atualizar_diagnostico') {
        $diagnostico = limpar($_POST['diagnostico'] ?? '');
        $observacoes = limpar($_POST['observacoes_internas'] ?? '');
        if ($diagnostico === '') {
            os_api_saida(['ok' => false, 'erro' => 'Informe o diagnóstico técnico.'], 422);
        }
        $st = $conn->prepare("UPDATE ordens_servico SET diagnostico = :diagnostico, observacoes_internas = :observacoes, status = IF(status IN ('recebida', 'triagem', 'aguardando_diagnostico'), 'aguardando_orcamento', status) WHERE id = :id");
        $st->execute([':diagnostico' => $diagnostico, ':observacoes' => $observacoes ?: null, ':id' => $os_id]);
        os_api_evento($conn, $os_id, 'diagnostico', 'Diagnóstico técnico atualizado', $os_atual['status'], 'aguardando_orcamento');
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'diagnostico', 'Diagnóstico da OS #' . $os_atual['numero'] . ' atualizado — aguardando orçamento.', (int) $_SESSION['usuario_id']);
        os_api_saida(['ok' => true, 'mensagem' => 'Diagnóstico atualizado.']);
    }

    // =========================================
    // UPDATE - tarefa
    // =========================================
    if ($acao === 'mudar_tarefa') {
        $tarefa_id = (int) ($_POST['tarefa_id'] ?? 0);
        $novo_status = $_POST['tarefa_status'] ?? '';
        if (!in_array($novo_status, ['pendente', 'em_execucao', 'concluida', 'bloqueada'], true)) {
            os_api_saida(['ok' => false, 'erro' => 'Status de tarefa inválido.'], 422);
        }
        $st = $conn->prepare('SELECT id FROM os_tarefas WHERE id = :tarefa AND os_id = :os_id');
        $st->execute([':tarefa' => $tarefa_id, ':os_id' => $os_id]);
        if (!$st->fetchColumn()) {
            os_api_saida(['ok' => false, 'erro' => 'Tarefa não encontrada nesta OS.'], 404);
        }
        $st = $conn->prepare("UPDATE os_tarefas SET status = :status, concluido_por = IF(:status = 'concluida', :usuario, concluido_por), concluido_em = IF(:status = 'concluida', NOW(), concluido_em) WHERE id = :id");
        $st->execute([':status' => $novo_status, ':usuario' => $_SESSION['usuario_id'], ':id' => $tarefa_id]);
        os_api_evento($conn, $os_id, 'tarefa_status', 'Tarefa atualizada para ' . $novo_status, null, null);
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'tarefa', 'Uma tarefa da OS #' . $os_atual['numero'] . ' foi atualizada para "' . str_replace('_', ' ', $novo_status) . '".', (int) $_SESSION['usuario_id']);
        os_api_saida(['ok' => true, 'mensagem' => 'Tarefa atualizada.']);
    }

    // =========================================
    // UPDATE - status
    // =========================================
    if ($acao === 'mudar_status') {
        if (!tem_papel(['admin', 'secretaria', 'mecanico_chefe'])) {
            os_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
        }
        $novo_status = $_POST['status'] ?? '';
        $status_validos = array_keys($status_nomes);
        if (!in_array($novo_status, $status_validos, true)) {
            os_api_saida(['ok' => false, 'erro' => 'Status inválido.'], 422);
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
            'cancelada' => [],
        ];
        if ($novo_status !== $os_atual['status'] && !in_array($novo_status, $transicoes[$os_atual['status']] ?? [], true)) {
            os_api_saida(['ok' => false, 'erro' => 'Essa mudança de status não é permitida para a etapa atual.'], 422);
        }
        if ($novo_status === 'entregue') {
            $st = $conn->prepare("SELECT 1 FROM os_entregas WHERE os_id = :os_id AND status = 'concluida' LIMIT 1");
            $st->execute([':os_id' => $os_id]);
            if (!$st->fetchColumn()) {
                os_api_saida(['ok' => false, 'erro' => 'Confirme a entrega ou retirada antes de encerrar a OS.'], 422);
            }
        }
        $conn->beginTransaction();
        $st = $conn->prepare("UPDATE ordens_servico SET status = :status, data_conclusao = IF(:status = 'entregue', NOW(), data_conclusao) WHERE id = :id");
        $st->execute([':status' => $novo_status, ':id' => $os_id]);
        $num_os = str_pad((string) $os_atual['numero'], 4, '0', STR_PAD_LEFT);
        $msg_status = 'O status da OS #' . $num_os . ' mudou para "' . str_replace('_', ' ', $novo_status) . '".';
        notificar_responsaveis_os($conn, $os_id, 'os_status', $msg_status, (int) $_SESSION['usuario_id']);
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'os_status', $msg_status, (int) $_SESSION['usuario_id']);
        if ($novo_status === 'entregue') {
            $st = $conn->prepare("SELECT cliente_id, veiculo_id, COALESCE(diagnostico, queixa_inicial) AS descricao FROM ordens_servico WHERE id = :id");
            $st->execute([':id' => $os_id]);
            $dados_os = $st->fetch();
            $conn->prepare('INSERT INTO cliente_servicos_historico (cliente_id, veiculo_id, os_id, descricao) VALUES (:cliente, :veiculo, :os, :descricao)')
                ->execute([':cliente' => $dados_os['cliente_id'], ':veiculo' => $dados_os['veiculo_id'], ':os' => $os_id, ':descricao' => $dados_os['descricao']]);
        }
        $conn->commit();
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'status', 'ordem_servico', $os_id, 'Status alterado de ' . $os_atual['status'] . ' para ' . $novo_status, ['status_anterior' => $os_atual['status'], 'status_novo' => $novo_status]);
        os_api_saida(['ok' => true, 'mensagem' => 'Status da OS atualizado.']);
    }

    // =========================================
    // UPDATE - atribuir mecânico
    // =========================================
    if ($acao === 'atribuir') {
        if (!tem_papel(['admin', 'mecanico_chefe'])) {
            os_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
        }
        $usuario_id = (int) ($_POST['usuario_id'] ?? 0);
        $st = $conn->prepare("SELECT id FROM usuarios WHERE id = :id AND tipo IN ('mecanico', 'mecanico_chefe')");
        $st->execute([':id' => $usuario_id]);
        if (!$st->fetchColumn()) {
            os_api_saida(['ok' => false, 'erro' => 'Mecânico inválido.'], 422);
        }
        $conn->prepare('INSERT INTO os_responsaveis (os_id, usuario_id, atribuido_por) VALUES (:os_id, :usuario_id, :atribuido_por) ON DUPLICATE KEY UPDATE concluido_em = NULL')
            ->execute([':os_id' => $os_id, ':usuario_id' => $usuario_id, ':atribuido_por' => $_SESSION['usuario_id']]);
        $conn->prepare("INSERT INTO notificacoes (usuario_id, os_id, tipo, mensagem) VALUES (:usuario_id, :os_id, 'os_atribuida', 'Uma nova ordem de serviço foi atribuída a você.')")
            ->execute([':usuario_id' => $usuario_id, ':os_id' => $os_id]);
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'atribuir', 'ordem_servico', $os_id, 'Mecânico #' . $usuario_id . ' atribuído à OS', ['usuario_id' => $usuario_id]);
        os_api_saida(['ok' => true, 'mensagem' => 'Mecânico atribuído à OS.']);
    }

    // =========================================
    // UPDATE - entrega
    // =========================================
    if ($acao === 'registrar_entrega') {
        if (!tem_papel(['admin', 'secretaria', 'mecanico_chefe'])) {
            os_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
        }
        $modalidade = $_POST['modalidade'] ?? '';
        $responsavel = limpar($_POST['responsavel'] ?? '');
        $destino = limpar($_POST['destino'] ?? '');
        $km_saida = (int) ($_POST['km_saida'] ?? 0);
        if (!in_array($modalidade, ['retirada', 'entrega'], true)) {
            os_api_saida(['ok' => false, 'erro' => 'Modalidade de entrega inválida.'], 422);
        }
        if ($os_atual['status'] !== 'pronta_para_entrega') {
            os_api_saida(['ok' => false, 'erro' => 'A OS precisa estar pronta para entrega antes da confirmação.'], 422);
        }
        $st = $conn->prepare("INSERT INTO os_entregas (os_id, modalidade, responsavel, destino, km_saida, status, criado_por, concluido_por, concluido_em) VALUES (:os_id, :modalidade, :responsavel, :destino, :km_saida, 'concluida', :usuario, :usuario, NOW())");
        $st->execute([':os_id' => $os_id, ':modalidade' => $modalidade, ':responsavel' => $responsavel ?: null, ':destino' => $destino ?: null, ':km_saida' => $km_saida ?: null, ':usuario' => $_SESSION['usuario_id']]);
        $conn->prepare("UPDATE ordens_servico SET status = 'entregue', data_conclusao = NOW() WHERE id = :id")->execute([':id' => $os_id]);
        os_api_evento($conn, $os_id, 'entrega_planejada', 'Entrega ou retirada registrada', null, null);
        notificar_tipos($conn, ['admin', 'secretaria', 'mecanico_chefe'], $os_id, 'entrega', 'Entrega da OS #' . str_pad((string) $os_atual['numero'], 4, '0', STR_PAD_LEFT) . ' registrada — aguardando confirmação.', (int) $_SESSION['usuario_id']);
        $st = $conn->prepare("SELECT cliente_id, veiculo_id, COALESCE(diagnostico, queixa_inicial) AS descricao FROM ordens_servico WHERE id = :id");
        $st->execute([':id' => $os_id]);
        $dados_os = $st->fetch();
        $conn->prepare('INSERT INTO cliente_servicos_historico (cliente_id, veiculo_id, os_id, descricao) VALUES (:cliente, :veiculo, :os, :descricao)')
            ->execute([':cliente' => $dados_os['cliente_id'], ':veiculo' => $dados_os['veiculo_id'], ':os' => $os_id, ':descricao' => $dados_os['descricao']]);
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'entrega', 'ordem_servico', $os_id, 'Entrega/retirada registrada (' . $modalidade . ')', ['modalidade' => $modalidade]);
        os_api_saida(['ok' => true, 'mensagem' => 'Entrega ou retirada registrada.']);
    }

    os_api_saida(['ok' => false, 'erro' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    if (isset($conn)) {
        $conn->query("SELECT RELEASE_LOCK('mecanica_os_numero')");
    }
    error_log('API ordens_servico: ' . $e->getMessage());
    $status = $e instanceof RuntimeException ? 422 : 500;
    os_api_saida(['ok' => false, 'erro' => $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.'], $status);
}
