// =============================================
// PEDIDO DE PEÇAS — itens dinâmicos + autocomplete
// (descrição + quantidade; catálogo sugere nome/preço)
// =============================================
(function () {
    'use strict';

    function boot() {
        var form = document.getElementById('formPartOrder');
        var body = document.getElementById('partOrderItems');
        var addBtn = document.getElementById('addPartItem');
        var hidden = document.getElementById('items_json');
        if (!form || !body || !addBtn || !hidden) {
            return;
        }

        function metaText(meta) {
            if (!meta) {
                return '';
            }
            var parts = [meta.brand, meta.part_code];
            if (!meta.brand && !meta.part_code && meta.catalog_notes) {
                parts.push(meta.catalog_notes);
            }
            if (meta.price_label) {
                parts.push(meta.price_label);
            } else if (meta.price != null) {
                parts.push('R$ ' + Number(meta.price).toFixed(2).replace('.', ','));
            }
            return parts.filter(function (v) { return v !== undefined && v !== null && v !== ''; }).join(' · ');
        }

        function addRow(description, quantity, meta) {
            meta = meta || {};
            var tr = document.createElement('tr');

            var tdDesc = document.createElement('td');
            tdDesc.setAttribute('data-label', 'Descrição');
            var desc = document.createElement('input');
            desc.type = 'text';
            desc.className = 'form-control item-description';
            desc.placeholder = 'Ex.: Pastilha de freio';
            desc.maxLength = 200;
            desc.value = description || '';
            tdDesc.appendChild(desc);

            var infoText = metaText(meta);
            var info = document.createElement('div');
            info.className = 'catalog-info small text-body-secondary mt-1';
            info.textContent = infoText;

            if (meta.product_url) {
                var catalogLink = document.createElement('a');
                catalogLink.href = meta.product_url;
                catalogLink.target = '_blank';
                catalogLink.rel = 'noopener';
                catalogLink.className = 'ms-2 text-primary text-decoration-none';
                catalogLink.title = 'Abrir no Canal da Peça';
                catalogLink.innerHTML = '<i class="icon-base ti tabler-external-link"></i> Canal da Peça';
                if (info.textContent !== '') {
                    info.appendChild(document.createTextNode(' · '));
                }
                info.appendChild(catalogLink);
            }

            if (info.textContent !== '') {
                tdDesc.appendChild(info);
            }

            var tdQty = document.createElement('td');
            tdQty.setAttribute('data-label', 'Quantidade');
            var qty = document.createElement('input');
            qty.type = 'number';
            qty.className = 'form-control item-quantity';
            qty.min = '1';
            qty.step = '1';
            qty.value = String(quantity || 1);
            tdQty.appendChild(qty);

            var tdRemove = document.createElement('td');
            tdRemove.className = 'actions-cell';
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-icon btn-text-danger item-remove';
            remove.title = 'Remover item';
            remove.innerHTML = '<i class="icon-base ti tabler-x"></i>';
            tdRemove.appendChild(remove);

            tr.appendChild(tdDesc);
            tr.appendChild(tdQty);
            tr.appendChild(tdRemove);

            // Metadados do catálogo (não são exibidos diretamente).
            tr.dataset.sku = meta.sku || '';
            tr.dataset.unitPrice = meta.price != null ? String(meta.price) : (meta.unit_price != null ? String(meta.unit_price) : '');
            tr.dataset.productUrl = meta.product_url || meta.url || '';
            tr.dataset.catalogNotes = meta.catalog_notes || [meta.brand, meta.part_code, meta.category].filter(Boolean).join(' · ');

            body.appendChild(tr);

            remove.addEventListener('click', function () {
                tr.remove();
                if (!body.querySelector('tr')) {
                    addRow();
                }
            });

            desc.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addRow();
                }
            });

            return tr;
        }

        function collect() {
            var items = [];
            body.querySelectorAll('tr').forEach(function (tr) {
                var description = tr.querySelector('.item-description').value.trim();
                if (!description) {
                    return;
                }
                items.push({
                    description: description,
                    quantity: Math.max(1, parseInt(tr.querySelector('.item-quantity').value, 10) || 1),
                    sku: tr.dataset.sku || null,
                    unit_price: tr.dataset.unitPrice !== '' ? parseFloat(tr.dataset.unitPrice) : null,
                    supplier: null,
                    product_url: tr.dataset.productUrl || null,
                    catalog_notes: tr.dataset.catalogNotes || null
                });
            });
            return items;
        }

        function removeEmptyInitialRow() {
            var rows = body.querySelectorAll('tr');
            if (rows.length === 1) {
                var desc = rows[0].querySelector('.item-description');
                if (desc && !desc.value.trim()) {
                    rows[0].remove();
                }
            }
        }

        addBtn.addEventListener('click', function () {
            var row = addRow();
            row.querySelector('.item-description').focus();
        });

        form.addEventListener('submit', function () {
            hidden.value = JSON.stringify(collect());
        });

        // Autocomplete de peças do catálogo.
        if (window.PartAutocomplete) {
            window.PartAutocomplete.init('#partOrderSearch', function (item) {
                var qtyEl = document.getElementById('partOrderSearchQty');
                var quantity = Math.max(1, parseInt(qtyEl && qtyEl.value, 10) || 1);
                removeEmptyInitialRow();
                var row = addRow(item.name || item.text, quantity, item);
                if (qtyEl) {
                    qtyEl.value = '1';
                }
                var lastInput = row.querySelector('.item-description');
                lastInput.classList.add('is-valid');
                setTimeout(function () { lastInput.classList.remove('is-valid'); }, 900);
            });
        }

        var initial = window.PART_ORDER_ITEMS || [];
        if (Array.isArray(initial) && initial.length) {
            initial.forEach(function (item) {
                addRow(item.description || '', item.quantity || 1, {
                    sku: item.sku,
                    unit_price: item.unit_price,
                    price: item.unit_price,
                    product_url: item.product_url,
                    catalog_notes: item.catalog_notes
                });
            });
        } else {
            addRow();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
