<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$filtro_cliente = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;

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
        <div class="row mb-3 g-2">
            <div class="col-md-4">
                <div class="search-wrapper">
                    <i data-lucide="search" class="search-icon"></i>
                    <input type="text" class="form-control" id="buscarVeiculo" placeholder="Buscar por placa, marca, modelo...">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="filtroCliente" onchange="window.location.href='listar.php?cliente_id=' + this.value">
                    <option value="0">Todos os clientes</option>
                    <?php
                    $stmt_clientes = $conn->query("SELECT id, nome FROM clientes ORDER BY nome");
                    foreach ($stmt_clientes->fetchAll() as $c):
                        $selected = ($filtro_cliente == $c['id']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selected; ?>><?php echo limpar($c['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tabelaVeiculos">
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
                    <?php
                    if ($filtro_cliente > 0) {
                        $stmt = $conn->prepare("SELECT v.*, c.nome as cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id WHERE v.cliente_id = :cliente_id ORDER BY v.marca, v.modelo");
                        $stmt->bindParam(':cliente_id', $filtro_cliente);
                        $stmt->execute();
                    } else {
                        $stmt = $conn->query("SELECT v.*, c.nome as cliente_nome FROM veiculos v LEFT JOIN clientes c ON c.id = v.cliente_id ORDER BY v.marca, v.modelo");
                    }
                    $veiculos = $stmt->fetchAll();

                    if (count($veiculos) > 0):
                        foreach ($veiculos as $v):
                            $cor_style = !empty($v['cor']) ? 'style="background-color:' . htmlspecialchars($v['cor']) . '; width:18px; height:18px; display:inline-block; border-radius:3px; border:1px solid #ccc;"' : '';
                    ?>
                    <tr>
                        <td>
                            <i data-lucide="car" class="text-muted me-2"></i>
                            <strong><?php echo limpar($v['marca']); ?> <?php echo limpar($v['modelo']); ?></strong>
                        </td>
                        <td><span class="badge bg-dark"><?php echo limpar($v['placa']); ?></span></td>
                        <td><?php echo limpar($v['cliente_nome']); ?></td>
                        <td><?php echo $v['ano']; ?></td>
                        <td>
                            <?php if (!empty($v['cor'])): ?>
                                <span <?php echo $cor_style; ?>></span>
                                <span class="ms-1"><?php echo limpar($v['cor']); ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><?php echo number_format($v['km_atual'], 0, ',', '.'); ?> km</td>
                        <td class="actions-cell">
                            <a href="editar.php?id=<?php echo $v['id']; ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                                <i data-lucide="pencil"></i>
                            </a>
                            <form method="POST" action="excluir.php" id="excluirVeiculo<?php echo $v['id']; ?>" class="d-inline" onsubmit="return confirmarExclusao('excluirVeiculo<?php echo $v['id']; ?>', 'Excluir o veículo <?php echo $v['marca']; ?> <?php echo $v['modelo']; ?> - <?php echo $v['placa']; ?>?');">
                                <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
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
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.getElementById('buscarVeiculo').addEventListener('keyup', function() {
        const termo = this.value.toLowerCase();
        const linhas = document.querySelectorAll('#tabelaVeiculos tbody tr');
        linhas.forEach(linha => {
            const texto = linha.textContent.toLowerCase();
            linha.style.display = texto.indexOf(termo) > -1 ? '' : 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
