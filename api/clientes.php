<?php
// =============================================
// API DE CLIENTES (JSON) - CRUD + AÇÕES EM MASSA
// Endpoint único consumido via AJAX por js/clientes.js.
//
// Segurança:
//  - Sessão obrigatória (includes/sessao.php redireciona se deslogado).
//  - Papel restrito (admin/serviço) verificado aqui com resposta JSON.
//  - CSRF validado em todo POST (header X-CSRF-Token enviado pelo JS).
//  - Todas as consultas usam prepared statements (sem SQL injection).
//  - Saída sempre JSON; erros do banco são registrados no log, nunca expostos.
// =============================================

require_once dirname(__DIR__) . '/includes/sessao.php';
require_once dirname(__DIR__) . '/config/conexao.php';
require_once dirname(__DIR__) . '/includes/auditoria.php';
require_once dirname(__DIR__) . '/includes/clientes.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('cl_api_saida')) {
    function cl_api_saida(array $dados, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if (!function_exists('cl_api_cliente')) {
    // Normaliza uma linha do banco para o formato consumido pelo front.
    function cl_api_cliente(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'nome' => (string) $linha['nome'],
            'cpf' => (string) ($linha['cpf'] ?? ''),
            'telefone' => (string) ($linha['telefone'] ?? ''),
            'email' => (string) ($linha['email'] ?? ''),
            'endereco' => (string) ($linha['endereco'] ?? ''),
            'cidade' => (string) ($linha['cidade'] ?? ''),
            'observacoes' => (string) ($linha['observacoes'] ?? ''),
            'ativo' => (int) ($linha['ativo'] ?? 1) === 1,
            'data_cadastro' => (string) ($linha['data_cadastro'] ?? ''),
            'veiculos' => (int) ($linha['qtd_veiculos'] ?? 0),
            'veiculos_lista' => (string) ($linha['veiculos_lista'] ?? ''),
        ];
    }
}

if (!function_exists('cl_api_buscar')) {
    function cl_api_buscar(PDO $conn, int $id): ?array
    {
        $st = $conn->prepare("SELECT c.*,
            (SELECT COUNT(*) FROM veiculos v WHERE v.cliente_id = c.id) AS qtd_veiculos,
            (SELECT GROUP_CONCAT(CONCAT_WS(' ', v.marca, v.modelo) ORDER BY v.marca, v.modelo SEPARATOR ', ')
                FROM veiculos v WHERE v.cliente_id = c.id) AS veiculos_lista
            FROM clientes c WHERE c.id = :id");
        $st->execute([':id' => $id]);
        $linha = $st->fetch();
        return $linha ? cl_api_cliente($linha) : null;
    }
}

// ---------- Autenticação / autorização ----------
if (!isset($_SESSION['usuario_id'])) {
    cl_api_saida(['ok' => false, 'erro' => 'Não autenticado.'], 401);
}
$papeis_permitidos = ['admin', 'secretaria', 'mecanico_chefe'];
if (!in_array($_SESSION['usuario_tipo'] ?? '', $papeis_permitidos, true)) {
    cl_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
}

clientes_garantir_schema($conn);

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

// ---------- CSRF (mutações) ----------
if ($metodo === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        cl_api_saida(['ok' => false, 'erro' => 'Requisição inválida.'], 403);
    }
}

