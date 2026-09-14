<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';
exigir_papel(['admin']);

$entidade = limpar($_GET['entidade'] ?? '');
$acao = limpar($_GET['acao'] ?? '');
$busca = limpar($_GET['busca'] ?? '');
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pagina = 30;
$offset = ($pagina - 1) * $por_pagina;

$condicoes = ['1=1'];
$params = [];
if ($entidade !== '') { $condicoes[] = 'a.entidade = :entidade'; $params[':entidade'] = $entidade; }
if ($acao !== '') { $condicoes[] = 'a.acao = :acao'; $params[':acao'] = $acao; }
if ($busca !== '') { $condicoes[] = 'u.nome LIKE :busca'; $params[':busca'] = '%' . $busca . '%'; }
$where = implode(' AND ', $condicoes);

$st = $conn->prepare("SELECT COUNT(*) FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
$total_paginas = max(1, (int) ceil($total / $por_pagina));
if ($pagina > $total_paginas) $pagina = $total_paginas;

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
?>
<div class="page-header"><div><h1><i data-lucide="history" class="text-primary me-2"></i>Auditoria</h1><span class="page-subtitle">Registro das ações sensíveis do sistema</span></div></div>
<div class="card"><div class="card-body p-0">
<div class="filter-bar mb-0 rounded-bottom-0">
    <div class="filter-search">
        <i data-lucide="search" class="search-icon"></i>
        <input type="text" class="form-control" id="buscaAuditoria" placeholder="Buscar por usuário..." value="<?php echo esc($busca); ?>">
    </div>
    <div class="filter-group">
        <label for="filtroAcao">Ação</label>
        <select class="form-select" id="filtroAcao">
            <option value="">Todas</option>
            <?php foreach ($acoes as $a): ?>
            <option value="<?php echo esc($a); ?>" <?php echo $acao === $a ? 'selected' : ''; ?>><?php echo esc($a); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label for="filtroEntidade">Entidade</label>
        <select class="form-select" id="filtroEntidade">
            <option value="">Todas</option>
            <?php foreach ($entidades as $e): ?>
            <option value="<?php echo esc($e); ?>" <?php echo $entidade === $e ? 'selected' : ''; ?>><?php echo esc($e); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="window.location.href='listar.php'"><i data-lucide="filter-x"></i> Limpar</button>
</div>
<div class="px-3 pt-3 filter-results-info"><span>Exibindo <strong><?php echo count($registros); ?></strong> de <?php echo $total; ?> registros</span></div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0 responsive-card">
<thead><tr><th>Data</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>Registro</th><th>Detalhes</th></tr></thead>
<tbody>
<?php foreach ($registros as $r): ?>
<tr>
    <td data-label="Data"><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($r['criado_em'])); ?></small></td>
    <td data-label="Usuário"><?php echo esc($r['usuario'] ?: 'Sistema'); ?></td>
    <td data-label="Ação"><span class="badge bg-secondary"><?php echo esc($r['acao']); ?></span></td>
    <td data-label="Entidade"><?php echo esc($r['entidade']); ?></td>
    <td data-label="Registro">#<?php echo (int) $r['entidade_id']; ?></td>
    <td data-label="Detalhes"><?php echo $r['dados'] ? '<small class="text-muted">' . esc($r['dados']) . '</small>' : '-'; ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$registros): ?><tr class="row-empty"><td colspan="6"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="history"></i></div><h4>Nenhum registro</h4><p>Nenhuma ação de auditoria encontrada com os filtros atuais.</p></div></td></tr><?php endif; ?>
</tbody></table></div>
<?php echo paginacao_links($pagina, $total_paginas, $filtros_pag); ?>
</div></div>
<script>
(function () {
    function recarregar() {
        var busca = document.getElementById('buscaAuditoria').value;
        var acao = document.getElementById('filtroAcao').value;
        var entidade = document.getElementById('filtroEntidade').value;
        window.location.href = 'listar.php?acao=' + encodeURIComponent(acao) + '&entidade=' + encodeURIComponent(entidade) + '&busca=' + encodeURIComponent(busca);
    }
    var busca = document.getElementById('buscaAuditoria');
    var debounce = null;
    busca.addEventListener('keydown', function (e) { if (e.key === 'Enter') recarregar(); });
    busca.addEventListener('input', function () { clearTimeout(debounce); debounce = setTimeout(recarregar, 600); });
    document.getElementById('filtroAcao').addEventListener('change', recarregar);
    document.getElementById('filtroEntidade').addEventListener('change', recarregar);
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
