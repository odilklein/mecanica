// =============================================
// PEDIDO DE PEÇAS — itens dinâmicos
// (descrição + quantidade, sem textarea "|")
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

        function addRow(description, quantity) {
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
                    quantity: Math.max(1, parseInt(tr.querySelector('.item-quantity').value, 10) || 1)
                });
            });
            return items;
        }

        addBtn.addEventListener('click', function () {
            var row = addRow();
            row.querySelector('.item-description').focus();
        });

        form.addEventListener('submit', function () {
            hidden.value = JSON.stringify(collect());
        });

        var initial = window.PART_ORDER_ITEMS || [];
        if (Array.isArray(initial) && initial.length) {
            initial.forEach(function (item) {
                addRow(item.description || '', item.quantity || 1);
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
