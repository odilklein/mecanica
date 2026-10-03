<?php
// =============================================
// API DE FUNCIONÁRIOS/USUÁRIOS (JSON)
// CRUD: listar, obter, salvar, status e status_lote.
// Sem exclusão física (usuários possuem vínculos);
// o desligamento é feito via status (ativo = 0).
// =============================================

require_once dirname(__DIR__) . '/includes/sessao.php';
require_once dirname(__DIR__) . '/config/conexao.php';
require_once dirname(__DIR__) . '/includes/auditoria.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('fn_api_saida')) {
    function fn_api_saida(array $dados, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

$tipos = ['admin' => 'Administrador', 'secretaria' => 'Secretaria', 'mecanico_chefe' => 'Mecânico-chefe', 'mecanico' => 'Mecânico'];

if (!function_exists('fn_api_usuario')) {
    function fn_api_usuario(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'nome' => (string) $linha['nome'],
            'email' => (string) $linha['email'],
            'cpf' => (string) ($linha['cpf'] ?? ''),
            'telefone' => (string) ($linha['telefone'] ?? ''),
            'cargo' => (string) ($linha['cargo'] ?? ''),
            'salario' => $linha['salario'] !== null ? (float) $linha['salario'] : null,
            'data_admissao' => (string) ($linha['data_admissao'] ?? ''),
            'tipo' => (string) $linha['tipo'],
            'ativo' => (int) ($linha['ativo'] ?? 1) === 1,
        ];
    }
}

if (!isset($_SESSION['usuario_id'])) {
    fn_api_saida(['ok' => false, 'erro' => 'Não autenticado.'], 401);
}
$papeis_permitidos = ['admin', 'secretaria', 'mecanico_chefe'];
if (!in_array($_SESSION['usuario_tipo'] ?? '', $papeis_permitidos, true)) {
    fn_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
}

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

if ($metodo === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        fn_api_saida(['ok' => false, 'erro' => 'Requisição inválida.'], 403);
    }
}

