<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../includes/paginacao.php';

exigir_papel(['admin', 'secretaria', 'mecanico_chefe', 'mecanico']);
$titulo_pagina = 'Ordens de serviço';
$status_nomes = ['recebida' => 'Recebida', 'triagem' => 'Triagem', 'aguardando_diagnostico' => 'Aguardando diagnóstico', 'aguardando_orcamento' => 'Aguardando orçamento', 'aguardando_aprovacao' => 'Aguardando aprovação', 'aguardando_peca' => 'Aguardando peça', 'pronta_para_execucao' => 'Pronta para execução', 'em_execucao' => 'Em execução', 'aguardando_retorno' => 'Aguardando retorno', 'pronta_para_entrega' => 'Pronta para entrega'];
$prioridades = ['urgente' => 'Urgente', 'alta' => 'Alta', 'normal' => 'Normal', 'baixa' => 'Baixa'];

// Mecânico enxerga apenas as OS atribuídas a ele
$filtro_funcionario = $_SESSION['usuario_tipo'] === 'mecanico'
    ? ' JOIN os_responsaveis r ON r.os_id = os.id AND r.usuario_id = ' . (int) $_SESSION['usuario_id'] . ' AND r.concluido_em IS NULL '
    : '';
$from = "FROM ordens_servico os JOIN clientes c ON c.id = os.cliente_id LEFT JOIN veiculos v ON v.id = os.veiculo_id $filtro_funcionario";

// ---------- Filtros (server-side) ----------
$busca = limpar($_GET['busca'] ?? '');
$prioridade = $_GET['prioridade'] ?? '';
$status = $_GET['status'] ?? '';

$condicoes = ["os.status NOT IN ('entregue', 'cancelada')"];
$params = [];
if ($busca !== '') {
    $condicoes[] = '(CAST(os.numero AS CHAR) LIKE :busca OR c.nome LIKE :busca OR v.placa LIKE :busca OR v.marca LIKE :busca OR v.modelo LIKE :busca OR os.queixa_inicial LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if (array_key_exists($prioridade, $prioridades)) {
    $condicoes[] = 'os.prioridade = :prioridade';
    $params[':prioridade'] = $prioridade;
}
if (array_key_exists($status, $status_nomes)) {
    $condicoes[] = 'os.status = :status';
    $params[':status'] = $status;
}
$where = 'WHERE ' . implode(' AND ', $condicoes);

// ---------- Paginação ----------
$por_pagina = por_pagina_atual();
$pagina = pagina_atual();

$st = $conn->prepare("SELECT COUNT(*) $from $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$pagina, $offset, $total_paginas] = paginacao_calcular($total, $por_pagina, $pagina);

$st = $conn->prepare("SELECT os.id, os.numero, os.status, os.prioridade, os.queixa_inicial, os.data_entrada, c.nome AS cliente, v.marca, v.modelo, v.placa
    $from $where
    ORDER BY FIELD(os.prioridade, 'urgente', 'alta', 'normal', 'baixa'), os.data_entrada ASC
    LIMIT :limite OFFSET :offset");
foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$ordens = $st->fetchAll();

$filtros_pag = array_filter(['busca' => $busca, 'prioridade' => $prioridade, 'status' => $status, 'por_pagina' => $por_pagina], fn($v) => $v !== '');
$tem_filtro = $busca !== '' || $prioridade !== '' || $status !== '';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header">
    <div>
        <h1><i data-lucide="clipboard-list" class="text-primary me-2"></i>Ordens de serviço</h1>
        <span class="page-subtitle">Fila operacional da oficina</span>
    </div>
    <?php if (tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?><a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="plus"></i>Novo atendimento</a><?php endif; ?>
</div>
<div class="card">
    <div class="card-body p-0">
        <form class="filter-bar mb-0 rounded-bottom-0" method="get" action="listar.php">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" name="busca" value="<?php echo esc($busca); ?>" placeholder="Buscar por OS, cliente, veículo, placa ou queixa...">
            </div>
            <div class="filter-group">
                <label for="filtroPrioridade">Prioridade</label>
                <select class="form-select" id="filtroPrioridade" name="prioridade" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($prioridades as $key => $label): ?>
                        <option value="<?php echo esc($key); ?>" <?php echo $prioridade === $key ? 'selected' : ''; ?>><?php echo esc($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroStatusOS">Status</label>
                <select class="form-select" id="filtroStatusOS" name="status" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($status_nomes as $key => $label): ?>
                        <option value="<?php echo esc($key); ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo esc($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroPorPagina">Por página</label>
                <select class="form-select" id="filtroPorPagina" name="por_pagina" onchange="this.form.submit()">
                    <?php echo opcoes_por_pagina($por_pagina); ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit"><i data-lucide="search"></i> Filtrar</button>
            <a class="btn btn-outline-secondary btn-reset-filters" href="listar.php"><i data-lucide="filter-x"></i> Limpar</a>
        </form>

        <div class="px-3 pt-3 filter-results-info">
            <span>Exibindo <strong><?php echo count($ordens); ?></strong> de <strong><?php echo $total; ?></strong> ordens de serviço<?php echo $tem_filtro ? ' (filtradas)' : ''; ?></span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 responsive-card">
                <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Queixa</th><th>Prioridade</th><th>Status</th><th>Entrada</th></tr></thead>
                <tbody>
                <?php foreach ($ordens as $ordem): ?>
                    <tr>
                        <td data-label="OS"><a href="detalhes.php?id=<?php echo (int) $ordem['id']; ?>"><strong>#<?php echo str_pad((int) $ordem['numero'], 4, '0', STR_PAD_LEFT); ?></strong></a></td>
                        <td data-label="Cliente"><?php echo esc($ordem['cliente']); ?></td>
                        <td data-label="Veículo"><?php echo esc(trim(($ordem['marca'] ?? '') . ' ' . ($ordem['modelo'] ?? '')) ?: 'Não informado'); ?><br><small class="text-muted"><?php echo esc($ordem['placa'] ?: ''); ?></small></td>
                        <td data-label="Queixa"><span title="<?php echo esc($ordem['queixa_inicial']); ?>"><?php echo esc(mb_strimwidth($ordem['queixa_inicial'], 0, 55, '...')); ?></span></td>
                        <td data-label="Prioridade"><span class="badge badge-prioridade-<?php echo esc($ordem['prioridade']); ?>"><?php echo esc(ucfirst($ordem['prioridade'])); ?></span></td>
                        <td data-label="Status"><span class="badge badge-status-<?php echo esc($ordem['status']); ?>"><?php echo esc($status_nomes[$ordem['status']] ?? $ordem['status']); ?></span></td>
                        <td data-label="Entrada"><small><?php echo date('d/m/Y H:i', strtotime($ordem['data_entrada'])); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ordens): ?>
                    <tr class="row-empty"><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i data-lucide="<?php echo $tem_filtro ? 'filter' : 'clipboard-list'; ?>"></i></div>
                            <h4><?php echo $tem_filtro ? 'Nenhuma ordem encontrada' : 'Nenhuma ordem de serviço em aberto'; ?></h4>
                            <p><?php echo $tem_filtro ? 'Ajuste a busca ou limpe os filtros para ver todas as ordens de serviço.' : 'Crie um novo atendimento para começar a acompanhar o trabalho da oficina.'; ?></p>
                            <?php if (!$tem_filtro && tem_papel(['admin', 'secretaria', 'mecanico_chefe'])): ?>
                            <a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="plus"></i> Novo atendimento</a>
                            <?php endif; ?>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php echo paginacao_links($pagina, $total_paginas, $filtros_pag); ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
