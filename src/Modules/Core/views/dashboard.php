<?php $this->layout('core::layouts/app') ?>

<div class="page-header">
    <div>
        <h1 class="mb-1">Painel</h1>
        <span class="page-subtitle">Acompanhe o trabalho da oficina</span>
    </div>
</div>

<div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar">
                        <div class="avatar-initial rounded-2 bg-label-primary">
                            <i class="icon-base ti tabler-users"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <span class="text-heading d-block">Clientes</span>
                        <h5 class="mb-0">128</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar">
                        <div class="avatar-initial rounded-2 bg-label-success">
                            <i class="icon-base ti tabler-car"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <span class="text-heading d-block">Veículos</span>
                        <h5 class="mb-0">56</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar">
                        <div class="avatar-initial rounded-2 bg-label-warning">
                            <i class="icon-base ti tabler-clipboard-list"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <span class="text-heading d-block">OS em andamento</span>
                        <h5 class="mb-0">12</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar">
                        <div class="avatar-initial rounded-2 bg-label-danger">
                            <i class="icon-base ti tabler-package"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <span class="text-heading d-block">Peças pendentes</span>
                        <h5 class="mb-0">3</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Bem-vindo de volta!</h5>
    </div>
    <div class="card-body">
        <p class="mb-0">Você está autenticado como <strong><?= e($user['name']) ?></strong> (<?= e(roleLabel($user['role'] ?? null)) ?>).</p>
        <p class="mb-0 mt-2 text-body-secondary">Este é o shell do kernel modular. Os módulos (clientes, veículos, ordens de serviço...) serão ligados aqui.</p>
    </div>
</div>
