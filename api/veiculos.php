<?php
// =============================================
// API DE VEÍCULOS (JSON) - CRUD + AÇÕES EM MASSA
// -------------------------------------------------
// Consumida por js/crud.js (listagem moderna) e
// por chamadas legadas (?cliente_id= e ?placa=)
// usadas nos selects de OS/orçamento.
//
// Segurança:
//  - Sessão obrigatória.
//  - Papel restrito verificado aqui com resposta JSON.
//  - CSRF validado em todo POST (header X-CSRF-Token).
//  - Prepared statements em todas as consultas.
// =============================================

require_once dirname(__DIR__) . '/includes/sessao.php';
require_once dirname(__DIR__) . '/config/conexao.php';
require_once dirname(__DIR__) . '/includes/auditoria.php';
require_once dirname(__DIR__) . '/includes/fipe.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('vc_api_saida')) {
    function vc_api_saida(array $dados, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if (!function_exists('vc_api_veiculo')) {
    function vc_api_veiculo(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'cliente_id' => (int) $linha['cliente_id'],
            'cliente_nome' => (string) ($linha['cliente_nome'] ?? ''),
            'placa' => (string) ($linha['placa'] ?? ''),
            'marca' => (string) ($linha['marca'] ?? ''),
            'modelo' => (string) ($linha['modelo'] ?? ''),
            'ano' => (int) ($linha['ano'] ?? 0),
            'cor' => (string) ($linha['cor'] ?? ''),
            'cor_hex' => cor_para_hex($linha['cor'] ?? ''),
            'km_atual' => (int) ($linha['km_atual'] ?? 0),
            'chassi' => (string) ($linha['chassi'] ?? ''),
            'marca_id' => (int) ($linha['marca_id'] ?? 0),
            'modelo_base_id' => (int) ($linha['modelo_base_id'] ?? 0),
            'modelo_id' => (int) ($linha['modelo_id'] ?? 0),
            'valor_fipe' => $linha['valor_fipe'] !== null ? (float) $linha['valor_fipe'] : null,
            'fipe_mes_referencia' => (string) ($linha['fipe_mes_referencia'] ?? ''),
        ];
    }
}

if (!isset($_SESSION['usuario_id'])) {
    vc_api_saida(['ok' => false, 'erro' => 'Não autenticado.'], 401);
}
$papeis_permitidos = ['admin', 'secretaria', 'mecanico_chefe'];
if (!in_array($_SESSION['usuario_tipo'] ?? '', $papeis_permitidos, true)) {
    vc_api_saida(['ok' => false, 'erro' => 'Acesso não autorizado.'], 403);
}

fipe_garantir_schema($conn);

// ---------- Compatibilidade: consultas legadas (selects) ----------
if (isset($_GET['cliente_id']) && !isset($_GET['acao'])) {
    $stmt = $conn->prepare("SELECT id, placa, marca, modelo, ano, cor FROM veiculos WHERE cliente_id = :cliente_id ORDER BY marca, modelo");
    $stmt->bindParam(':cliente_id', $_GET['cliente_id']);
    $stmt->execute();
    vc_api_saida($stmt->fetchAll());
}
if (isset($_GET['placa']) && !isset($_GET['acao'])) {
    $stmt = $conn->prepare("SELECT v.*, c.nome as cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id WHERE v.placa = :placa");
    $stmt->bindParam(':placa', strtoupper($_GET['placa']));
    $stmt->execute();
    vc_api_saida($stmt->fetch() ?: null);
}

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

if ($metodo === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        vc_api_saida(['ok' => false, 'erro' => 'Requisição inválida.'], 403);
    }
}

if (!function_exists('vc_api_resolver_fipe')) {
    // Resolve marca/modelo/versão a partir dos IDs do catálogo FIPE.
    function vc_api_resolver_fipe(PDO $conn, int $marca_id, int $base_id, int $modelo_id): array
    {
        $marca = '';
        $modelo = '';
        $versao = null;

        if ($marca_id > 0) {
            $st = $conn->prepare("SELECT nome_exibicao FROM fipe_marcas WHERE id = :id");
            $st->execute([':id' => $marca_id]);
            $marca = (string) ($st->fetchColumn() ?: '');
        }
        if ($base_id > 0) {
            $st = $conn->prepare("SELECT nome_base FROM fipe_modelos_base WHERE id = :id");
            $st->execute([':id' => $base_id]);
            $modelo = (string) ($st->fetchColumn() ?: '');
        }
        if ($modelo_id > 0) {
            $st = $conn->prepare("SELECT nome_versao FROM fipe_modelos WHERE id = :id");
            $st->execute([':id' => $modelo_id]);
            $versao = $st->fetchColumn() ?: null;
        }

        return [$marca, $modelo, $versao];
    }
}

