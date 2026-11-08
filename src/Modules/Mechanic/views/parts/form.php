<?php
$isEdit = $record !== null;
$action = $isEdit ? '/parts/' . (int) $record['id'] : '/parts';
$old = $old ?? [];
$errors = $errors ?? [];
$categories = $categories ?? [];
$statuses = $statuses ?? [];
$offers = $offers ?? [];
$movements = $movements ?? [];

$val = static fn (string $key, mixed $default = ''): mixed => $old[$key] ?? $default;
$err = static fn (string $key): ?string => $errors[$key] ?? null;
$invalid = static fn (string $key): string => !empty($errors[$key]) ? ' is-invalid' : '';
$image = (string) ($old['image'] ?? '');
$descriptionValue = (string) ($old['description'] ?? '');
$descriptionValue = preg_replace('/<br\s*\/?>/i', "\n", $descriptionValue) ?? $descriptionValue;
$descriptionValue = trim(strip_tags($descriptionValue));
$margin = ((float) ($old['price'] ?? 0)) > 0
    ? (((float) $old['price'] - (float) ($old['cost_price'] ?? 0)) / (float) $old['price']) * 100
    : 0.0;
?>
<form method="POST" action="<?= e($action) ?>" id="formPart">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="page-header">
        <div>
            <h1><i class="icon-base ti tabler-package"></i><?= e($title) ?></h1>
            <span class="page-subtitle">Preencha os dados comerciais, aplicação, estoque e precificação da peça</span>
        </div>
        <div class="page-header-actions">
            <a href="<?= $isEdit ? '/parts/' . (int) $record['id'] : '/parts' ?>" class="btn btn-label-secondary">
                <i class="icon-base ti tabler-arrow-left"></i> Voltar
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="icon-base ti tabler-device-floppy"></i> Salvar peça
            </button>
        </div>
    </div>

    <?php if (!empty($errors['_form'])): ?>
    <div class="alert alert-danger" role="alert"><?= e($errors['_form']) ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Coluna principal -->
        <div class="col-12 col-lg-8">
            <!-- Informações -->
            <div class="card mb-6">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informações da peça</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label" for="partName">Nome *</label>
                        <input type="text" class="form-control<?= $invalid('name') ?>" id="partName" name="name" maxlength="200" required
                               value="<?= e($val('name')) ?>" placeholder="Ex.: Pastilha de freio dianteira">
                        <?php if ($err('name')): ?><div class="invalid-feedback d-block"><?= e($err('name')) ?></div><?php endif; ?>
                    </div>
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <label class="form-label" for="partBrand">Marca</label>
                            <input type="text" class="form-control" id="partBrand" name="brand" maxlength="120" value="<?= e($val('brand')) ?>" placeholder="Ex.: Cobreq">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="partCode">Código da peça</label>
                            <input type="text" class="form-control" id="partCode" name="part_code" maxlength="80" value="<?= e($val('part_code')) ?>" placeholder="Ex.: N1728">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="partSku">SKU</label>
                            <input type="text" class="form-control" id="partSku" name="sku" maxlength="80" value="<?= e($val('sku')) ?>" placeholder="Ex.: 2015670">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="partBarcode">Código de barras</label>
                            <input type="text" class="form-control" id="partBarcode" name="barcode" maxlength="80" value="<?= e($val('barcode')) ?>" placeholder="Ex.: 7891234567890">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="partTags">Tags</label>
                            <input type="text" class="form-control" id="partTags" name="tags" maxlength="255" value="<?= e($val('tags')) ?>" placeholder="Ex.: original, linha leve, promoção">
                            <div class="form-text">Separe as tags por vírgula.</div>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="partDescription">Descrição</label>
                        <textarea class="form-control" id="partDescription" name="description" rows="4" placeholder="Detalhes técnicos, composição, observações..."><?= e($descriptionValue) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Imagem -->
            <div class="card mb-6">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0">Imagem da peça</h5>
                    <small class="text-body-secondary">Produtos importados já vêm com a imagem do catálogo</small>
                </div>
                <div class="card-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-md-8">
                            <label class="form-label" for="partImage">URL da imagem</label>
                            <input type="url" class="form-control" id="partImage" name="image" maxlength="500"
                                   value="<?= e($image) ?>" placeholder="https://exemplo.com/imagem.jpg" data-image-input>
                            <div class="form-text">Use uma URL pública (JPG, PNG ou WebP).</div>
                        </div>
                        <div class="col-md-4">
                            <div class="part-image-preview border rounded-3 d-flex align-items-center justify-content-center bg-label-secondary" data-image-preview>
                                <?php if ($image !== ''): ?>
                                <img src="<?= e($image) ?>" alt="Prévia da peça" class="img-fluid rounded-2">
                                <?php else: ?>
                                <span class="text-body-secondary small">Sem imagem</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aplicação -->
            <div class="card mb-6">
                <div class="card-header">
                    <h5 class="card-title mb-0">Aplicação veicular</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label" for="partApplications">Aplicações</label>
                        <textarea class="form-control" id="partApplications" name="applications" rows="3"
                                  placeholder="Ex.: Gol 1.0 2014-2020, Saveiro 1.6 2018+"><?= e($val('applications')) ?></textarea>
                        <div class="form-text">Informe modelos, anos e motorizações compatíveis.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="partOem">Códigos OEM</label>
                        <textarea class="form-control" id="partOem" name="oem_codes" rows="3"
                                  placeholder="Um código por linha ou separado por vírgula"><?= e($val('oem_codes_text')) ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="partUniversal" name="universal" value="1" <?= !empty($val('universal')) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="partUniversal">Peça universal</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="partByRequest" name="by_request" value="1" <?= !empty($val('by_request')) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="partByRequest">Sob consulta</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="partByBudget" name="by_budget" value="1" <?= !empty($val('by_budget')) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="partByBudget">Sob orçamento</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="partWeight">Peso (kg)</label>
                            <input type="number" class="form-control" id="partWeight" name="weight" step="0.001" min="0" value="<?= e($val('weight')) ?>" placeholder="0.000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="partWarranty">Garantia (meses)</label>
                            <input type="number" class="form-control" id="partWarranty" name="warranty_months" min="0" step="1" value="<?= e($val('warranty_months')) ?>" placeholder="Ex.: 3">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estoque -->
            <div class="card mb-6">
                <div class="card-header">
                    <h5 class="card-title mb-0">Estoque</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label" for="partStock">Quantidade</label>
                            <input type="number" class="form-control" id="partStock" name="stock" min="0" step="1" value="<?= e($val('stock', 0)) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="partMinStock">Estoque mínimo</label>
                            <input type="number" class="form-control" id="partMinStock" name="min_stock" min="0" step="1" value="<?= e($val('min_stock', 0)) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="partLocation">Localização</label>
                            <input type="text" class="form-control" id="partLocation" name="location" maxlength="80" value="<?= e($val('location')) ?>" placeholder="Ex.: Prateleira A3">
                        </div>
                    </div>

                    <?php if ($isEdit && $movements): ?>
                    <hr class="my-5">
                    <h6 class="mb-3">Últimas movimentações</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Coluna lateral -->
        <div class="col-12 col-lg-4">
            <!-- Preço -->
            <div class="card mb-6">
                <div class="card-header">
                    <h5 class="card-title mb-0">Precificação</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label" for="partPrice">Preço de venda (R$)</label>
                        <input type="number" class="form-control<?= $invalid('price') ?>" id="partPrice" name="price" step="0.01" min="0"
                               value="<?= e($val('price')) ?>" placeholder="0,00" data-price-input>
                        <?php if ($err('price')): ?><div class="invalid-feedback d-block"><?= e($err('price')) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="partCostSide">Custo (R$)</label>
                        <input type="number" class="form-control<?= $invalid('cost_price') ?>" id="partCostSide" name="cost_price" step="0.01" min="0"
                               value="<?= e($val('cost_price')) ?>" placeholder="0,00" data-cost-side>
                        <?php if ($err('cost_price')): ?><div class="invalid-feedback d-block"><?= e($err('cost_price')) ?></div><?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <span class="text-body">Margem estimada</span>
                        <span class="badge bg-label-primary fs-6" data-margin><?= number_format($margin, 1, ',', '.') ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Organização -->
            <div class="card mb-6">
                <div class="card-header">
                    <h5 class="card-title mb-0">Organização</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label" for="partCategory">Categoria</label>
                        <select class="form-select select2" id="partCategory" name="category_id" data-placeholder="Selecione a categoria">
                            <option value="">Sem categoria</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (string) $val('category_id') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="text-body-secondary">Organize o catálogo por tipo de peça.</small>
                            <a href="/parts/categories" class="small">Gerenciar</a>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="partStatus">Status</label>
                        <select class="form-select" id="partStatus" name="status">
                            <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) $val('status', 'published') === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="partSupplier">Fornecedor</label>
                        <input type="text" class="form-control" id="partSupplier" name="supplier" maxlength="160" value="<?= e($val('supplier')) ?>" placeholder="Ex.: Auto Peças Silva">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="partSupplierUrl">Site do fornecedor</label>
                        <input type="url" class="form-control" id="partSupplierUrl" name="supplier_url" maxlength="500" value="<?= e($val('supplier_url')) ?>" placeholder="https://...">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="partProductUrl">Link do produto</label>
                        <input type="url" class="form-control" id="partProductUrl" name="product_url" maxlength="500" value="<?= e($val('product_url')) ?>" placeholder="https://...">
                    </div>
                    <div>
                        <label class="form-label" for="partNotes">Observações internas</label>
                        <textarea class="form-control" id="partNotes" name="notes" rows="2" placeholder="Visível apenas para a equipe"><?= e($val('notes')) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Catálogo -->
            <div class="card mb-6">
                <div class="card-header">
                    <h5 class="card-title mb-0">Catálogo Canal da Peça</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-body">Origem</span>
                        <span class="badge bg-label-<?= ($val('source') === 'canaldapeca') ? 'primary' : 'secondary' ?>"><?= $val('source') === 'canaldapeca' ? 'Importada' : 'Manual' ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-body">SKU do catálogo</span>
                        <span class="fw-medium"><?= e($val('cdp_sku') ?: '—') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-body">Ofertas encontradas</span>
                        <span class="fw-medium"><?= count($offers) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-4">
                        <span class="text-body">Última sincronização</span>
                        <span class="fw-medium"><?= $val('last_synced_at') ? e(date('d/m/Y H:i', strtotime((string) $val('last_synced_at')))) : '—' ?></span>
                    </div>

                    <?php if ($offers): ?>
                    <h6 class="mb-2">Melhores ofertas</h6>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($offers, 0, 4) as $offer): ?>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-medium"><?= e($offer['seller_name'] ?: 'Fornecedor') ?></div>
                                <small class="text-body-secondary"><?= e(trim(($offer['city'] ?? '') . '/' . ($offer['state'] ?? ''), '/')) ?: '—' ?> · <?= (int) $offer['stock'] ?> un.</small>
                            </div>
                            <span class="badge bg-label-success">R$ <?= number_format((float) $offer['price'], 2, ',', '.') ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($val('product_url'))): ?>
                    <a href="<?= e($val('product_url')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-label-primary w-100 mt-3">
                        <i class="icon-base ti tabler-external-link"></i> Ver no catálogo
                    </a>
                    <?php endif; ?>
                    <?php elseif ($val('source') === 'canaldapeca'): ?>
                    <p class="text-body-secondary mb-0">Nenhuma oferta armazenada. Use “Sincronizar catálogo” na listagem.</p>
                    <?php else: ?>
                    <p class="text-body-secondary mb-0">Peça manual. Peças importadas podem ser sincronizadas com o Canal da Peça.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon-base ti tabler-device-floppy"></i> Salvar peça</button>
                <a href="<?= $isEdit ? '/parts/' . (int) $record['id'] : '/parts' ?>" class="btn btn-label-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
