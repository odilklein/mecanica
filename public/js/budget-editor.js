// =============================================
// EDITOR DE ORÇAMENTO — itens dinâmicos + totais.
// =============================================
(function () {
    'use strict';

    var vehicles = window.BUDGET_VEHICLES || [];

    function fmt(v) {
        return 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
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
                var preco = parseFloat(tr.querySelector('.preco-item').value) || 0;
                var sub = qtd * preco;
                tr.querySelector('.subtotal-item').textContent = fmt(sub);
                if (tipo === 'service') { labor += sub; } else { parts += sub; }
            });
            var discountEl = document.getElementById('discount');
            var discount = parseFloat(discountEl && discountEl.value) || 0;
            document.getElementById('totalPecas').textContent = fmt(parts);
            document.getElementById('totalServicos').textContent = fmt(labor);
            document.getElementById('totalDesconto').textContent = fmt(discount);
            document.getElementById('totalGeral').textContent = fmt(Math.max(0, parts + labor - discount));
        }

        function addRow(data) {
            data = data || {};
            var tr = document.createElement('tr');
            tr.innerHTML = '<td><select class="form-select form-select-sm tipo-item"><option value="part">Peça</option><option value="service">Serviço</option></select></td>'
                + '<td><input type="text" class="form-control form-control-sm descricao-item" maxlength="200" placeholder="Descrição do item"></td>'
                + '<td><input type="number" class="form-control form-control-sm qtd-item" value="1" min="0.01" step="0.01"></td>'
                + '<td><input type="number" class="form-control form-control-sm preco-item" value="0" min="0" step="0.01"></td>'
                + '<td><strong class="subtotal-item">R$ 0,00</strong></td>'
                + '<td><button type="button" class="btn btn-sm btn-icon btn-text-danger btn-remover"><i class="icon-base ti tabler-x"></i></button></td>';
            corpo.appendChild(tr);

            var tipo = tr.querySelector('.tipo-item');
            var desc = tr.querySelector('.descricao-item');
            var qtd = tr.querySelector('.qtd-item');
            var preco = tr.querySelector('.preco-item');

            tipo.value = data.type === 'service' ? 'service' : 'part';
            desc.value = data.description || '';
            qtd.value = data.quantity || 1;
            preco.value = data.unit_price || 0;

            [tipo, desc, qtd, preco].forEach(function (el) { el.addEventListener('input', recalc); });
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
                    unit_price: parseFloat(tr.querySelector('.preco-item').value) || 0
                });
            });
            return items;
        }

        document.getElementById('btnAddItem').addEventListener('click', function () { addRow(); });

        var discount = document.getElementById('discount');
        if (discount) { discount.addEventListener('input', recalc); }

        (window.BUDGET_ITEMS || []).forEach(function (item) { addRow(item); });
        if (!(window.BUDGET_ITEMS || []).length) { addRow(); }

        // Veículos do cliente
        var cliente = document.getElementById('cliente_id');
        var veiculo = document.getElementById('veiculo_id');
        function loadVehicles() {
            var cid = cliente.value;
            var current = veiculo.getAttribute('data-current');
            veiculo.innerHTML = '<option value="">Selecione o cliente</option>';
            if (!cid) {
                if (window.jQuery && window.jQuery(veiculo).data('select2')) {
                    window.jQuery(veiculo).trigger('change.select2');
                }
                return;
            }
            vehicles.filter(function (v) { return String(v.client_id) === String(cid); }).forEach(function (v) {
                var opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = [v.plate, v.model].filter(Boolean).join(' · ');
                if (String(v.id) === String(current)) { opt.selected = true; }
                veiculo.appendChild(opt);
            });
            if (window.jQuery && window.jQuery(veiculo).data('select2')) {
                window.jQuery(veiculo).trigger('change.select2');
            }
        }
        cliente.addEventListener('change', function () {
            veiculo.setAttribute('data-current', '');
            loadVehicles();
        });
        loadVehicles();

        form.addEventListener('submit', function () {
            document.getElementById('items_json').value = JSON.stringify(collect());
        });
    });
})();
