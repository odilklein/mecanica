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

        // Listas dinâmicas de tarefas/peças
        document.querySelectorAll('[data-list]').forEach(function (box) {
            var input = box.querySelector('input.form-control');
            var addBtn = box.querySelector('.btn-add');
            var items = box.querySelector('.dynamic-list-items');
            var target = document.getElementById(
                box.getAttribute('data-list') === 'tasks' ? 'tasks_json' : 'parts_json'
            );

            function sync() {
                var list = Array.prototype.map.call(items.children, function (row) {
                    return row.querySelector('.item-text').textContent;
                });
                if (target) {
                    target.value = JSON.stringify(list);
                }
            }

            function addItem(text) {
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
                    sync();
                });
                items.appendChild(row);
                input.value = '';
                sync();
            }

            addBtn.addEventListener('click', function () {
                addItem(input.value);
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addItem(input.value);
                }
            });
        });

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
