<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';
require_once __DIR__ . '/../../includes/crud.php';
exigir_papel(['admin']);

$entidade = limpar($_GET['entidade'] ?? '');
$acao = limpar($_GET['acao'] ?? '');
$busca = limpar($_GET['busca'] ?? '');
$pagina = pagina_atual();
$por_pagina = 15;

$condicoes = ['1=1'];
$params = [];
if ($entidade !== '') { $condicoes[] = 'a.entidade = :entidade'; $params[':entidade'] = $entidade; }
if ($acao !== '') { $condicoes[] = 'a.acao = :acao'; $params[':acao'] = $acao; }
if ($busca !== '') { $condicoes[] = 'u.nome LIKE :busca'; $params[':busca'] = '%' . $busca . '%'; }
$where = implode(' AND ', $condicoes);

$st = $conn->prepare("SELECT COUNT(*) FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$pagina, $offset, $total_paginas] = paginacao_calcular($total, $por_pagina, $pagina);

$st = $conn->prepare("SELECT a.id, a.entidade, a.entidade_id, a.acao, a.dados, a.criado_em, u.nome AS usuario
    FROM auditoria a
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    WHERE $where
    ORDER BY a.id DESC
    LIMIT :limite OFFSET :offset");
$st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->execute();
$registros = $st->fetchAll();

$entidades = $conn->query('SELECT DISTINCT entidade FROM auditoria ORDER BY entidade')->fetchAll(PDO::FETCH_COLUMN);
$acoes = $conn->query('SELECT DISTINCT acao FROM auditoria ORDER BY acao')->fetchAll(PDO::FETCH_COLUMN);
$filtros_pag = array_filter(['acao' => $acao, 'entidade' => $entidade, 'busca' => $busca], fn($v) => $v !== '');

$titulo_pagina = 'Auditoria';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';

$opcoes_acao = ['' => 'Ação: todas'];
foreach ($acoes as $a) { $opcoes_acao[$a] = $a; }
$opcoes_entidade = ['' => 'Entidade: todas'];
foreach ($entidades as $e) { $opcoes_entidade[$e] = $e; }
?>
<div class="page-header">
    <div>
        <h1>Auditoria</h1>
        <span class="page-subtitle">Registro de ações do sistema</span>
    </div>
</div>

<div class="card">
    <form id="filtrosForm" method="get" action="listar.php"><button type="submit" hidden>Filtrar</button></form>
    <?php
    $filtros = '<select class="form-select" name="acao" form="filtrosForm" onchange="document.getElementById(\'filtrosForm\').submit()">';
    foreach ($opcoes_acao as $valor => $rotulo) {
        $filtros .= '<option value="' . esc($valor) . '"' . ($acao === $valor ? ' selected' : '') . '>' . esc($rotulo) . '</option>';
    }
    $filtros .= '</select>';
    $filtros .= '<select class="form-select" name="entidade" form="filtrosForm" onchange="document.getElementById(\'filtrosForm\').submit()">';
    foreach ($opcoes_entidade as $valor => $rotulo) {
        $filtros .= '<option value="' . esc($valor) . '"' . ($entidade === $valor ? ' selected' : '') . '>' . esc($rotulo) . '</option>';
    }
    $filtros .= '</select>';
    echo '<div class="table-panel"><div class="crud-toolbar">'
        . '<div class="crud-search"><i class="icon-base ti tabler-search"></i>'
        . '<input type="search" name="busca" form="filtrosForm" class="form-control" placeholder="Buscar por usuário..." value="' . esc($busca) . '" autocomplete="off"></div>'
        . $filtros
        . '</div></div>';
    ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle responsive-card">
            <thead>
                <tr><th>Data</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>Registro</th><th>Detalhes</th></tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $r): ?>
                <tr>
                    <td data-label="Data"><small class="text-body-secondary"><?php echo date('d/m/Y H:i', strtotime($r['criado_em'])); ?></small></td>
                    <td data-label="Usuário"><?php echo esc($r['usuario'] ?: 'Sistema'); ?></td>
                    <td data-label="Ação"><span class="badge bg-label-secondary"><?php echo esc($r['acao']); ?></span></td>
                    <td data-label="Entidade"><?php echo esc($r['entidade']); ?></td>
                    <td data-label="Registro">#<?php echo (int) $r['entidade_id']; ?></td>
                    <td data-label="Detalhes"><?php echo $r['dados'] ? '<small class="text-body-secondary">' . esc($r['dados']) . '</small>' : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$registros): ?>
                <tr class="row-empty"><td colspan="6"><?php echo crud_empty_state('history', 'Nenhum registro', 'Nenhuma ação de auditoria encontrada com os filtros atuais.'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo dt_rodape($pagina, $total, $por_pagina, $total_paginas, $filtros_pag, 'listar.php', 'registros'); ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
