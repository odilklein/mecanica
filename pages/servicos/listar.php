<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';

$titulo_pagina = "Serviços";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-tools text-primary me-2"></i>Serviços</h1>
        <span class="page-subtitle">Tipos de serviços oferecidos pela oficina</span>
    </div>
    <a href="cadastrar.php" class="btn btn-primary btn-icon">
        <i class="fas fa-plus"></i> Novo Serviço
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="form-control" id="buscarServico" placeholder="Buscar serviço...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tabelaServicos">
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Descrição</th>
                        <th>Preço de Mão de Obra</th>
                        <th>Tempo Estimado</th>
                        <th>Status</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->query("SELECT * FROM servicos ORDER BY nome");
                    $servicos = $stmt->fetchAll();

                    if (count($servicos) > 0):
                        foreach ($servicos as $s):
                    ?>
                    <tr>
                        <td><strong><?php echo limpar($s['nome']); ?></strong></td>
                        <td><?php echo limpar(substr($s['descricao'] ?: '-', 0, 60)); ?></td>
                        <td class="fw-600 text-success"><?php echo formatar_moeda($s['preco_mao_obra']); ?></td>
                        <td>
                            <?php
                            $horas = floor($s['tempo_estimado'] / 60);
                            $min = $s['tempo_estimado'] % 60;
                            if ($horas > 0) echo $horas . 'h ';
                            if ($min > 0) echo $min . 'min';
                            if ($s['tempo_estimado'] == 0) echo '-';
                            ?>
                        </td>
                        <td>
                            <?php if ($s['ativo']): ?>
                                <span class="badge badge-success">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="editar.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="excluir.php" id="excluirServico<?php echo $s['id']; ?>" class="d-inline" onsubmit="return confirmarExclusao('excluirServico<?php echo $s['id']; ?>', 'Excluir o serviço <?php echo addslashes($s['nome']); ?>?');">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-tools"></i>
                                <h4>Nenhum serviço cadastrado</h4>
                                <p>Cadastre os serviços que a oficina oferece para usar nos orçamentos.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i class="fas fa-plus"></i> Cadastrar Serviço
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
    document.getElementById('buscarServico').addEventListener('keyup', function() {
        const termo = this.value.toLowerCase();
        const linhas = document.querySelectorAll('#tabelaServicos tbody tr');
        linhas.forEach(linha => {
            const texto = linha.textContent.toLowerCase();
            linha.style.display = texto.indexOf(termo) > -1 ? '' : 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
