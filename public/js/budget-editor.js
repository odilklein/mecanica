// =============================================
// EDITOR DE ORÇAMENTO — itens dinâmicos + totais.
// Inclui busca no catálogo, filtro de OS por cliente
// e formatação de preços em pt-BR.
// =============================================
(function () {
    'use strict';

    var vehicles = window.BUDGET_VEHICLES || [];
    var orders = window.BUDGET_ORDERS || [];

    function parseMoney(value) {
        var normalized = String(value || '').replace(/[^\d,.-]/g, '');
        if (normalized.indexOf(',') !== -1) {
            normalized = normalized.replace(/\./g, '').replace(',', '.');
        }
        var number = parseFloat(normalized);
        return isNaN(number) ? 0 : number;
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function fmt(value) {
        return 'R$ ' + formatMoney(value);
    }

    function triggerSelect2(el) {
        if (window.jQuery && window.jQuery(el).data('select2')) {
            window.jQuery(el).trigger('change.select2');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var corpo = document.getElementById('corpoItens');
        var form = document.getElementById('formOrcamento');
        if (!corpo || !form) { return; }

        function recalc() {
            var parts = 0;
            var labor = 0;
            corpo.querySelectorAll('tr').forEach(function (tr) {
                var tipo = tr.querySelector('.tipo-item').value;
                var qtd = parseFloat(tr.querySelector('.qtd-item').value) || 0;
                var preco = parseMoney(tr.querySelector('.preco-item').value);
                var sub = qtd * preco;
                tr.querySelector('.subtotal-item').textContent = fmt(sub);
                if (tipo === 'service') { labor += sub; } else { parts += sub; }
            });
            var discountEl = document.getElementById('discount');
            var discount = parseFloat(discountEl && discountEl.value) || 0;
            var effectiveDiscount = Math.min(discount, parts + labor);
            document.getElementById('totalPecas').textContent = fmt(parts);
            document.getElementById('totalServicos').textContent = fmt(labor);
            document.getElementById('totalDesconto').textContent = fmt(effectiveDiscount);
            document.getElementById('totalGeral').textContent = fmt(Math.max(0, parts + labor - effectiveDiscount));
        }

        function addRow(data) {
            data = data || {};
            var tr = document.createElement('tr');
            tr.innerHTML = '<td><select class="form-select form-select-sm tipo-item"><option value="part">Peça</option><option value="service">Serviço</option></select></td>'
                + '<td><input type="text" class="form-control form-control-sm descricao-item" maxlength="200" placeholder="Descrição do item"></td>'
                + '<td><input type="number" class="form-control form-control-sm qtd-item" value="1" min="0.01" step="0.01" style="min-width:56px;"></td>'
                + '<td><input type="text" class="form-control form-control-sm preco-item" inputmode="decimal" placeholder="0,00"></td>'
                + '<td><strong class="subtotal-item text-nowrap">R$ 0,00</strong></td>'
                + '<td><button type="button" class="btn btn-sm btn-icon btn-text-danger btn-remover"><i class="icon-base ti tabler-x"></i></button></td>';
            corpo.appendChild(tr);

            var tipo = tr.querySelector('.tipo-item');
            var desc = tr.querySelector('.descricao-item');
            var qtd = tr.querySelector('.qtd-item');
            var preco = tr.querySelector('.preco-item');

            tipo.value = data.type === 'service' ? 'service' : 'part';
            desc.value = data.description || '';
            qtd.value = data.quantity || 1;
            preco.value = formatMoney(data.unit_price || 0);

            [tipo, desc, qtd, preco].forEach(function (el) { el.addEventListener('input', recalc); });
            preco.addEventListener('focus', function () {
                preco.value = String(parseMoney(preco.value)).replace('.', ',');
            });
            preco.addEventListener('blur', function () {
                preco.value = formatMoney(parseMoney(preco.value));
                recalc();
            });
            tr.querySelector('.btn-remover').addEventListener('click', function () { tr.remove(); recalc(); });
            recalc();
        }

        function collect() {
            var items = [];
            corpo.querySelectorAll('tr').forEach(function (tr) {
                var desc = tr.querySelector('.descricao-item').value.trim();
                if (!desc) { return; }
                items.push({
                    type: tr.querySelector('.tipo-item').value,
                    description: desc,
                    quantity: parseFloat(tr.querySelector('.qtd-item').value) || 1,
                    unit_price: parseMoney(tr.querySelector('.preco-item').value)
                });
            });
            return items;
        }

        document.getElementById('btnAddItem').addEventListener('click', function () { addRow(); });

        // Peça do catálogo entra com descrição e preço de referência.
        if (window.PartAutocomplete) {
            window.PartAutocomplete.init('#budgetPartSearch', function (item) {
                addRow({
                    type: 'part',
                    description: item.name || item.text,
                    quantity: 1,
                    unit_price: item.price || 0
                });
            });
        }

        var discount = document.getElementById('discount');
        if (discount) { discount.addEventListener('input', recalc); }

        (window.BUDGET_ITEMS || []).forEach(function (item) { addRow(item); });
        if (!(window.BUDGET_ITEMS || []).length) { addRow(); }

        // Veículos e OS do cliente
        var cliente = document.getElementById('cliente_id');
        var veiculo = document.getElementById('veiculo_id');
        var os = document.getElementById('service_order_id');

        function loadVehicles() {
            if (!cliente || !veiculo) { return; }
            var cid = cliente.value;
            var current = veiculo.getAttribute('data-current');
            veiculo.innerHTML = '<option value="">Selecione o cliente</option>';
            if (!cid) {
                triggerSelect2(veiculo);
                return;
            }
            vehicles.filter(function (v) { return String(v.client_id) === String(cid); }).forEach(function (v) {
                var opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = [v.plate, v.model].filter(Boolean).join(' · ');
                if (String(v.id) === String(current)) { opt.selected = true; }
                veiculo.appendChild(opt);
            });
            triggerSelect2(veiculo);
        }

        function loadOrders() {
            if (!os) { return; }
            var cid = cliente ? cliente.value : '';
            var current = os.value || os.getAttribute('data-current') || '';
            os.innerHTML = '<option value="">Sem OS vinculada</option>';
            if (cid) {
                orders.filter(function (o) {
                    return String(o.client_id) === String(cid);
                }).forEach(function (o) {
                    var opt = new Option(o.label, o.id, false, false);
                    opt.selected = String(o.id) === String(current);
                    os.appendChild(opt);
                });
            }
            var hasCurrent = Array.prototype.some.call(os.options, function (opt) {
                return opt.value === String(current);
            });
            if (!hasCurrent) { os.value = ''; }
            os.setAttribute('data-current', '');
            triggerSelect2(os);
        }

        function onClientChange() {
            if (veiculo) { veiculo.setAttribute('data-current', ''); }
            loadVehicles();
            loadOrders();
        }

        function onOrderChange() {
            var oid = os ? os.value : '';
            if (!oid || !veiculo) { return; }
            var order = orders.find(function (o) { return String(o.id) === String(oid); });
            if (!order || !order.vehicle_id) { return; }
            var exists = Array.prototype.some.call(veiculo.options, function (opt) {
                return String(opt.value) === String(order.vehicle_id);
            });
            if (exists) {
                veiculo.value = String(order.vehicle_id);
                triggerSelect2(veiculo);
            }
        }

        if (cliente) {
            if (window.jQuery) {
                window.jQuery(cliente).on('change', onClientChange);
            } else {
                cliente.addEventListener('change', onClientChange);
            }
        }

        if (os) {
            if (window.jQuery) {
                window.jQuery(os).on('change', onOrderChange);
            } else {
                os.addEventListener('change', onOrderChange);
            }
        }

        loadVehicles();
        loadOrders();

        form.addEventListener('submit', function () {
            document.getElementById('items_json').value = JSON.stringify(collect());
        });
    });
})();
