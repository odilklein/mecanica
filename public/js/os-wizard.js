// =============================================
// WIZARD DE NOVA OS — Vertical Icons (Vuexy)
// Cliente, veículo, serviço, equipe e revisão.
// Usa Select2 (live search) para cliente/veículo/FIPE.
// =============================================
(function () {
    'use strict';

    var clients = window.WIZARD_CLIENTS || [];
    var vehicles = window.WIZARD_VEHICLES || [];
    var stepper = null;

    function boot() {
        var $ = window.jQuery;
        var form = document.getElementById('formOS');
        if (!form || !$) {
            return;
        }

        var stepperEl = document.getElementById('osStepper');
        if (stepperEl && window.Stepper) {
            stepper = new Stepper(stepperEl, { linear: false });
        }

        var $client = $('#client_id');
        var $vehicle = $('#vehicle_id');
        var $lead = $('#lead_mechanic_id');
        var newClientFields = document.getElementById('newClientFields');
        var vehicleBox = document.getElementById('vehicleExistingBox');
        var newVehicleFields = document.getElementById('newVehicleFields');

        function setVehicleOptions(list) {
            $vehicle.empty();
            $vehicle.append(new Option('', '', false, false));
            (list || []).forEach(function (v) {
                var label = [v.plate, v.brand, v.model].filter(Boolean).join(' · ');
                $vehicle.append(new Option(label, v.id, false, false));
            });
            $vehicle.trigger('change.select2');
        }

        function updateClientState() {
            var clientId = $client.val() || '';

            if (newClientFields) {
                newClientFields.hidden = clientId !== '';
            }

            if (!clientId) {
                if (vehicleBox) {
                    vehicleBox.hidden = true;
                }
                setVehicleOptions([]);
                if (newVehicleFields) {
                    newVehicleFields.hidden = false;
                }
                updateReview();
                return;
            }

            var list = vehicles.filter(function (v) {
                return String(v.client_id) === String(clientId);
            });

            if (vehicleBox) {
                vehicleBox.hidden = false;
            }
            setVehicleOptions(list);
            if (window.initSelect2) {
                window.initSelect2('#vehicle_id');
            }
            if (newVehicleFields) {
                newVehicleFields.hidden = false;
            }

            updateReview();
        }

        $client.on('change', updateClientState);

        $vehicle.on('change', function () {
            var hasVehicle = !!($vehicle.val() || '');
            if (newVehicleFields) {
                newVehicleFields.hidden = hasVehicle;
            }
            updateReview();
        });

        updateClientState();

        // Navegação do stepper (Vertical Icons)
        document.querySelectorAll('#osStepper .btn-next').forEach(function (btn) {
            btn.addEventListener('click', function () {
                updateReview();
                if (stepper) {
                    stepper.next();
                }
            });
        });

        document.querySelectorAll('#osStepper .btn-prev').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (stepper) {
                    stepper.previous();
                }
            });
        });

        // Tarefas
        (function () {
            var box = document.querySelector('[data-list="tasks"]');
            if (!box) {
                return;
            }

            var input = box.querySelector('input.form-control');
            var addBtn = box.querySelector('.btn-add');
            var items = box.querySelector('.dynamic-list-items');
            var target = document.getElementById('tasks_json');

            function syncTasks() {
                var list = Array.prototype.map.call(items.children, function (row) {
                    return row.querySelector('.item-text').textContent;
                });
                if (target) {
                    target.value = JSON.stringify(list);
                }
            }

            function addTask(text) {
                text = (text || '').trim();
                if (!text) {
                    return;
                }

                var row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2 mb-2';
                row.innerHTML = '<i class="icon-base ti tabler-grip-vertical text-body-secondary"></i>'
                    + '<span class="item-text flex-grow-1"></span>'
                    + '<button type="button" class="btn btn-sm btn-icon btn-text-danger"><i class="icon-base ti tabler-x"></i></button>';
                row.querySelector('.item-text').textContent = text;
                row.querySelector('button').addEventListener('click', function () {
                    row.remove();
                    syncTasks();
                });
                items.appendChild(row);
                input.value = '';
                input.focus();
                syncTasks();
            }

            addBtn.addEventListener('click', function () {
                addTask(input.value);
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addTask(input.value);
                }
            });
        })();

        // Peças (autocomplete com preço + quantidade)
        (function () {
            var box = document.querySelector('[data-list="parts"]');
            if (!box) {
                return;
            }

            var items = box.querySelector('.dynamic-list-items');
            var target = document.getElementById('parts_json');
            var qtyInput = document.getElementById('osPartQty');
            var qtyValue = document.getElementById('osPartQtyValue');
            var countEl = document.getElementById('osPartsCount');
            var sliderEl = document.getElementById('osPartQtySlider');

            function syncParts() {
                var list = Array.prototype.map.call(items.children, function (row) {
                    var qty = row.querySelector('.item-qty');
                    return {
                        description: row.querySelector('.item-text').textContent,
                        quantity: Math.max(1, parseInt(qty && qty.value, 10) || 1),
                        sku: row.dataset.sku || null,
                        unit_price: row.dataset.unitPrice !== '' ? parseFloat(row.dataset.unitPrice) : null,
                        supplier: null,
                        product_url: null,
                        catalog_notes: row.dataset.catalogNotes || null
                    };
                });
                if (target) {
                    target.value = JSON.stringify(list);
                }
                if (countEl) {
                    countEl.textContent = String(items.children.length);
                }
            }

            function addPartRow(text, quantity, meta) {
                meta = meta || {};
                text = (text || '').trim();
                if (!text) {
                    return;
                }

                var row = document.createElement('div');
                row.className = 'd-flex align-items-start gap-2 mb-2';
                row.innerHTML = '<i class="icon-base ti tabler-grip-vertical text-body-secondary mt-1"></i>'
                    + '<div class="flex-grow-1"><span class="item-text d-block"></span><div class="catalog-info small text-body-secondary"></div></div>'
                    + '<input type="number" class="form-control form-control-sm item-qty" min="1" step="1" value="1" style="max-width: 90px; min-width: 72px;" aria-label="Quantidade">'
                    + '<button type="button" class="btn btn-sm btn-icon btn-text-danger"><i class="icon-base ti tabler-x"></i></button>';

                row.querySelector('.item-text').textContent = text;
                row.querySelector('.item-qty').value = Math.max(1, parseInt(quantity, 10) || 1);

                var metaParts = [meta.brand, meta.part_code, meta.price_label]
                    .filter(function (value) { return value !== undefined && value !== null && value !== ''; });
                row.querySelector('.catalog-info').textContent = metaParts.join(' · ');

                row.dataset.sku = meta.sku || '';
                row.dataset.unitPrice = meta.price != null ? String(meta.price) : '';
                row.dataset.catalogNotes = meta.catalog_notes || [meta.brand, meta.part_code, meta.category]
                    .filter(Boolean).join(' · ');

                row.querySelector('.item-qty').addEventListener('input', syncParts);
                row.querySelector('button').addEventListener('click', function () {
                    row.remove();
                    syncParts();
                });
                items.appendChild(row);
                syncParts();
            }

            function setQuantity(value) {
                var v = Math.max(1, Math.min(99, parseInt(value, 10) || 1));
                if (qtyInput) {
                    qtyInput.value = String(v);
                }
                if (qtyValue) {
                    qtyValue.textContent = String(v);
                }
                if (sliderEl && sliderEl.noUiSlider) {
                    sliderEl.noUiSlider.set(v);
                }
            }

            if (sliderEl && window.noUiSlider) {
                noUiSlider.create(sliderEl, {
                    start: [1],
                    connect: [true, false],
                    step: 1,
                    range: { min: 1, max: 99 }
                });
                sliderEl.noUiSlider.on('update', function (values) {
                    var v = Math.round(Number(values[0]));
                    if (qtyInput) {
                        qtyInput.value = String(v);
                    }
                    if (qtyValue) {
                        qtyValue.textContent = String(v);
                    }
                });
            }

            if (qtyInput) {
                qtyInput.addEventListener('input', function () {
                    var v = Math.max(1, Math.min(99, parseInt(qtyInput.value, 10) || 1));
                    if (qtyValue) {
                        qtyValue.textContent = String(v);
                    }
                    if (sliderEl && sliderEl.noUiSlider) {
                        sliderEl.noUiSlider.set(v);
                    }
                });
            }

            if (window.PartAutocomplete) {
                window.PartAutocomplete.init('#osPartSearch', function (item) {
                    var quantity = Math.max(1, parseInt(qtyInput && qtyInput.value, 10) || 1);
                    addPartRow(item.name || item.text, quantity, item);
                    setQuantity(1);
                });
            }

            var manualInput = document.getElementById('osPartManual');
            var manualAdd = document.getElementById('osPartManualAdd');

            function addManualPart() {
                var text = manualInput ? manualInput.value.trim() : '';
                if (!text) {
                    return;
                }
                var quantity = Math.max(1, parseInt(qtyInput && qtyInput.value, 10) || 1);
                addPartRow(text, quantity, {});
                if (manualInput) {
                    manualInput.value = '';
                    manualInput.focus();
                }
                setQuantity(1);
            }

            if (manualAdd) {
                manualAdd.addEventListener('click', addManualPart);
            }
            if (manualInput) {
                manualInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        addManualPart();
                    }
                });
            }
        })();

        // Revisão
        function updateReview() {
            var el = function (id) {
                return document.getElementById(id);
            };

            if (el('reviewCliente')) {
                var clientText = $client.find('option:selected').text().trim();
                if (!clientText) {
                    clientText = (el('client_name') ? el('client_name').value : '') || 'Novo cliente';
                }
                el('reviewCliente').textContent = clientText;
            }

            if (el('reviewVeiculo')) {
                var vehicleText = $vehicle.find('option:selected').text().trim();
                if (!vehicleText) {
                    var plate = el('plate') ? el('plate').value : '';
                    var brand = $('#fipe_brand_code').find('option:selected').text().trim();
                    var model = $('#fipe_model_code').find('option:selected').text().trim();
                    vehicleText = [plate, brand, model].filter(Boolean).join(' · ') || 'Novo veículo';
                }
                el('reviewVeiculo').textContent = vehicleText;
            }

            if (el('reviewServico') && el('initial_complaint')) {
                el('reviewServico').textContent = el('initial_complaint').value || '—';
            }

            if (el('reviewEquipe')) {
                var names = Array.prototype.map.call(
                    document.querySelectorAll('input[name="mechanics[]"]:checked'),
                    function (cb) {
                        var label = cb.closest('label');
                        var nameEl = label ? label.querySelector('.fw-medium') : null;
                        return nameEl ? nameEl.textContent : '';
                    }
                ).filter(Boolean);

                var leadText = $lead.find('option:selected').text().trim();
                el('reviewEquipe').textContent = names.join(', ')
                    + (leadText ? (names.length ? ' · Responsável: ' : 'Responsável: ') + leadText : '');
                if (!names.length && !leadText) {
                    el('reviewEquipe').textContent = '—';
                }
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
