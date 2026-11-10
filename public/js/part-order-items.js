// =============================================
// PEDIDO DE PEÇAS — itens no estilo do wizard da OS
// (cards com imagem, dados do catálogo, preço,
// quantidade e link para o produto).
// =============================================
(function () {
    'use strict';

    function boot() {
        var box = document.querySelector('[data-list="part-order-items"]');
        if (!box) {
            return;
        }

        var form = document.getElementById('formPartOrder');
        var items = box.querySelector('.dynamic-list-items');
        var target = document.getElementById('items_json');
        var qtyInput = document.getElementById('partOrderSearchQty');
        var countEl = document.getElementById('partOrderItemsCount');
        if (!form || !items || !target) {
            return;
        }

        function initials(name) {
            return (String(name || '').match(/\b\w/g) || [])
                .slice(0, 2)
                .map(function (part) { return part.toUpperCase(); })
                .join('') || '?';
        }

        function money(value) {
            return 'R$ ' + Number(value).toFixed(2)
                .replace('.', ',')
                .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function sync() {
            var list = Array.prototype.map.call(items.children, function (row) {
                var qty = row.querySelector('.item-qty');
                return {
                    description: row.querySelector('.item-text').textContent,
                    quantity: Math.max(1, parseInt(qty && qty.value, 10) || 1),
                    sku: row.dataset.sku || null,
                    unit_price: row.dataset.unitPrice !== '' ? parseFloat(row.dataset.unitPrice) : null,
                    supplier: null,
                    product_url: row.dataset.productUrl || null,
                    catalog_notes: row.dataset.catalogNotes || null
                };
            });
            target.value = JSON.stringify(list);
            if (countEl) {
                countEl.textContent = String(items.children.length);
            }
        }

        function addRow(text, quantity, meta) {
            meta = meta || {};
            text = (text || '').trim();
            if (!text) {
                return null;
            }

            var qty = Math.max(1, parseInt(quantity, 10) || 1);
            var row = document.createElement('div');
            row.className = 'os-part-card os-wizard-part';
            row.innerHTML = '<div class="os-part-thumb"></div>'
                + '<div class="os-part-body">'
                + '<div class="d-flex justify-content-between align-items-start gap-2">'
                + '<div class="min-w-0"><div class="os-part-name item-text"></div>'
                + '<small class="os-part-desc catalog-info text-body-secondary d-block"></small></div>'
                + '<button type="button" class="btn btn-sm btn-icon btn-text-danger item-remove" title="Remover peça">'
                + '<i class="icon-base ti tabler-x"></i></button>'
                + '</div>'
                + '<div class="d-flex justify-content-between align-items-center gap-2 mt-2">'
                + '<div class="d-flex align-items-center gap-1"><span class="small text-body-secondary">Qtd</span>'
                + '<input type="number" class="form-control form-control-sm item-qty" min="1" step="1" value="' + qty + '" style="max-width: 76px;" aria-label="Quantidade"></div>'
                + '<span class="fw-semibold item-price text-nowrap"></span>'
                + '</div></div>';

            var thumb = row.querySelector('.os-part-thumb');
            var image = window.safeUrl ? window.safeUrl(meta.image) : '';
            if (image) {
                var img = document.createElement('img');
                img.src = image;
                img.alt = text;
                img.loading = 'lazy';
                img.referrerPolicy = 'no-referrer';
                img.onerror = function () {
                    this.hidden = true;
                    var fallback = document.createElement('span');
                    fallback.className = 'avatar-initial rounded-2 bg-label-primary';
                    fallback.textContent = initials(text);
                    thumb.appendChild(fallback);
                };
                thumb.appendChild(img);
            } else {
                var badge = document.createElement('span');
                badge.className = 'avatar-initial rounded-2 bg-label-primary';
                badge.textContent = initials(text);
                thumb.appendChild(badge);
            }

            row.querySelector('.item-text').textContent = text;

            var info = row.querySelector('.catalog-info');
            var metaParts = [meta.brand, meta.part_code, meta.category].filter(Boolean);
            var isCatalog = !!(meta.name || meta.sku || meta.brand || meta.part_code || meta.category);
            info.textContent = metaParts.length
                ? metaParts.join(' · ')
                : (meta.catalog_notes || (isCatalog ? 'Peça do catálogo' : 'Peça manual'));

            var productUrl = window.safeUrl ? window.safeUrl(meta.product_url) : '';
            if (productUrl) {
                var link = document.createElement('a');
                link.href = productUrl;
                link.target = '_blank';
                link.rel = 'noopener';
                link.className = 'ms-1 text-primary text-decoration-none';
                link.title = 'Abrir no Canal da Peça';
                link.innerHTML = '<i class="icon-base ti tabler-external-link"></i>';
                info.appendChild(link);
            }

            var unitPrice = meta.price != null ? Number(meta.price) : null;

            row.dataset.sku = meta.sku || '';
            row.dataset.unitPrice = unitPrice != null && !isNaN(unitPrice) ? String(unitPrice) : '';
            row.dataset.productUrl = productUrl || '';
            row.dataset.catalogNotes = metaParts.length ? metaParts.join(' · ') : (meta.catalog_notes || '');

            var priceEl = row.querySelector('.item-price');
            var qtyEl = row.querySelector('.item-qty');

            function refreshPrice() {
                var currentQty = Math.max(1, parseInt(qtyEl.value, 10) || 1);
                priceEl.textContent = unitPrice != null && !isNaN(unitPrice) ? money(unitPrice * currentQty) : '—';
            }

            qtyEl.addEventListener('input', function () {
                refreshPrice();
                sync();
            });
            row.querySelector('.item-remove').addEventListener('click', function () {
                row.remove();
                sync();
            });

            refreshPrice();
            items.appendChild(row);
            sync();
            return row;
        }

        function setQuantity(value) {
            var v = Math.max(1, Math.min(99, parseInt(value, 10) || 1));
            if (qtyInput) {
                qtyInput.value = String(v);
            }
        }

        // Catálogo com autocomplete.
        if (window.PartAutocomplete) {
            window.PartAutocomplete.init('#partOrderSearch', function (item) {
                var quantity = Math.max(1, parseInt(qtyInput && qtyInput.value, 10) || 1);
                addRow(item.name || item.text, quantity, item);
                setQuantity(1);
            });
        }

        // Peça digitada manualmente.
        var manualInput = document.getElementById('partOrderManual');
        var manualAdd = document.getElementById('partOrderManualAdd');

        function addManual() {
            var text = manualInput ? manualInput.value.trim() : '';
            if (!text) {
                return;
            }
            var quantity = Math.max(1, parseInt(qtyInput && qtyInput.value, 10) || 1);
            addRow(text, quantity, {});
            if (manualInput) {
                manualInput.value = '';
                manualInput.focus();
            }
            setQuantity(1);
        }

        if (manualAdd) {
            manualAdd.addEventListener('click', addManual);
        }
        if (manualInput) {
            manualInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addManual();
                }
            });
        }

        form.addEventListener('submit', sync);

        // Itens já salvos (edição).
        var initial = window.PART_ORDER_ITEMS || [];
        if (Array.isArray(initial)) {
            initial.forEach(function (item) {
                addRow(item.description || '', item.quantity || 1, {
                    sku: item.sku,
                    price: item.unit_price,
                    product_url: item.product_url,
                    catalog_notes: item.catalog_notes,
                    brand: item.brand,
                    part_code: item.part_code,
                    category: item.category,
                    image: item.image,
                    name: item.description || ''
                });
            });
        }
        sync();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
