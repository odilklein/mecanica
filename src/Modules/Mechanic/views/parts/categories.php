<?php
$stats = $stats ?? [];
$categories = $categories ?? [];
$icons = $icons ?? [];
$colors = $colors ?? [];

$iconLabels = [
    'package' => 'Pacote', 'filter' => 'Filtro', 'engine' => 'Motor', 'circle-dot' => 'Freio',
    'arrows-vertical' => 'Suspensão', 'bolt' => 'Elétrica', 'temperature' => 'Arrefecimento',
    'settings' => 'Transmissão', 'circle' => 'Pneu', 'car' => 'Carroceria', 'bulb' => 'Iluminação',
    'star' => 'Acessórios', 'droplet' => 'Lubrificantes', 'tools' => 'Ferramentas',
    'packages' => 'Diversos', 'tool' => 'Oficina',
];
$colorLabels = [
    'primary' => 'Azul', 'secondary' => 'Cinza', 'success' => 'Verde', 'danger' => 'Vermelho',
    'warning' => 'Amarelo', 'info' => 'Ciano', 'dark' => 'Escuro',
];
$fmt = static fn (int $value): string => number_format($value, 0, ',', '.');
$money = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
?>
<div class="page-header">
    <div>
        <h1><i class="icon-base ti tabler-category"></i>Categorias de peças</h1>
        <span class="page-subtitle">Organize o catálogo e acompanhe o valor em estoque por categoria</span>
    </div>
    <div class="page-header-actions">
        <a href="/parts" class="btn btn-label-secondary"><i class="icon-base ti tabler-arrow-left"></i> Voltar ao catálogo</a>
        <button class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCategory">
            <i class="icon-base ti tabler-plus"></i> Nova categoria
        </button>
    </div>
</div>

