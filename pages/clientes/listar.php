<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);

$titulo_pagina = "Clientes";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="users" class="text-primary me-2"></i>Clientes</h1>
        <span class="page-subtitle">Gerencie os clientes da oficina</span>
    </div>
    <a href="cadastrar.php" class="btn btn-primary btn-icon">
        <i data-lucide="plus"></i> Novo Cliente
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="search-wrapper">
                    <i data-lucide="search" class="search-icon"></i>
                    <input type="text" class="form-control" id="buscarCliente" placeholder="Buscar por nome, CPF, telefone ou email...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tabelaClientes">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Cidade</th>
                        <th>Cadastro</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->query("SELECT * FROM clientes ORDER BY nome");
                    $clientes = $stmt->fetchAll();

                    if (count($clientes) > 0):
                        foreach ($clientes as $cliente):
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-primary text-white me-2">
                                    <?php echo strtoupper(substr($cliente['nome'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo limpar($cliente['nome']); ?></strong>
                                    <br>
                                    <small class="text-muted">Cliente #<?php echo $cliente['id']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo limpar($cliente['cpf']); ?></td>
                        <td><?php echo limpar($cliente['telefone']); ?></td>
                        <td><?php echo limpar($cliente['email']); ?></td>
                        <td><?php echo limpar($cliente['cidade'] ?: '-'); ?></td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('d/m/Y', strtotime($cliente['data_cadastro'])); ?>
                            </small>
                        </td>
                        <td class="actions-cell">
                            <!-- Ver veículos -->
                            <a href="detalhes.php?id=<?php echo $cliente['id']; ?>" class="btn btn-outline-info btn-sm" title="Cliente, veículos e histórico">
                                <i data-lucide="car"></i>
                            </a>
                            <!-- Editar -->
                            <a href="editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                                <i data-lucide="pencil"></i>
                            </a>
                            <!-- Excluir -->
                            <form method="POST" action="excluir.php" id="excluirCliente<?php echo $cliente['id']; ?>" class="d-inline" onsubmit="return confirmarExclusao('excluirCliente<?php echo $cliente['id']; ?>', 'Excluir o cliente <?php echo addslashes($cliente['nome']); ?>? Todos os dados vinculados serão removidos.');">
                                <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
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
                                <i data-lucide="users"></i>
                                <h4>Nenhum cliente cadastrado</h4>
                                <p>Clique em "Novo Cliente" para começar.</p>
                                <a href="cadastrar.php" class="btn btn-primary btn-icon">
                                    <i data-lucide="plus"></i> Cadastrar Cliente
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
    // Busca em tempo real
    document.getElementById('buscarCliente').addEventListener('keyup', function() {
        const termo = this.value.toLowerCase();
        const linhas = document.querySelectorAll('#tabelaClientes tbody tr');
        linhas.forEach(linha => {
            const texto = linha.textContent.toLowerCase();
            linha.style.display = texto.indexOf(termo) > -1 ? '' : 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
