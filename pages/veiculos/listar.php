<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$stmt_clientes = $conn->query("SELECT id, nome FROM clientes ORDER BY nome");
$clientes_opcoes = $stmt_clientes->fetchAll();

$stmt = $conn->query("SELECT v.*, c.nome as cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id ORDER BY v.marca, v.modelo");
$veiculos = $stmt->fetchAll();

$titulo_pagina = "Veículos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="car" class="text-primary me-2"></i>Veículos</h1>
        <span class="page-subtitle">Gerencie os veículos dos clientes</span>
    </div>
    <a href="cadastrar.php" class="btn btn-primary btn-icon">
        <i data-lucide="plus"></i> Novo Veículo
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="filter-bar">
            <div class="filter-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" class="form-control" id="buscarVeiculo" data-filtro-busca="tabelaVeiculos" placeholder="Buscar por placa, marca, modelo, cliente...">
            </div>
            <div class="filter-group">
                <label for="filtroCliente">Cliente</label>
                <select class="form-select" id="filtroCliente" data-filtro-tabela="tabelaVeiculos" data-filtro-coluna="Cliente">
                    <option value="">Todos</option>
                    <?php foreach ($clientes_opcoes as $c): ?>
                        <option value="<?php echo esc($c['nome']); ?>"><?php echo limpar($c['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-outline-secondary btn-reset-filters" type="button" onclick="limparFiltros('tabelaVeiculos')">
                <i data-lucide="filter-x"></i> Limpar
            </button>
        </div>

        <div class="filter-results-info">
            <span>Exibindo <strong data-filtro-total="tabelaVeiculos"><?php echo count($veiculos); ?></strong> de <?php echo count($veiculos); ?> veículos</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle responsive-card" id="tabelaVeiculos">
                <thead>
                    <tr>
                        <th>Veículo</th>
                        <th>Placa</th>
                        <th>Cliente</th>
                        <th>Ano</th>
                        <th>Cor</th>
                        <th>KM Atual</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($veiculos) > 0): foreach ($veiculos as $v): ?>
                    <tr>
                        <td data-label="Veículo">
                            <i data-lucide="car" class="text-muted me-2"></i>
                            <strong><?php echo limpar($v['marca']); ?> <?php echo limpar($v['modelo']); ?></strong>
                        </td>
                        <td data-label="Placa"><span class="badge bg-dark"><?php echo limpar($v['placa']); ?></span></td>
                        <td data-label="Cliente"><?php echo limpar($v['cliente_nome']); ?></td>
                        <td data-label="Ano"><?php echo $v['ano']; ?></td>
                        <td data-label="Cor">
                            <?php
                            $cor_hex = cor_para_hex($v['cor']);
                            if ($cor_hex):
                            ?>
                                <span class="color-badge" style="background-color:<?php echo esc($cor_hex); ?>" title="<?php echo esc($v['cor']); ?>"></span>
                                <span class="ms-1"><?php echo limpar($v['cor']); ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td data-label="KM Atual"><?php echo number_format($v['km_atual'], 0, ',', '.'); ?> km</td>
                        <td data-label="Ações" class="actions-cell">
                            <div class="dropdown table-dropdown">
                                <button class="table-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do veículo">
                                    <i data-lucide="more-horizontal"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end table-dropdown-menu">
                                    <h6 class="dropdown-header">Ações do veículo</h6>
                                    <a class="dropdown-item" href="editar.php?id=<?php echo $v['id']; ?>">
                                        <i data-lucide="pencil"></i> Editar
                                    </a>
                                    <form method="POST" action="excluir.php" id="excluirVeiculo<?php echo $v['id']; ?>" onsubmit="return confirmarExclusao('excluirVeiculo<?php echo $v['id']; ?>', 'Excluir o veículo <?php echo $v['marca']; ?> <?php echo $v['modelo']; ?> - <?php echo $v['placa']; ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i data-lucide="trash-2"></i> Excluir
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr class="row-empty">
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="car"></i></div>
                                <h4>Nenhum veículo cadastrado</h4>
                                <p>Cadastre o primeiro veículo da oficina para começar.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Cadastrar Veículo
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="row-filtro-vazio" data-filtro-vazio="tabelaVeiculos" style="display:none;">
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="filter"></i></div>
                                <h4>Nenhum veículo encontrado com esses filtros</h4>
                                <p>Ajuste a busca ou limpe os filtros para ver todos os veículos.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>