// =============================================
// WIZARD DE NOVA OS — stepper, autocomplete de
// cliente, veículos do cliente e listas dinâmicas.
// =============================================
(function () {
    'use strict';

    var clients = window.WIZARD_CLIENTS || [];
    var vehicles = window.WIZARD_VEHICLES || [];
    var stepper = null;

    document.addEventListener('DOMContentLoaded', function () {
        var stepperEl = document.querySelector('#osStepper');
        if (stepperEl && window.Stepper) {
            stepper = new Stepper(stepperEl, { linear: false });
        }

        var nameInput = document.getElementById('client_name');
        var suggestions = document.getElementById('clientSuggestions');
        var clientId = document.getElementById('client_id');
        var phoneInput = document.getElementById('client_phone');
        var existingBox = document.getElementById('vehicleExistingBox');
        var existingSelect = document.getElementById('vehicle_existing');
        var vehicleId = document.getElementById('vehicle_id');

        // ---------- Autocomplete de cliente ----------
        function renderSuggestions(term) {
            if (!suggestions) { return; }
            suggestions.innerHTML = '';
            if (term.length < 2) { suggestions.hidden = true; return; }

            var matches = clients.filter(function (c) {
                return ((c.name || '') + ' ' + (c.phone || '')).toLowerCase().indexOf(term.toLowerCase()) !== -1;
            }).slice(0, 8);

            if (!matches.length) { suggestions.hidden = true; return; }

            matches.forEach(function (c) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action';
                var title = document.createElement('span');
                title.className = 'd-block fw-medium';
                title.textContent = c.name;
                var sub = document.createElement('small');
                sub.className = 'text-body-secondary';
                sub.textContent = [c.phone, c.city].filter(Boolean).join(' · ') || '—';
                btn.appendChild(title);
                btn.appendChild(sub);
                btn.addEventListener('click', function () { selectClient(c); });
                suggestions.appendChild(btn);
            });
            suggestions.hidden = false;
        }

        function selectClient(c) {
            if (clientId) { clientId.value = c.id; }
            if (nameInput) { nameInput.value = c.name; }
            if (phoneInput && !phoneInput.value) { phoneInput.value = c.phone || ''; }
            if (suggestions) { suggestions.hidden = true; }
            loadVehicles(c.id);
        }

        if (nameInput) {
            nameInput.addEventListener('input', function () {
                if (clientId) { clientId.value = ''; }
                renderSuggestions(nameInput.value.trim());
            });
        }

        document.addEventListener('click', function (e) {
            if (suggestions && !suggestions.hidden && !suggestions.contains(e.target) && e.target !== nameInput) {
                suggestions.hidden = true;
            }
        });

        // ---------- Veículos do cliente ----------
        function loadVehicles(cid) {
            if (!existingSelect) { return; }
            var list = vehicles.filter(function (v) { return String(v.client_id) === String(cid); });
            existingSelect.innerHTML = '<option value="">Selecione um veículo cadastrado</option>';
            if (!list.length) {
                if (existingBox) { existingBox.hidden = true; }
                return;
            }
            list.forEach(function (v) {
                var opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = [v.plate, v.brand, v.model].filter(Boolean).join(' · ');
                existingSelect.appendChild(opt);
            });
            if (existingBox) { existingBox.hidden = false; }
        }

        if (existingSelect) {
            existingSelect.addEventListener('change', function () {
                var id = existingSelect.value;
                if (!id) { if (vehicleId) { vehicleId.value = ''; } return; }
                var v = vehicles.find(function (x) { return String(x.id) === String(id); });
                if (!v) { return; }
                if (vehicleId) { vehicleId.value = v.id; }
                var plate = document.getElementById('plate');
                var color = document.getElementById('color');
                var brand = document.getElementById('brand');
                var model = document.getElementById('model');
                var year = document.getElementById('year');
                if (plate) { plate.value = v.plate || ''; }
                if (color) { color.value = v.color || ''; }
                if (brand) { brand.value = v.brand || ''; }
                if (model) { model.value = v.model || ''; }
                if (year) { year.value = v.year || ''; }
            });
        }

        // ---------- Listas dinâmicas (tarefas/peças) ----------
        document.querySelectorAll('[data-list]').forEach(function (box) {
            var input = box.querySelector('input.form-control');
            var addBtn = box.querySelector('.btn-add');
            var items = box.querySelector('.dynamic-list-items');
            var target = document.getElementById(box.getAttribute('data-list') === 'tasks' ? 'tasks_json' : 'parts_json');

            function sync() {
                var list = Array.prototype.map.call(items.children, function (row) {
                    return row.querySelector('.item-text').textContent;
                });
                if (target) { target.value = JSON.stringify(list); }
            }

            function addItem(text) {
                text = (text || '').trim();
                if (!text) { return; }
                var row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2 mb-2';
                row.innerHTML = '<i class="icon-base ti tabler-grip-vertical text-body-secondary"></i>'
                    + '<span class="item-text flex-grow-1"></span>'
                    + '<button type="button" class="btn btn-sm btn-icon btn-text-danger"><i class="icon-base ti tabler-x"></i></button>';
                row.querySelector('.item-text').textContent = text;
                row.querySelector('button').addEventListener('click', function () { row.remove(); sync(); });
                items.appendChild(row);
                input.value = '';
                sync();
            }

            addBtn.addEventListener('click', function () { addItem(input.value); });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); addItem(input.value); }
            });
        });

        // ---------- Revisão ----------
        function updateReview() {
            var el = function (id) { return document.getElementById(id); };
            if (el('reviewCliente')) { el('reviewCliente').textContent = (nameInput && nameInput.value) || '—'; }
            if (el('reviewVeiculo')) {
                var plate = el('plate') ? el('plate').value : '';
                var brand = el('brand') ? el('brand').value : '';
                el('reviewVeiculo').textContent = [plate, brand].filter(Boolean).join(' · ') || '—';
            }
            if (el('reviewServico') && el('initial_complaint')) {
                el('reviewServico').textContent = el('initial_complaint').value || '—';
            }
            var names = Array.prototype.map.call(
                document.querySelectorAll('input[name="mechanics[]"]:checked'),
                function (cb) {
                    var label = cb.closest('label');
                    var nameEl = label ? label.querySelector('.fw-medium') : null;
                    return nameEl ? nameEl.textContent : '';
                }
            ).filter(Boolean);
            if (el('reviewEquipe')) { el('reviewEquipe').textContent = names.join(', ') || '—'; }
        }

        window.OSWizard = {
            next: function () { updateReview(); if (stepper) { stepper.next(); } },
            prev: function () { if (stepper) { stepper.prev(); } }
        };
    });
})();
