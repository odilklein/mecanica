// =============================================
// PEDIDO DE PEÇAS — itens no estilo do wizard da OS
// (cards com imagem, dados do catálogo, preço,
// quantidade e link para o produto).
// =============================================
(function () {
    'use strict';

    var PAGE_SIZE = 5;

    function boot() {
        var box = document.querySelector('[data-list="part-order-items"]');
        if (!box) {
            return;
        }

        var form = document.getElementById('formPartOrder');
        var items = box.querySelector('.dynamic-list-items');
        var target = document.getElementById('items_json');
        var countEl = document.getElementById('partOrderItemsCount');
        var pager = document.getElementById('partOrderItemsPager');
        var pagerInfo = document.getElementById('partOrderItemsInfo');
        var pagerPrev = document.getElementById('partOrderItemsPrev');
        var pagerNext = document.getElementById('partOrderItemsNext');
        if (!form || !items || !target) {
            return;
        }

        var currentPage = 1;
        var pendingItem = null;

        var quickModalEl = document.getElementById('partQuickViewModal');
        var quickName = document.getElementById('quickViewName');
        var quickMeta = document.getElementById('quickViewMeta');
        var quickPrice = document.getElementById('quickViewPrice');
        var quickLink = document.getElementById('quickViewLink');
        var quickImage = document.getElementById('quickViewImage');
        var quickDescription = document.getElementById('quickViewDescription');
        var quickDescriptionWrap = document.getElementById('quickViewDescriptionWrap');
        var quickApplications = document.getElementById('quickViewApplications');
        var quickApplicationsWrap = document.getElementById('quickViewApplicationsWrap');
        var quickSku = document.getElementById('quickViewSku');
        var quickSkuWrap = document.getElementById('quickViewSkuWrap');
        var quickBarcode = document.getElementById('quickViewBarcode');
        var quickBarcodeWrap = document.getElementById('quickViewBarcodeWrap');
        var quickQty = document.getElementById('quickViewQty');
        var quickAdd = document.getElementById('quickViewAdd');

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

        function applyPage() {
            var cards = Array.prototype.slice.call(items.children);
            var total = cards.length;
            var totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
            currentPage = Math.min(Math.max(1, currentPage), totalPages);

            cards.forEach(function (card, index) {
                card.classList.toggle('d-none', Math.floor(index / PAGE_SIZE) !== currentPage - 1);
            });

            if (pager) {
                pager.classList.toggle('d-none', total <= PAGE_SIZE);
            }
            if (pagerInfo) {
                pagerInfo.textContent = total + (total === 1 ? ' item' : ' itens')
                    + ' · página ' + currentPage + ' de ' + totalPages;
            }
            if (pagerPrev) {
                pagerPrev.disabled = currentPage <= 1;
            }
            if (pagerNext) {
                pagerNext.disabled = currentPage >= totalPages;
            }
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
            var isCatalog = meta.catalog === true
                || !!(meta.sku || meta.brand || meta.part_code || meta.category || meta.catalog_notes);
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
                applyPage();
            });

            refreshPrice();
            items.appendChild(row);
            sync();
            currentPage = Math.max(1, Math.ceil(items.children.length / PAGE_SIZE));
            applyPage();
            return row;
        }

        function openQuickView(item) {
            pendingItem = item;
            var name = item.name || item.text || 'Peça';
            var metaParts = [item.brand, item.part_code, item.category].filter(Boolean);

            if (quickName) {
                quickName.textContent = name;
            }
            if (quickMeta) {
                quickMeta.textContent = metaParts.length ? metaParts.join(' · ') : 'Peça do catálogo';
            }
            if (quickPrice) {
                quickPrice.textContent = item.price_label
                    || (item.price != null ? money(item.price) : 'Sob consulta');
            }

            var productUrl = window.safeUrl ? window.safeUrl(item.product_url) : '';
            if (quickLink) {
                if (productUrl) {
                    quickLink.href = productUrl;
                    quickLink.classList.remove('d-none');
                } else {
                    quickLink.href = '#';
                    quickLink.classList.add('d-none');
                }
            }

            if (quickDescription) {
                quickDescription.textContent = item.description || '';
            }
            if (quickDescriptionWrap) {
                quickDescriptionWrap.classList.toggle('d-none', !item.description);
            }
            if (quickApplications) {
                quickApplications.textContent = item.applications || '';
            }
            if (quickApplicationsWrap) {
                quickApplicationsWrap.classList.toggle('d-none', !item.applications);
            }
            if (quickSku) {
                quickSku.textContent = item.sku || '';
            }
            if (quickSkuWrap) {
                quickSkuWrap.classList.toggle('d-none', !item.sku);
            }
            if (quickBarcode) {
                quickBarcode.textContent = item.barcode || '';
            }
            if (quickBarcodeWrap) {
                quickBarcodeWrap.classList.toggle('d-none', !item.barcode);
            }

            if (quickImage) {
                quickImage.innerHTML = '';
                var image = window.safeUrl ? window.safeUrl(item.image) : '';
                if (image) {
                    var img = document.createElement('img');
                    img.src = image;
                    img.alt = name;
                    img.className = 'img-fluid rounded-2';
                    img.loading = 'lazy';
                    img.referrerPolicy = 'no-referrer';
                    img.onerror = function () {
                        this.remove();
                        var fallback = document.createElement('span');
                        fallback.className = 'fw-semibold text-primary';
                        fallback.textContent = initials(name);
                        quickImage.appendChild(fallback);
                    };
                    quickImage.appendChild(img);
                } else {
                    var fallback = document.createElement('span');
                    fallback.className = 'fw-semibold text-primary';
                    fallback.textContent = initials(name);
                    quickImage.appendChild(fallback);
                }
            }

            if (quickQty) {
                quickQty.value = '1';
            }

            if (quickModalEl && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(quickModalEl).show();
            } else {
                // Fallback sem Bootstrap: adiciona direto.
                addRow(name, 1, Object.assign({}, item, { catalog: true }));
            }
        }

        // Catálogo: seleciona a peça e abre o quick view.
        if (window.PartAutocomplete) {
            window.PartAutocomplete.init('#partOrderSearch', openQuickView);
        }

        if (quickAdd) {
            quickAdd.addEventListener('click', function () {
                if (!pendingItem) {
                    return;
                }
                var quantity = Math.max(1, Math.min(99, parseInt(quickQty && quickQty.value, 10) || 1));
                addRow(pendingItem.name || pendingItem.text, quantity, Object.assign({}, pendingItem, { catalog: true }));
                pendingItem = null;
                if (quickModalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(quickModalEl).hide();
                }
            });
        }

        if (quickModalEl) {
            quickModalEl.addEventListener('hidden.bs.modal', function () {
                pendingItem = null;
            });
        }

        // Peça digitada manualmente: entra com quantidade 1 (ajustável no card).
        var manualInput = document.getElementById('partOrderManual');
        var manualAdd = document.getElementById('partOrderManualAdd');

        function addManual() {
            var text = manualInput ? manualInput.value.trim() : '';
            if (!text) {
                return;
            }
            addRow(text, 1, {});
            if (manualInput) {
                manualInput.value = '';
                manualInput.focus();
            }
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

        if (pagerPrev) {
            pagerPrev.addEventListener('click', function () {
                currentPage--;
                applyPage();
            });
        }
        if (pagerNext) {
            pagerNext.addEventListener('click', function () {
                currentPage++;
                applyPage();
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
                    name: item.description || '',
                    catalog: !!(item.sku || item.brand || item.part_code || item.category || item.catalog_notes)
                });
            });
        }
        currentPage = 1;
        applyPage();

        // Veículo da OS selecionada (ajuda a secretaria a fazer o pedido correto).
        var orderSelect = document.getElementById('service_order_id');
        var orderDetails = window.PART_ORDER_ORDERS || {};
        var vehicleTitle = document.getElementById('partOrderVehicleTitle');
        var vehicleInfo = document.getElementById('partOrderVehicleInfo');

        function updateVehicle() {
            if (!orderSelect) {
                return;
            }
            var info = orderDetails[String(orderSelect.value)] || null;
            if (!vehicleTitle || !vehicleInfo) {
                return;
            }
            if (!info) {
                vehicleTitle.textContent = 'Selecione a OS';
                vehicleInfo.textContent = 'O veículo da OS aparecerá aqui.';
                return;
            }

            var number = String(info.number || '').padStart(4, '0');
            vehicleTitle.textContent = 'OS #' + number + (info.client ? ' · ' + info.client : '');

            var parts = [
                info.plate,
                info.brand,
                info.model,
                info.year,
                info.color,
                info.km != null ? Number(info.km).toLocaleString('pt-BR') + ' km' : ''
            ].filter(function (value) { return value !== undefined && value !== null && value !== ''; });

            vehicleInfo.textContent = parts.length ? parts.join(' · ') : 'Veículo não informado.';
        }

        if (orderSelect) {
            orderSelect.addEventListener('change', updateVehicle);
            if (window.jQuery) {
                // Select2 dispara via jQuery: cobre troca programática e seleção.
                window.jQuery(orderSelect).on('change select2:select', updateVehicle);
            }
            updateVehicle();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
