<?php
$path = $resource->path();
$clientId = (int) $client['id'];
$Status = \Modules\Mechanic\Support\ServiceOrderStatus::class;
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-user"></i><?= e($client['name']) ?></h1>
        <span class="page-subtitle">Dados, veículos e histórico de atendimentos</span>
    </div>
    <div class="page-header-actions">
        <a href="/vehicles/create" class="btn btn-primary"><i class="icon-base ti tabler-car"></i> Novo veículo</a>
        <a href="<?= e($path) ?>" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-address-book"></i><div><h3 class="panel-title">Contato</h3></div></div>
            <p class="mb-2"><small class="text-body-secondary d-block">Telefone</small><?= e($client['phone'] ?: '—') ?></p>
            <p class="mb-2"><small class="text-body-secondary d-block">E-mail</small><?= e($client['email'] ?: '—') ?></p>
            <p class="mb-0"><small class="text-body-secondary d-block">Endereço</small><?= e(trim(($client['address'] ?? '') . ' ' . ($client['address_number'] ?? '') . ' ' . ($client['neighborhood'] ?? '') . ' ' . ($client['city'] ?? '') . ' ' . ($client['state'] ?? '')) ?: '—') ?></p>
        </div></div>

        <div class="card"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-car"></i><div><h3 class="panel-title">Veículos</h3></div></div>
            <?php foreach ($vehicles as $v): ?>
            <div class="border-bottom pb-2 mb-2">
                <strong><?= e(trim(($v['brand'] ?? '') . ' ' . ($v['model'] ?? '')) ?: 'Veículo') ?></strong><br>
                <small class="text-body-secondary"><?= e($v['plate'] ?: 'Placa não informada') ?><?= $v['year'] ? ' · ' . (int) $v['year'] : '' ?></small>
                <?php if (!empty($v['fipe_value'])): ?>
                <br><small class="text-body-secondary">FIPE: R$ <?= number_format((float) $v['fipe_value'], 2, ',', '.') ?></small>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$vehicles): ?><span class="text-body-secondary">Nenhum veículo cadastrado.</span><?php endif; ?>
        </div></div>
    </div>

    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <div class="panel-header"><i class="icon-base ti tabler-history"></i><div><h3 class="panel-title">Ordens de serviço</h3></div></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle responsive-card mb-0">
                    <thead><tr><th>OS</th><th>Veículo</th><th>Queixa</th><th>Data</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                        <tr>
                            <td data-label="OS"><a href="/service-orders/<?= (int) $o['id'] ?>">#<?= str_pad((string) $o['number'], 4, '0', STR_PAD_LEFT) ?></a></td>
                            <td data-label="Veículo"><?= e(trim(($o['model'] ?? '') . ' ' . ($o['plate'] ?? '')) ?: '—') ?></td>
                            <td data-label="Queixa"><?= e(mb_strimwidth((string) $o['initial_complaint'], 0, 70, '...')) ?></td>
                            <td data-label="Data"><?= $o['entry_date'] ? e(date('d/m/Y', strtotime($o['entry_date']))) : '—' ?></td>
                            <td data-label="Status"><span class="badge <?= e($Status::badgeClass($o['status'])) ?>"><?= e($Status::label($o['status'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$orders): ?>
                        <tr><td colspan="5" class="text-center text-body-secondary py-5">Nenhuma ordem de serviço.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
