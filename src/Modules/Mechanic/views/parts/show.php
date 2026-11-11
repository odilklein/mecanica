<?php
$part = $part ?? [];
$offers = $offers ?? [];
$movements = $movements ?? [];
$money = static fn (mixed $value): string => $value !== null && $value !== ''
    ? 'R$ ' . number_format((float) $value, 2, ',', '.')
    : '—';
$oem = $part['oem_codes_list'] ?? [];
$tags = array_filter(array_map('trim', explode(',', (string) ($part['tags'] ?? ''))));
$description = (string) ($part['description'] ?? '');
$description = preg_replace('/<br\s*\/?>/i', "\n", $description) ?? $description;
$description = trim(strip_tags($description));
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-package"></i><?= e($part['name'] ?? 'Peça') ?></h1>
        <span class="page-subtitle">
            <?= e($part['brand'] ?: 'Sem marca') ?>
            <?php if (!empty($part['part_code'])): ?> · <?= e($part['part_code']) ?><?php endif; ?>
            <?php if (!empty($part['category_name'])): ?> · <?= e($part['category_name']) ?><?php endif; ?>
        </span>
    </div>
    <div class="page-header-actions">
        <a href="/parts" class="btn btn-label-secondary"><i class="icon-base ti tabler-arrow-left"></i> Catálogo</a>
        <a href="/parts/<?= (int) $part['id'] ?>/edit" class="btn btn-primary"><i class="icon-base ti tabler-edit"></i> Editar peça</a>
    </div>
</div>