try {
    // =========================================
    // READ - busca rápida (autocomplete do novo atendimento)
    // =========================================
    if ($acao === 'buscar') {
        $termo = trim((string) ($_GET['termo'] ?? ''));
        if (mb_strlen($termo) < 2) {
            cl_api_saida(['ok' => true, 'itens' => []]);
        }

        $st = $conn->prepare("SELECT c.id, c.nome, c.telefone, c.email, c.cpf, c.cidade,
                (SELECT COUNT(*) FROM veiculos v WHERE v.cliente_id = c.id) AS qtd_veiculos
            FROM clientes c
            WHERE c.nome LIKE :termo OR c.telefone LIKE :telefone OR c.cpf LIKE :cpf
            ORDER BY c.nome
            LIMIT 8");
        $st->execute([
            ':termo' => '%' . $termo . '%',
            ':telefone' => '%' . preg_replace('/\D/', '', $termo) . '%',
            ':cpf' => '%' . preg_replace('/\D/', '', $termo) . '%',
        ]);

        $itens = array_map(function ($c) {
            return [
                'id' => (int) $c['id'],
                'nome' => (string) $c['nome'],
                'telefone' => (string) ($c['telefone'] ?? ''),
                'email' => (string) ($c['email'] ?? ''),
                'cpf' => (string) ($c['cpf'] ?? ''),
                'cidade' => (string) ($c['cidade'] ?? ''),
                'veiculos' => (int) $c['qtd_veiculos'],
            ];
        }, $st->fetchAll());

        cl_api_saida(['ok' => true, 'itens' => $itens]);
    }

    // =========================================
    // READ - listagem paginada
    // =========================================
    if ($acao === 'listar') {
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $cidade = trim((string) ($_GET['cidade'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $ordem = ($_GET['ordem'] ?? 'nome') === 'recentes' ? 'c.data_cadastro DESC, c.nome' : 'c.nome';
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $por_pagina = (int) ($_GET['por_pagina'] ?? 15);
        if (!in_array($por_pagina, [10, 15, 25, 50], true)) {
            $por_pagina = 15;
        }

        $condicoes = [];
        $params = [];
        if ($busca !== '') {
            $condicoes[] = '(c.nome LIKE :busca OR c.cpf LIKE :busca OR c.telefone LIKE :busca OR c.email LIKE :busca OR c.cidade LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }
        if ($cidade !== '') {
            $condicoes[] = 'c.cidade = :cidade';
            $params[':cidade'] = $cidade;
        }
        if ($status === 'ativo' || $status === 'inativo') {
            $condicoes[] = 'c.ativo = :ativo';
            $params[':ativo'] = $status === 'ativo' ? 1 : 0;
        }
        $where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

        $st = $conn->prepare("SELECT COUNT(*) FROM clientes c $where");
        $st->execute($params);
        $total_filtrado = (int) $st->fetchColumn();

        $total_paginas = max(1, (int) ceil($total_filtrado / $por_pagina));
        $pagina = min($pagina, $total_paginas);
        $offset = ($pagina - 1) * $por_pagina;

        $st = $conn->prepare("SELECT c.*,
            (SELECT COUNT(*) FROM veiculos v WHERE v.cliente_id = c.id) AS qtd_veiculos,
            (SELECT GROUP_CONCAT(CONCAT_WS(' ', v.marca, v.modelo) ORDER BY v.marca, v.modelo SEPARATOR ', ')
                FROM veiculos v WHERE v.cliente_id = c.id) AS veiculos_lista
            FROM clientes c $where ORDER BY $ordem LIMIT :limite OFFSET :offset");
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        $itens = array_map('cl_api_cliente', $st->fetchAll());

        // Totais gerais (sem filtro) para os cartões do cabeçalho.
        $totais = $conn->query("SELECT COUNT(*) AS total, COALESCE(SUM(ativo = 1), 0) AS ativos FROM clientes")->fetch();

        $cidades = $conn->query("SELECT DISTINCT cidade FROM clientes WHERE cidade IS NOT NULL AND cidade <> '' ORDER BY cidade")
            ->fetchAll(PDO::FETCH_COLUMN);

        cl_api_saida([
            'ok' => true,
            'itens' => $itens,
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total' => $total_filtrado,
            'total_paginas' => $total_paginas,
            'totais' => [
                'total' => (int) ($totais['total'] ?? 0),
                'ativos' => (int) ($totais['ativos'] ?? 0),
                'inativos' => (int) ($totais['total'] ?? 0) - (int) ($totais['ativos'] ?? 0),
            ],
            'cidades' => $cidades,
        ]);
    }

    // =========================================
    // READ - um cliente
    // =========================================
    if ($acao === 'obter') {
        $id = (int) ($_GET['id'] ?? 0);
        $cliente = cl_api_buscar($conn, $id);
        if (!$cliente) {
            cl_api_saida(['ok' => false, 'erro' => 'Cliente não encontrado.'], 404);
        }
        cl_api_saida(['ok' => true, 'item' => $cliente]);
    }

    // =========================================
    // CREATE / UPDATE
    // =========================================
    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = limpar($_POST['nome'] ?? '');
        $cpf = limpar($_POST['cpf'] ?? '');
        $telefone = limpar($_POST['telefone'] ?? '');
        $email = limpar($_POST['email'] ?? '');
        $endereco = limpar($_POST['endereco'] ?? '');
        $cidade = limpar($_POST['cidade'] ?? '');
        $observacoes = limpar($_POST['observacoes'] ?? '');
        $ativo = (int) ($_POST['ativo'] ?? 1) === 1 ? 1 : 0;

        if ($nome === '') {
            cl_api_saida(['ok' => false, 'erro' => 'O nome do cliente é obrigatório.'], 422);
        }
        if ($cpf !== '' && !validar_cpf($cpf)) {
            cl_api_saida(['ok' => false, 'erro' => 'CPF inválido.'], 422);
        }
        if ($cpf !== '') {
            $st = $conn->prepare("SELECT id FROM clientes WHERE cpf = :cpf AND id <> :id");
            $st->execute([':cpf' => $cpf, ':id' => $id]);
            if ($st->fetch()) {
                cl_api_saida(['ok' => false, 'erro' => 'Já existe um cliente com este CPF.'], 409);
            }
        }

        $dados = [
            ':nome' => $nome,
            ':cpf' => $cpf,
            ':telefone' => $telefone,
            ':email' => $email,
            ':endereco' => $endereco,
            ':cidade' => $cidade,
            ':observacoes' => $observacoes,
            ':ativo' => $ativo,
        ];

        if ($id > 0) {
            $st = $conn->prepare("UPDATE clientes SET nome = :nome, cpf = :cpf, telefone = :telefone,
                email = :email, endereco = :endereco, cidade = :cidade, observacoes = :observacoes,
                ativo = :ativo WHERE id = :id");
            $dados[':id'] = $id;
            $st->execute($dados);
            registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'cliente', $id, 'Cliente atualizado', ['nome' => $nome]);
            $mensagem = 'Cliente atualizado com sucesso!';
        } else {
            $st = $conn->prepare("INSERT INTO clientes (nome, cpf, telefone, email, endereco, cidade, observacoes, ativo)
                VALUES (:nome, :cpf, :telefone, :email, :endereco, :cidade, :observacoes, :ativo)");
            $st->execute($dados);
            $id = (int) $conn->lastInsertId();
            registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'cliente', $id, 'Cliente cadastrado', ['nome' => $nome]);
            $mensagem = 'Cliente cadastrado com sucesso!';
        }

        cl_api_saida(['ok' => true, 'mensagem' => $mensagem, 'item' => cl_api_buscar($conn, $id)]);
    }

    // =========================================
    // DELETE - um cliente
    // =========================================
    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            cl_api_saida(['ok' => false, 'erro' => 'Cliente inválido.'], 422);
        }
        $st = $conn->prepare("DELETE FROM clientes WHERE id = :id");
        $st->execute([':id' => $id]);
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'excluir', 'cliente', $id, 'Cliente excluído');
        cl_api_saida(['ok' => true, 'mensagem' => 'Cliente excluído com sucesso!']);
    }

    // =========================================
    // DELETE em massa
    // =========================================
    if ($acao === 'excluir_lote') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($v) => $v > 0));
        if (!$ids) {
            cl_api_saida(['ok' => false, 'erro' => 'Nenhum cliente selecionado.'], 422);
        }
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $st = $conn->prepare("DELETE FROM clientes WHERE id IN ($marcadores)");
        $st->execute($ids);
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'excluir', 'cliente', null, 'Clientes excluídos em massa', ['ids' => $ids]);
        cl_api_saida(['ok' => true, 'mensagem' => count($ids) . ' cliente(s) excluído(s) com sucesso!']);
    }

    // =========================================
    // STATUS - um ou vários
    // =========================================
    if ($acao === 'status' || $acao === 'status_lote') {
        $ativo = (int) ($_POST['ativo'] ?? 1) === 1 ? 1 : 0;
        $ids = $acao === 'status'
            ? [(int) ($_POST['id'] ?? 0)]
            : array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($v) => $v > 0));
        $ids = array_values(array_filter($ids, fn($v) => $v > 0));
        if (!$ids) {
            cl_api_saida(['ok' => false, 'erro' => 'Nenhum cliente selecionado.'], 422);
        }
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $parametros = array_merge([$ativo], $ids);
        $st = $conn->prepare("UPDATE clientes SET ativo = ? WHERE id IN ($marcadores)");
        $st->execute($parametros);
        $rotulo = $ativo ? 'ativado(s)' : 'suspenso(s)';
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'cliente', null, 'Clientes ' . $rotulo, ['ids' => $ids, 'ativo' => $ativo]);
        cl_api_saida(['ok' => true, 'mensagem' => count($ids) . ' cliente(s) ' . $rotulo . ' com sucesso!']);
    }

    cl_api_saida(['ok' => false, 'erro' => 'Ação inválida.'], 400);
} catch (PDOException $e) {
    error_log('API clientes: ' . $e->getMessage());
    cl_api_saida(['ok' => false, 'erro' => 'Não foi possível concluir a operação.'], 500);
}