try {
    // =========================================
    // READ - listagem paginada
    // =========================================
    if ($acao === 'listar') {
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $cliente_id = (int) ($_GET['cliente_id'] ?? 0);
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $por_pagina = (int) ($_GET['por_pagina'] ?? 15);
        if (!in_array($por_pagina, [10, 15, 25, 50], true)) {
            $por_pagina = 15;
        }

        $condicoes = [];
        $params = [];
        if ($busca !== '') {
            $condicoes[] = '(v.placa LIKE :busca OR v.marca LIKE :busca OR v.modelo LIKE :busca OR c.nome LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }
        if ($cliente_id > 0) {
            $condicoes[] = 'v.cliente_id = :cliente_id';
            $params[':cliente_id'] = $cliente_id;
        }
        $where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

        $st = $conn->prepare("SELECT COUNT(*) FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $total_paginas = max(1, (int) ceil($total / $por_pagina));
        $pagina = min($pagina, $total_paginas);
        $offset = ($pagina - 1) * $por_pagina;

        $st = $conn->prepare("SELECT v.*, c.nome AS cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id
            $where ORDER BY v.marca, v.modelo LIMIT :limite OFFSET :offset");
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        vc_api_saida([
            'ok' => true,
            'itens' => array_map('vc_api_veiculo', $st->fetchAll()),
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total' => $total,
            'total_paginas' => $total_paginas,
        ]);
    }

    // =========================================
    // READ - um veículo
    // =========================================
    if ($acao === 'obter') {
        $id = (int) ($_GET['id'] ?? 0);
        $st = $conn->prepare("SELECT v.*, c.nome AS cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id WHERE v.id = :id");
        $st->execute([':id' => $id]);
        $linha = $st->fetch();
        if (!$linha) {
            vc_api_saida(['ok' => false, 'erro' => 'Veículo não encontrado.'], 404);
        }
        vc_api_saida(['ok' => true, 'item' => vc_api_veiculo($linha)]);
    }

    // =========================================
    // CREATE / UPDATE
    // =========================================
    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
        $placa = strtoupper(limpar($_POST['placa'] ?? ''));
        $ano = (int) ($_POST['ano'] ?? 0);
        $cor = limpar($_POST['cor'] ?? '');
        $km_atual = (int) ($_POST['km_atual'] ?? 0);
        $chassi = limpar($_POST['chassi'] ?? '');
        $marca_id = (int) ($_POST['marca_id'] ?? 0);
        $modelo_base_id = (int) ($_POST['modelo_base_id'] ?? 0);
        $modelo_id = (int) ($_POST['modelo_id'] ?? 0);

        $marca = limpar($_POST['marca_nome'] ?? '');
        $modelo = limpar($_POST['modelo_nome'] ?? '');

        // Catálogo FIPE tem prioridade sobre os campos de texto.
        if ($marca_id > 0 || $modelo_base_id > 0 || $modelo_id > 0) {
            [$m_fipe, $mo_fipe, $versao] = vc_api_resolver_fipe($conn, $marca_id, $modelo_base_id, $modelo_id);
            if ($m_fipe !== '') {
                $marca = $m_fipe;
            }
            if ($mo_fipe !== '') {
                $modelo = $mo_fipe;
            }
        } else {
            $versao = null;
        }

        if ($cliente_id <= 0 || $placa === '' || $marca === '' || $modelo === '') {
            vc_api_saida(['ok' => false, 'erro' => 'Preencha cliente, placa, marca e modelo.'], 422);
        }
        if (!validar_placa($placa)) {
            vc_api_saida(['ok' => false, 'erro' => 'Placa inválida. Use ABC-1234 ou ABC1D23 (Mercosul).'], 422);
        }
        if ($ano < 1886 || $ano > (int) date('Y') + 1) {
            vc_api_saida(['ok' => false, 'erro' => 'Ano do veículo inválido.'], 422);
        }

        $st = $conn->prepare("SELECT id FROM veiculos WHERE placa = :placa AND id <> :id");
        $st->execute([':placa' => $placa, ':id' => $id]);
        if ($st->fetch()) {
            vc_api_saida(['ok' => false, 'erro' => 'Já existe um veículo com esta placa.'], 409);
        }

        // Valor FIPE do catálogo, quando versão + ano estão definidos.
        $valor_fipe = null;
        $fipe_mes = null;
        if ($modelo_id > 0 && $ano > 0) {
            $fipe = fipe_valor_por_modelo_ano($conn, $modelo_id, $ano);
            if ($fipe) {
                $valor_fipe = $fipe['valor'];
                $fipe_mes = mb_substr($fipe['mes_referencia'], 0, 20);
            }
        }

        if ($id > 0) {
            $st = $conn->prepare("UPDATE veiculos SET
                cliente_id = :cliente_id, placa = :placa, marca = :marca, modelo = :modelo, ano = :ano,
                cor = :cor, km_atual = :km_atual, chassi = :chassi,
                marca_id = :marca_id, modelo_base_id = :modelo_base_id, modelo_id = :modelo_id,
                fipe_versao = :fipe_versao, valor_fipe = :valor_fipe, fipe_mes_referencia = :fipe_mes
                WHERE id = :id");
            $st->execute([
                ':cliente_id' => $cliente_id, ':placa' => $placa, ':marca' => $marca, ':modelo' => $modelo,
                ':ano' => $ano, ':cor' => $cor, ':km_atual' => $km_atual, ':chassi' => $chassi,
                ':marca_id' => $marca_id ?: null, ':modelo_base_id' => $modelo_base_id ?: null,
                ':modelo_id' => $modelo_id ?: null, ':fipe_versao' => $versao,
                ':valor_fipe' => $valor_fipe, ':fipe_mes' => $fipe_mes, ':id' => $id,
            ]);
            registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'editar', 'veiculo', $id, 'Veículo atualizado', ['placa' => $placa]);
            $mensagem = 'Veículo atualizado com sucesso!';
        } else {
            $st = $conn->prepare("INSERT INTO veiculos
                (cliente_id, placa, marca, modelo, ano, cor, km_atual, chassi,
                 marca_id, modelo_base_id, modelo_id, fipe_versao, valor_fipe, fipe_mes_referencia)
                VALUES (:cliente_id, :placa, :marca, :modelo, :ano, :cor, :km_atual, :chassi,
                 :marca_id, :modelo_base_id, :modelo_id, :fipe_versao, :valor_fipe, :fipe_mes)");
            $st->execute([
                ':cliente_id' => $cliente_id, ':placa' => $placa, ':marca' => $marca, ':modelo' => $modelo,
                ':ano' => $ano, ':cor' => $cor, ':km_atual' => $km_atual, ':chassi' => $chassi,
                ':marca_id' => $marca_id ?: null, ':modelo_base_id' => $modelo_base_id ?: null,
                ':modelo_id' => $modelo_id ?: null, ':fipe_versao' => $versao,
                ':valor_fipe' => $valor_fipe, ':fipe_mes' => $fipe_mes,
            ]);
            $id = (int) $conn->lastInsertId();
            registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'cadastrar', 'veiculo', $id, 'Veículo cadastrado', ['placa' => $placa]);
            $mensagem = 'Veículo cadastrado com sucesso!';
        }

        $st = $conn->prepare("SELECT v.*, c.nome AS cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id WHERE v.id = :id");
        $st->execute([':id' => $id]);
        vc_api_saida(['ok' => true, 'mensagem' => $mensagem, 'item' => vc_api_veiculo($st->fetch())]);
    }

    // =========================================
    // DELETE - um veículo
    // =========================================
    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            vc_api_saida(['ok' => false, 'erro' => 'Veículo inválido.'], 422);
        }
        $conn->prepare("DELETE FROM veiculos WHERE id = :id")->execute([':id' => $id]);
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'excluir', 'veiculo', $id, 'Veículo excluído');
        vc_api_saida(['ok' => true, 'mensagem' => 'Veículo excluído com sucesso!']);
    }

    // =========================================
    // DELETE em massa
    // =========================================
    if ($acao === 'excluir_lote') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($v) => $v > 0));
        if (!$ids) {
            vc_api_saida(['ok' => false, 'erro' => 'Nenhum veículo selecionado.'], 422);
        }
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $conn->prepare("DELETE FROM veiculos WHERE id IN ($marcadores)")->execute($ids);
        registrar_auditoria($conn, (int) $_SESSION['usuario_id'], 'excluir', 'veiculo', null, 'Veículos excluídos em massa', ['ids' => $ids]);
        vc_api_saida(['ok' => true, 'mensagem' => count($ids) . ' veículo(s) excluído(s) com sucesso!']);
    }

    vc_api_saida(['ok' => false, 'erro' => 'Ação inválida.'], 400);
} catch (PDOException $e) {
    error_log('API veiculos: ' . $e->getMessage());
    vc_api_saida(['ok' => false, 'erro' => 'Não foi possível concluir a operação.'], 500);
}