try {
    if ($acao === 'listar') {
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $perfil = trim((string) ($_GET['perfil'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $por_pagina = (int) ($_GET['por_pagina'] ?? 15);
        if (!in_array($por_pagina, [10, 15, 25, 50], true)) {
            $por_pagina = 15;
        }

        $condicoes = [];
        $params = [];
        if ($busca !== '') {
            $condicoes[] = '(u.nome LIKE :busca OR u.email LIKE :busca OR u.cargo LIKE :busca OR u.cpf LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }
        if (array_key_exists($perfil, $tipos)) {
            $condicoes[] = 'u.tipo = :perfil';
            $params[':perfil'] = $perfil;
        }
        if ($status === 'ativo' || $status === 'inativo') {
            $condicoes[] = 'u.ativo = :ativo';
            $params[':ativo'] = $status === 'ativo' ? 1 : 0;
        }
        $where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

        $st = $conn->prepare("SELECT COUNT(*) FROM usuarios u $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $total_paginas = max(1, (int) ceil($total / $por_pagina));
        $pagina = min($pagina, $total_paginas);
        $offset = ($pagina - 1) * $por_pagina;

        $st = $conn->prepare("SELECT u.* FROM usuarios u $where ORDER BY u.ativo DESC, u.nome LIMIT :limite OFFSET :offset");
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        $itens = array_map('fn_api_usuario', $st->fetchAll());

        $totais = $conn->query("SELECT COUNT(*) AS total, COALESCE(SUM(ativo = 1), 0) AS ativos FROM usuarios")->fetch();

        fn_api_saida([
            'ok' => true,
            'itens' => $itens,
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total' => $total,
            'total_paginas' => $total_paginas,
            'totais' => [
                'total' => (int) ($totais['total'] ?? 0),
                'ativos' => (int) ($totais['ativos'] ?? 0),
                'inativos' => (int) ($totais['total'] ?? 0) - (int) ($totais['ativos'] ?? 0),
            ],
            'perfis' => $tipos,
        ]);
    }

    if ($acao === 'obter') {
        $id = (int) ($_GET['id'] ?? 0);
        $st = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
        $st->execute([':id' => $id]);
        $linha = $st->fetch();
        if (!$linha) {
            fn_api_saida(['ok' => false, 'erro' => 'Funcionário não encontrado.'], 404);
        }
        fn_api_saida(['ok' => true, 'item' => fn_api_usuario($linha)]);
    }

    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = limpar($_POST['nome'] ?? '');
        $email = strtolower(limpar($_POST['email'] ?? ''));
        $cpf = limpar($_POST['cpf'] ?? '');
        $telefone = limpar($_POST['telefone'] ?? '');
        $cargo = limpar($_POST['cargo'] ?? '');
        $data_admissao = trim((string) ($_POST['data_admissao'] ?? ''));
        $salario = ($_POST['salario'] ?? '') !== '' ? (float) $_POST['salario'] : null;
        $tipo = (string) ($_POST['tipo'] ?? 'mecanico');
        $senha = (string) ($_POST['senha'] ?? '');
        $ativo = (int) ($_POST['ativo'] ?? 1) === 1 ? 1 : 0;

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fn_api_saida(['ok' => false, 'erro' => 'Informe nome e e-mail válidos.'], 422);
        }
        if (!array_key_exists($tipo, $tipos)) {
            fn_api_saida(['ok' => false, 'erro' => 'Perfil de acesso inválido.'], 422);
        }
        if ($salario !== null && $salario < 0) {
            fn_api_saida(['ok' => false, 'erro' => 'Salário inválido.'], 422);
        }
        if ($tipo === 'admin' && $_SESSION['usuario_tipo'] !== 'admin') {
            fn_api_saida(['ok' => false, 'erro' => 'Apenas administrador pode atribuir o perfil de administrador.'], 403);
        }

        // E-mail único
        $st = $conn->prepare("SELECT id FROM usuarios WHERE email = :email AND id <> :id");
        $st->execute([':email' => $email, ':id' => $id]);
        if ($st->fetch()) {
            fn_api_saida(['ok' => false, 'erro' => 'Já existe um usuário com este e-mail.'], 409);
        }

        if ($id > 0) {
            // Não pode desativar o próprio acesso; não pode trocar o próprio perfil.
            if ($id === (int) $_SESSION['usuario_id']) {
                if (!$ativo) {
                    fn_api_saida(['ok' => false, 'erro' => 'Você não pode desativar o próprio acesso.'], 422);
                }
                $tipo = $_SESSION['usuario_tipo'];
            }

            $fields = 'nome = :nome, email = :email, cpf = :cpf, telefone = :telefone, salario = :salario, data_admissao = :data_admissao, cargo = :cargo, ativo = :ativo, tipo = :tipo';
            $params = [
                ':nome' => $nome, ':email' => $email, ':cpf' => $cpf ?: null, ':telefone' => $telefone ?: null,
                ':salario' => $salario, ':data_admissao' => $data_admissao ?: null, ':cargo' => $cargo ?: null,
                ':ativo' => $ativo, ':tipo' => $tipo, ':id' => $id,
            ];
            if ($senha !== '') {
                if (strlen($senha) < 8) {
                    fn_api_saida(['ok' => false, 'erro' => 'A nova senha deve ter pelo menos 8 caracteres.'], 422);
                }
                $fields .= ', senha = :senha';
                $params[':senha'] = password_hash($senha, PASSWORD_DEFAULT);
            }
            $st = $conn->prepare("UPDATE usuarios SET $fields WHERE id = :id");
            $st->execute($params);
            registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'usuario', $id, 'Funcionário atualizado', ['nome' => $nome, 'tipo' => $tipo, 'ativo' => $ativo]);
            $mensagem = 'Funcionário atualizado com sucesso!';
        } else {
            if (strlen($senha) < 8) {
                fn_api_saida(['ok' => false, 'erro' => 'A senha deve ter pelo menos 8 caracteres.'], 422);
            }
            $st = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo, cpf, telefone, salario, data_admissao, cargo, ativo)
                VALUES (:nome, :email, :senha, :tipo, :cpf, :telefone, :salario, :data_admissao, :cargo, 1)");
            $st->execute([
                ':nome' => $nome, ':email' => $email, ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                ':tipo' => $tipo, ':cpf' => $cpf ?: null, ':telefone' => $telefone ?: null,
                ':salario' => $salario, ':data_admissao' => $data_admissao ?: null, ':cargo' => $cargo ?: null,
            ]);
            $id = (int) $conn->lastInsertId();
            registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'usuario', $id, 'Funcionário cadastrado', ['nome' => $nome, 'tipo' => $tipo]);
            $mensagem = 'Funcionário cadastrado com sucesso!';
        }

        $st = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
        $st->execute([':id' => $id]);
        fn_api_saida(['ok' => true, 'mensagem' => $mensagem, 'item' => fn_api_usuario($st->fetch())]);
    }

    if ($acao === 'status' || $acao === 'status_lote') {
        $ativo = (int) ($_POST['ativo'] ?? 1) === 1 ? 1 : 0;
        $ids = $acao === 'status'
            ? [(int) ($_POST['id'] ?? 0)]
            : array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($v) => $v > 0));
        $ids = array_values(array_filter($ids, fn($v) => $v > 0));

        if (!$ids) {
            fn_api_saida(['ok' => false, 'erro' => 'Nenhum funcionário selecionado.'], 422);
        }
        // Bloqueia desativar a si mesmo.
        if (!$ativo && in_array((int) $_SESSION['usuario_id'], $ids, true)) {
            fn_api_saida(['ok' => false, 'erro' => 'Você não pode desativar o próprio acesso.'], 422);
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $parametros = array_merge([$ativo], $ids);
        $st = $conn->prepare("UPDATE usuarios SET ativo = ? WHERE id IN ($marcadores)");
        $st->execute($parametros);

        $rotulo = $ativo ? 'ativado(s)' : 'desativado(s)';
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'usuario', null, 'Funcionários ' . $rotulo, ['ids' => $ids, 'ativo' => $ativo]);
        fn_api_saida(['ok' => true, 'mensagem' => count($ids) . ' funcionário(s) ' . $rotulo . ' com sucesso!']);
    }

    fn_api_saida(['ok' => false, 'erro' => 'Ação inválida.'], 400);
} catch (PDOException $e) {
    error_log('API funcionarios: ' . $e->getMessage());
    fn_api_saida(['ok' => false, 'erro' => 'Não foi possível concluir a operação.'], 500);
}
