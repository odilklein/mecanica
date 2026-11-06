<?php $this->layout('core::layouts/app') ?>

<div class="page-header">
    <div>
        <h1 class="mb-1">Clientes</h1>
        <span class="page-subtitle"><?= e($total) ?> cliente(s) cadastrado(s)</span>
    </div>
    <a href="/clients/create" class="btn btn-primary"><i class="icon-base ti tabler-plus me-1"></i>Novo cliente</a>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="/clients" class="d-flex gap-2 w-100">
            <div class="input-group">
                <span class="input-group-text"><i class="icon-base ti tabler-search"></i></span>
                <input type="search" name="search" class="form-control" placeholder="Buscar por nome..." value="<?= e($search) ?>">
            </div>
            <button class="btn btn-outline-secondary" type="submit">Buscar</button>
        </form>
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Telefone</th>
                    <th>Email</th>
                    <th>Cidade</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $client): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-3">
                                <span class="avatar-initial rounded-circle bg-label-primary"><?= e(strtoupper(substr($client['name'], 0, 1))) ?></span>
                            </div>
                            <span class="fw-medium"><?= e($client['name']) ?></span>
                        </div>
                    </td>
                    <td><?= e($client['cpf'] ?: '—') ?></td>
                    <td><?= e($client['phone'] ?: '—') ?></td>
                    <td><?= e($client['email'] ?: '—') ?></td>
                    <td><?= e($client['city'] ?: '—') ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/clients/<?= (int) $client['id'] ?>/edit" class="btn btn-sm btn-icon btn-text-primary" title="Editar"><i class="icon-base ti tabler-pencil"></i></a>
                            <form method="POST" action="/clients/<?= (int) $client['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Excluir este cliente?')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button class="btn btn-sm btn-icon btn-text-danger" type="submit" title="Excluir"><i class="icon-base ti tabler-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (!$items): ?>
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="text-body-secondary"><i class="icon-base ti tabler-users icon-lg d-block mx-auto mb-2"></i>Nenhum cliente encontrado.</div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($lastPage > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination justify-content-end mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="/clients?page=<?= $page - 1 ?>&search=<?= e($search) ?>">Anterior</a>
                </li>
                <?php for ($i = 1; $i <= $lastPage; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="/clients?page=<?= $i ?>&search=<?= e($search) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
                    <a class="page-link" href="/clients?page=<?= $page + 1 ?>&search=<?= e($search) ?>">Próxima</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