<div class="row g-6">
    <div class="col-12 col-xl-4">
        <div class="card mb-6">
            <div class="card-body">
                <div class="part-show-image border rounded-3 d-flex align-items-center justify-content-center bg-label-secondary mb-4">
                    <div class="part-image-fallback text-center text-body-secondary">
                        <i class="icon-base ti tabler-photo-off icon-40px d-block mb-2"></i>
                        <small>Sem imagem</small>
                    </div>
                    <?php if (!empty($part['image'])): ?>
                    <img src="<?= e($part['image']) ?>" alt="<?= e($part['name']) ?>" class="img-fluid rounded-3 part-image-main"
                         loading="lazy" referrerpolicy="no-referrer"
                         onload="this.previousElementSibling.classList.add('d-none')"
                         onerror="this.remove()">
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-body">Status</span>
                    <span class="badge <?= e($part['status_class'] ?? 'bg-label-secondary') ?>"><?= e($part['status_label'] ?? '—') ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-body">Origem</span>
                    <span class="badge <?= ($part['source'] ?? '') === 'canaldapeca' ? 'bg-label-primary' : 'bg-label-secondary' ?>">
                        <?= ($part['source'] ?? '') === 'canaldapeca' ? 'Canal da Peça' : 'Manual' ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-body">SKU</span>
                    <span class="fw-medium"><?= e($part['sku_display'] ?: '—') ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-body">Código de barras</span>
                    <span class="fw-medium"><?= e($part['barcode'] ?: '—') ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-body">Atualizado em</span>
                    <span class="fw-medium"><?= !empty($part['updated_at']) ? e(date('d/m/Y H:i', strtotime((string) $part['updated_at']))) : '—' ?></span>
                </div>

                <?php if ($tags): ?>
                <hr class="my-4">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($tags as $tag): ?>
                    <span class="badge bg-label-primary"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header"><h5 class="card-title mb-0">Categoria</h5></div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <span class="avatar me-3">
                        <span class="avatar-initial rounded bg-label-<?= e($part['category_color'] ?? 'secondary') ?>">
                            <i class="icon-base ti tabler-<?= e($part['category_icon'] ?? 'package') ?> icon-22px"></i>
                        </span>
                    </span>
                    <div>
                        <h6 class="mb-0"><?= e($part['category_name'] ?: 'Sem categoria') ?></h6>
                        <small class="text-body-secondary"><?= e($part['category'] ?: '') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="row g-6 mb-6">
            <div class="col-sm-4">
                <div class="card h-100">
                    <div class="card-body">
                        <span class="text-body d-block mb-1">Preço de venda</span>
                        <h4 class="mb-1"><?= $money($part['price'] ?? null) ?></h4>
                        <small class="text-body-secondary"><?= number_format((float) ($part['margin'] ?? 0), 1, ',', '.') ?>% de margem</small>
                        <?php if (!empty($part['product_url'])): ?>
                        <a href="<?= e($part['product_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-label-primary w-100 mt-3">
                            <i class="icon-base ti tabler-external-link"></i> Abrir no Canal da Peça
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card h-100">
                    <div class="card-body">
                        <span class="text-body d-block mb-1">Estoque</span>
                        <h4 class="mb-1"><?= (int) ($part['stock'] ?? 0) ?> un.</h4>
                        <span class="badge <?= e($part['stock_class'] ?? 'bg-label-secondary') ?>"><?= e($part['stock_label'] ?? '—') ?></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card h-100">
                    <div class="card-body">
                        <span class="text-body d-block mb-1">Custo</span>
                        <h4 class="mb-1"><?= $money($part['cost_price'] ?? null) ?></h4>
                        <small class="text-body-secondary">mínimo: <?= (int) ($part['min_stock'] ?? 0) ?> un.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header"><h5 class="card-title mb-0">Descrição e aplicação</h5></div>
            <div class="card-body">
                <p class="mb-4"><?= nl2br(e($description !== '' ? $description : 'Sem descrição cadastrada.')) ?></p>
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="mb-2">Aplicação veicular</h6>
                        <p class="text-body-secondary mb-0"><?= nl2br(e($part['applications'] ?: 'Não informada.')) ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-2">Códigos OEM</h6>
                        <?php if ($oem): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($oem as $code): ?>
                            <span class="badge bg-label-secondary"><?= e($code) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-body-secondary mb-0">Nenhum código informado.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header"><h5 class="card-title mb-0">Fornecedor e catálogo</h5></div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <span class="text-body d-block mb-1">Fornecedor</span>
                        <h6 class="mb-1"><?= e($part['supplier'] ?: 'Não informado') ?></h6>
                        <?php if (!empty($part['supplier_url'])): ?>
                        <a href="<?= e($part['supplier_url']) ?>" target="_blank" rel="noopener" class="small">Site do fornecedor</a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <span class="text-body d-block mb-1">Localização</span>
                        <h6 class="mb-1"><?= e($part['location'] ?: '—') ?></h6>
                        <?php if (!empty($part['product_url'])): ?>
                        <a href="<?= e($part['product_url']) ?>" target="_blank" rel="noopener" class="small">
                            <i class="icon-base ti tabler-external-link"></i> Abrir no Canal da Peça
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($offers): ?>
                <hr class="my-4">
                <h6 class="mb-3">Ofertas do catálogo (<?= count($offers) ?>)</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr><th>Fornecedor</th><th>Cidade/UF</th><th class="text-end">Preço</th><th class="text-end">Estoque</th><th class="text-end">Prazo</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($offers as $offer): ?>
                            <tr>
                                <td><?= e($offer['seller_name'] ?: 'Fornecedor') ?></td>
                                <td><?= e(trim(($offer['city'] ?? '') . '/' . ($offer['state'] ?? ''), '/')) ?: '—' ?></td>
                                <td class="text-end"><?= $money($offer['price'] ?? null) ?></td>
                                <td class="text-end"><?= (int) ($offer['stock'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) ($offer['lead_time'] ?? 0) ?> d</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Movimentações de estoque</h5></div>
            <div class="card-body">
                <?php if ($movements): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr><th>Data</th><th>Tipo</th><th class="text-end">Qtd.</th><th class="text-end">Saldo</th><th>Motivo</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td><?= e(date('d/m/Y H:i', strtotime((string) $movement['created_at']))) ?></td>
                                <td>
                                    <?php if ($movement['type'] === 'in'): ?>
                                    <span class="badge bg-label-success">Entrada</span>
                                    <?php elseif ($movement['type'] === 'out'): ?>
                                    <span class="badge bg-label-danger">Saída</span>
                                    <?php else: ?>
                                    <span class="badge bg-label-info">Ajuste</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= (int) $movement['quantity'] > 0 ? '+' . (int) $movement['quantity'] : (int) $movement['quantity'] ?></td>
                                <td class="text-end"><?= (int) $movement['balance_after'] ?></td>
                                <td><?= e($movement['reason'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-body-secondary mb-0">Nenhuma movimentação registrada ainda.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
