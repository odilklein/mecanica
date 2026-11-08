<?php
$stats = $stats ?? [];
$categories = $categories ?? [];
$datatable = $datatable ?? [];
$configJson = json_encode($datatable, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$money = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$fmt = static fn (int $value): string => number_format($value, 0, ',', '.');
?>
<div class="page-header parts-page-header">
    <div>
        <h1><i class="icon-base ti tabler-package"></i>Catálogo de peças</h1>
        <span class="page-subtitle">Gerencie o catálogo importado, preços, estoque e categorias</span>
    </div>
    <div class="page-header-actions d-flex flex-wrap gap-2">
        <form method="POST" action="/parts/sync" onsubmit="return confirm('Sincronizar agora as peças com o catálogo Canal da Peça?');">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <button type="submit" class="btn btn-label-secondary">
                <i class="icon-base ti tabler-refresh"></i> Sincronizar catálogo
            </button>
        </form>
        <a href="/parts/export" class="btn btn-label-secondary">
            <i class="icon-base ti tabler-upload"></i> Exportar CSV
        </a>
        <a href="/parts/create" class="btn btn-primary">
            <i class="icon-base ti tabler-plus"></i> Nova peça
        </a>
    </div>
</div>

<!-- Widgets -->
<div class="card mb-6">
    <div class="card-widget-separator-wrapper">
        <div class="card-body card-widget-separator">
            <div class="row gy-4 gy-sm-1">
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start card-widget-1 border-end pb-4 pb-sm-0">
                        <div>
                            <p class="mb-1">Peças no catálogo</p>
                            <h4 class="mb-1"><?= $fmt((int) ($stats['total'] ?? 0)) ?></h4>
                            <p class="mb-0"><span class="badge bg-label-primary"><?= $fmt((int) ($stats['imported'] ?? 0)) ?> importadas</span></p>
                        </div>
                        <span class="avatar me-sm-6">
                            <span class="avatar-initial rounded"><i class="icon-base ti tabler-package icon-28px text-heading"></i></span>
                        </span>
                    </div>
                    <hr class="d-none d-sm-block d-lg-none me-6">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start card-widget-2 border-end pb-4 pb-sm-0">
                        <div>
                            <p class="mb-1">Publicadas</p>
                            <h4 class="mb-1"><?= $fmt((int) ($stats['published'] ?? 0)) ?></h4>
                            <p class="mb-0">visíveis no catálogo</p>
                        </div>
                        <span class="avatar p-2 me-lg-6">
                            <span class="avatar-initial rounded"><i class="icon-base ti tabler-circle-check icon-28px text-heading"></i></span>
                        </span>
                    </div>
                    <hr class="d-none d-sm-block d-lg-none">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0 card-widget-3">
                        <div>
                            <p class="mb-1">Estoque baixo</p>
                            <h4 class="mb-1"><?= $fmt((int) ($stats['low_stock'] ?? 0)) ?></h4>
                            <p class="mb-0"><span class="badge bg-label-warning">repor</span></p>
                        </div>
                        <span class="avatar p-2 me-sm-6">
                            <span class="avatar-initial rounded"><i class="icon-base ti tabler-alert-triangle icon-28px text-heading"></i></span>
                        </span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="mb-1">Valor em estoque</p>
                            <h4 class="mb-1"><?= $money((float) ($stats['stock_value'] ?? 0)) ?></h4>
                            <p class="mb-0">preço de venda</p>
                        </div>
                        <span class="avatar p-2">
                            <span class="avatar-initial rounded"><i class="icon-base ti tabler-wallet icon-28px text-heading"></i></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de peças -->
<div class="card">
    <div class="card-header border-bottom">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="card-title mb-0">Filtro</h5>
                <small class="text-body-secondary">Refine por status, categoria ou situação de estoque</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-label-danger" id="bulkDeleteParts" disabled>
                    <i class="icon-base ti tabler-trash"></i> Excluir selecionadas
                </button>
            </div>
        </div>
        <div class="row pt-4 g-3">
            <div class="col-md-4">
                <label class="form-label" for="partsFilterStatus">Status</label>
                <select class="form-select" id="partsFilterStatus" data-parts-filter="status">
                    <option value="">Todos os status</option>
                    <option value="published">Publicado</option>
                    <option value="draft">Rascunho</option>
                    <option value="inactive">Inativo</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="partsFilterCategory">Categoria</label>
                <select class="form-select" id="partsFilterCategory" data-parts-filter="category_id">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="partsFilterStock">Estoque</label>
                <select class="form-select" id="partsFilterStock" data-parts-filter="stock_state">
                    <option value="">Qualquer estoque</option>
                    <option value="in">Em estoque</option>
                    <option value="low">Estoque baixo</option>
                    <option value="out">Esgotado</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-datatable table-responsive">
        <table class="datatables-products table">
            <thead class="border-top">
                <tr>
                    <th></th>
                    <th></th>
                    <th>Peça</th>
                    <th>Categoria</th>
                    <th>Estoque</th>
                    <th>SKU</th>
                    <th>Preço</th>
                    <th>Qtd.</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>window.PARTS_DATATABLE = <?= $configJson ?>;</script>
