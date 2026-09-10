<?php
require_once __DIR__ . '/../../includes/sessao.php';
require_once __DIR__ . '/../../config/conexao.php';
exigir_papel(['admin', 'secretaria', 'mecanico_chefe']);
$titulo_pagina = 'Funcionários';
$funcionarios = $conn->query("SELECT id, nome, email, tipo, cargo, telefone, salario, data_admissao, ativo FROM usuarios ORDER BY ativo DESC, nome")->fetchAll();
$roles = ['admin' => 'Administrador', 'secretaria' => 'Secretaria', 'mecanico_chefe' => 'Mecânico chefe', 'mecanico' => 'Mecânico'];
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/mensagens.php';
?>
<div class="page-header"><div><h1><i data-lucide="users-round" class="text-primary me-2"></i>Funcionários</h1><span class="page-subtitle">Gerencie pessoas, cargos e permissões</span></div><a href="cadastrar.php" class="btn btn-primary btn-icon"><i data-lucide="user-plus"></i>Novo funcionário</a></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Funcionário</th><th>Cargo / perfil</th><th>Telefone</th><th>Salário</th><th>Na empresa desde</th><th>Status</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($funcionarios as $funcionario): ?><tr><td><strong><?php echo esc($funcionario['nome']); ?></strong><br><small class="text-muted"><?php echo esc($funcionario['email']); ?></small></td><td><?php echo esc($funcionario['cargo'] ?: ($roles[$funcionario['tipo']] ?? $funcionario['tipo'])); ?><br><small class="text-muted"><?php echo esc($roles[$funcionario['tipo']] ?? $funcionario['tipo']); ?></small></td><td><?php echo esc($funcionario['telefone'] ?: '-'); ?></td><td><?php echo $funcionario['salario'] !== null ? formatar_moeda($funcionario['salario']) : '-'; ?></td><td><?php echo $funcionario['data_admissao'] ? date('d/m/Y', strtotime($funcionario['data_admissao'])) : '-'; ?></td><td><span class="badge <?php echo $funcionario['ativo'] ? 'badge-success' : 'badge-secondary'; ?>"><?php echo $funcionario['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td><td><a class="btn btn-outline-primary btn-sm" href="editar.php?id=<?php echo (int) $funcionario['id']; ?>" title="Editar"><i data-lucide="pencil"></i></a></td></tr><?php endforeach; ?>
<?php if (!$funcionarios): ?><tr><td colspan="7" class="text-center py-5 text-muted">Nenhum funcionário cadastrado.</td></tr><?php endif; ?></tbody></table></div></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