<div class="card mb-6">
    <div class="card-widget-separator-wrapper">
        <div class="card-body card-widget-separator">
            <div class="row gy-4 gy-sm-1">
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start card-widget-1 border-end pb-4 pb-sm-0">
                        <div>
                            <p class="mb-1">Categorias</p>
                            <h4 class="mb-1"><?= $fmt((int) ($stats['total'] ?? 0)) ?></h4>
                            <p class="mb-0"><?= $fmt((int) ($stats['active'] ?? 0)) ?> ativas</p>
                        </div>
                        <span class="avatar me-sm-6"><span class="avatar-initial rounded"><i class="icon-base ti tabler-category icon-28px text-heading"></i></span></span>
                    </div>
                    <hr class="d-none d-sm-block d-lg-none me-6">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start card-widget-2 border-end pb-4 pb-sm-0">
                        <div>
                            <p class="mb-1">Peças categorizadas</p>
                            <h4 class="mb-1"><?= $fmt((int) ($stats['categorized'] ?? 0)) ?></h4>
                            <p class="mb-0">no catálogo</p>
                        </div>
                        <span class="avatar p-2 me-lg-6"><span class="avatar-initial rounded"><i class="icon-base ti tabler-checklist icon-28px text-heading"></i></span></span>
                    </div>
                    <hr class="d-none d-sm-block d-lg-none">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0 card-widget-3">
                        <div>
                            <p class="mb-1">Sem categoria</p>
                            <h4 class="mb-1"><?= $fmt((int) ($stats['uncategorized'] ?? 0)) ?></h4>
                            <p class="mb-0"><?= (int) ($stats['uncategorized'] ?? 0) > 0 ? 'precisam de atenção' : 'tudo organizado' ?></p>
                        </div>
                        <span class="avatar p-2 me-sm-6"><span class="avatar-initial rounded"><i class="icon-base ti tabler-alert-circle icon-28px text-heading"></i></span></span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="mb-1">Categorias com peças</p>
                            <h4 class="mb-1"><?= $fmt(count(array_filter($categories, static fn ($c) => (int) $c['total_products'] > 0))) ?></h4>
                            <p class="mb-0">com peças vinculadas</p>
                        </div>
                        <span class="avatar p-2"><span class="avatar-initial rounded"><i class="icon-base ti tabler-box icon-28px text-heading"></i></span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="card-title mb-0">Todas as categorias</h5>
            <small class="text-body-secondary">Clique em editar para alterar nome, ícone e cor</small>
        </div>
        <button class="btn btn-label-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCategory">
            <i class="icon-base ti tabler-plus"></i> Adicionar categoria
        </button>
    </div>
    <div class="card-datatable table-responsive">
        <table class="datatables-category-list table">
            <thead class="border-top">
                <tr>
                    <th></th>
                    <th>Categoria</th>
                    <th class="text-end">Produtos</th>
                    <th class="text-end">Valor em estoque</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                <?php
                    $categoryPayload = [
                        'id' => (int) $category['id'],
                        'name' => (string) $category['name'],
                        'slug' => (string) $category['slug'],
                        'description' => (string) ($category['description'] ?? ''),
                        'icon' => (string) $category['icon'],
                        'color' => (string) $category['color'],
                        'is_active' => (int) $category['is_active'],
                    ];
                ?>
                <tr>
                    <td></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar-wrapper me-3 rounded-2 bg-label-<?= e($category['color']) ?>">
                                <span class="avatar"><span class="avatar-initial rounded-2 bg-label-<?= e($category['color']) ?>"><i class="icon-base ti tabler-<?= e($category['icon']) ?> icon-20px"></i></span></span>
                            </span>
                            <div class="d-flex flex-column justify-content-center">
                                <span class="text-heading text-wrap fw-medium">
                                    <?= e($category['name']) ?>
                                    <?php if (!(int) $category['is_active']): ?>
                                    <span class="badge bg-label-secondary ms-2">inativa</span>
                                    <?php endif; ?>
                                    <?php if ((int) $category['low_stock'] > 0): ?>
                                    <span class="badge bg-label-warning ms-2" title="Peças com estoque baixo"><?= (int) $category['low_stock'] ?> repor</span>
                                    <?php endif; ?>
                                </span>
                                <small class="text-truncate d-none d-sm-block"><?= e($category['description'] ?: 'Sem descrição') ?></small>
                            </div>
                        </div>
                    </td>
                    <td class="text-end">
                        <span class="fw-medium"><?= $fmt((int) $category['total_products']) ?></span>
                        <small class="text-body-secondary d-block">peças</small>
                    </td>
                    <td class="text-end">
                        <span class="fw-medium"><?= $money((float) $category['total_value']) ?></span>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button" class="btn btn-icon btn-text-secondary rounded-pill category-edit"
                                    data-bs-toggle="offcanvas" data-bs-target="#offcanvasCategory"
                                    data-category="<?= e(json_encode($categoryPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"
                                    title="Editar categoria">
                                <i class="icon-base ti tabler-edit icon-22px"></i>
                            </button>
                            <form method="POST" action="/parts/categories/<?= (int) $category['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Excluir a categoria <?= e(addslashes($category['name'])) ?>? As peças ficarão sem categoria.');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn btn-icon btn-text-secondary rounded-pill text-danger" title="Excluir categoria">
                                    <i class="icon-base ti tabler-trash icon-22px"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Offcanvas: categoria -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasCategory" aria-labelledby="offcanvasCategoryTitle">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="offcanvasCategoryTitle">Nova categoria</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body p-6">
        <form method="POST" action="/parts/categories" id="categoryForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <div class="mb-4">
                <label class="form-label" for="categoryName">Nome *</label>
                <input type="text" class="form-control" id="categoryName" name="name" maxlength="120" required placeholder="Ex.: Freios">
            </div>
            <div class="mb-4">
                <label class="form-label" for="categorySlug">Slug</label>
                <input type="text" class="form-control" id="categorySlug" name="slug" maxlength="120" placeholder="gerado automaticamente">
                <div class="form-text">Usado em relatórios e integrações.</div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="categoryDescription">Descrição</label>
                <textarea class="form-control" id="categoryDescription" name="description" rows="3" placeholder="O que entra nesta categoria?"></textarea>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <label class="form-label" for="categoryIcon">Ícone</label>
                    <select class="form-select" id="categoryIcon" name="icon">
                        <?php foreach ($icons as $icon): ?>
                        <option value="<?= e($icon) ?>"><?= e($iconLabels[$icon] ?? $icon) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="categoryColor">Cor</label>
                    <select class="form-select" id="categoryColor" name="color">
                        <?php foreach ($colors as $color): ?>
                        <option value="<?= e($color) ?>"><?= e($colorLabels[$color] ?? $color) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-check form-switch mb-6">
                <input class="form-check-input" type="checkbox" role="switch" id="categoryActive" name="is_active" value="1" checked>
                <label class="form-check-label" for="categoryActive">Categoria ativa</label>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="offcanvas">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i> Salvar categoria</button>
            </div>
        </form>
    </div>
</div>
