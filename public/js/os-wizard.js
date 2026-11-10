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

        // Etapa Atendimento (ticket)
        var $ticketAssigned = $('#ticket_assigned_to');
        var createTicket = document.getElementById('create_ticket');
        var ticketFields = document.getElementById('ticketFields');
        var ticketDisabledHint = document.getElementById('ticketDisabledHint');
        var ticketMessage = document.getElementById('ticket_initial_message');
        var complaint = document.getElementById('initial_complaint');
        var ticketMessageTouched = false;

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

        // ----- Etapa Atendimento: estado do ticket -----
        function syncTicketState() {
            var enabled = createTicket ? createTicket.checked : true;

            if (ticketFields) {
                ticketFields.hidden = !enabled;
                ticketFields.querySelectorAll('input, select, textarea').forEach(function (el) {
                    el.disabled = !enabled;
                });
            }
            if (ticketDisabledHint) {
                ticketDisabledHint.hidden = enabled;
            }
            updateReview();
        }

        function syncTicketMessage() {
            if (ticketMessage && !ticketMessageTouched) {
                ticketMessage.value = complaint ? complaint.value : '';
            }
            updateReview();
        }

        function syncTicketAssignee() {
            if (!$ticketAssigned.length || $ticketAssigned.val()) {
                return;
            }
            var lead = $lead.val();
            if (lead) {
                $ticketAssigned.val(lead).trigger('change.select2');
            }
        }

        if (createTicket) {
            createTicket.addEventListener('change', syncTicketState);
        }
        if (complaint) {
            complaint.addEventListener('input', syncTicketMessage);
        }
        if (ticketMessage) {
            ticketMessage.addEventListener('input', function () {
                ticketMessageTouched = true;
                updateReview();
            });
        }
        $lead.on('change', function () {
            syncTicketAssignee();
            updateReview();
        });

        syncTicketState();
        syncTicketMessage();
        syncTicketAssignee();

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

        // Peças necessárias (cards no estilo do catálogo)
        (function () {
            var box = document.querySelector('[data-list="parts"]');
            if (!box) {
                return;
            }

            var items = box.querySelector('.dynamic-list-items');
            var target = document.getElementById('parts_json');
            var qtyInput = document.getElementById('osPartQty');
            var countEl = document.getElementById('osPartsCount');

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

            function syncParts() {
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
                if (target) {
                    target.value = JSON.stringify(list);
                }
                if (countEl) {
                    countEl.textContent = String(items.children.length);
                }
                updateReview();
            }

            function addPartRow(text, quantity, meta) {
                meta = meta || {};
                text = (text || '').trim();
                if (!text) {
                    return;
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
                if (meta.image) {
                    var img = document.createElement('img');
                    img.src = meta.image;
                    img.alt = text;
                    img.loading = 'lazy';
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
                info.textContent = metaParts.length
                    ? metaParts.join(' · ')
                    : (meta.name ? 'Peça do catálogo' : 'Peça manual');

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

                row.dataset.sku = meta.sku || '';
                row.dataset.unitPrice = meta.price != null ? String(meta.price) : '';
                row.dataset.productUrl = productUrl || '';
                row.dataset.catalogNotes = metaParts.join(' · ');

                var priceEl = row.querySelector('.item-price');
                var qtyEl = row.querySelector('.item-qty');

                function refreshPrice() {
                    var unit = meta.price != null ? Number(meta.price) : null;
                    var currentQty = Math.max(1, parseInt(qtyEl.value, 10) || 1);
                    priceEl.textContent = unit != null ? money(unit * currentQty) : '—';
                }

                qtyEl.addEventListener('input', function () {
                    refreshPrice();
                    syncParts();
                });
                row.querySelector('.item-remove').addEventListener('click', function () {
                    row.remove();
                    syncParts();
                });

                refreshPrice();
                items.appendChild(row);
                syncParts();
            }

            function setQuantity(value) {
                var v = Math.max(1, Math.min(99, parseInt(value, 10) || 1));
                if (qtyInput) {
                    qtyInput.value = String(v);
                }
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

            if (el('reviewPrioridade')) {
                var priorityText = $('#priority').find('option:selected').text().trim();
                el('reviewPrioridade').textContent = priorityText || '—';
            }

            if (el('reviewTicket')) {
                var ticketEnabled = createTicket ? createTicket.checked : true;
                if (!ticketEnabled) {
                    el('reviewTicket').textContent = 'Sem OS';
                } else {
                    var assigned = $ticketAssigned.find('option:selected').text().trim();
                    if (!assigned) {
                        assigned = $lead.find('option:selected').text().trim();
                    }
                    el('reviewTicket').textContent = 'OS será criada' + (assigned ? ' · ' + assigned : '');
                }
            }

            if (el('reviewPecas')) {
                var partsBox = document.querySelector('[data-list="parts"] .dynamic-list-items');
                var partsCount = partsBox ? partsBox.children.length : 0;
                el('reviewPecas').textContent = partsCount
                    ? partsCount + (partsCount === 1 ? ' peça' : ' peças')
                    : 'Nenhuma';
            }

        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
