// =============================================
// CATEGORIAS DE PEÇAS — DataTable + offcanvas
// de criação/edição no padrão Vuexy.
// =============================================
(function () {
    'use strict';

    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var table = document.querySelector('.datatables-category-list');
        if (table && typeof DataTable !== 'undefined' && !table.classList.contains('dataTable')) {
            new DataTable(table, {
                order: [[1, 'asc']],
                displayLength: 10,
                columnDefs: [
                    { targets: 0, className: 'control', orderable: false, searchable: false, responsivePriority: 1 }
                ],
                layout: {
                    topStart: {
                        rowClass: 'row mx-3 my-0 justify-content-between',
                        features: [
                            {
                                pageLength: {
                                    menu: [7, 10, 25, 50, 100],
                                    text: 'Mostrar _MENU_ categorias'
                                }
                            }
                        ]
                    },
                    topEnd: {
                        search: { placeholder: 'Buscar categoria...' }
                    },
                    bottomStart: {
                        rowClass: 'row mx-3 justify-content-between',
                        features: ['info']
                    },
                    bottomEnd: 'paging'
                },
                language: {
                    search: 'Buscar:',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ categorias',
                    infoEmpty: 'Nenhuma categoria encontrada',
                    infoFiltered: '(filtrado de _MAX_ no total)',
                    zeroRecords: 'Nenhuma categoria encontrada',
                    emptyTable: 'Nenhuma categoria cadastrada',
                    lengthMenu: '_MENU_ categorias por página',
                    paginate: {
                        next: '<i class="icon-base ti tabler-chevron-right scaleX-n1-rtl icon-18px"></i>',
                        previous: '<i class="icon-base ti tabler-chevron-left scaleX-n1-rtl icon-18px"></i>',
                        first: '<i class="icon-base ti tabler-chevrons-left scaleX-n1-rtl icon-18px"></i>',
                        last: '<i class="icon-base ti tabler-chevrons-right scaleX-n1-rtl icon-18px"></i>'
                    }
                },
                responsive: {
                    details: {
                        display: DataTable.Responsive.display.modal({
                            header: function (row) {
                                var data = row.data();
                                return 'Detalhes da categoria';
                            }
                        }),
                        type: 'column',
                        renderer: function (api, rowIdx, columns) {
                            var rows = columns.map(function (col) {
                                return col.title !== ''
                                    ? '<tr data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">'
                                        + '<td>' + col.title + ':</td>'
                                        + '<td>' + col.data + '</td>'
                                        + '</tr>'
                                    : '';
                            }).join('');

                            if (rows) {
                                var div = document.createElement('div');
                                div.classList.add('table-responsive');
                                var detailsTable = document.createElement('table');
                                div.appendChild(detailsTable);
                                detailsTable.classList.add('table');
                                var tbody = document.createElement('tbody');
                                tbody.innerHTML = rows;
                                detailsTable.appendChild(tbody);
                                return div;
                            }
                            return false;
                        }
                    }
                }
            });
        }

        var offcanvasEl = document.getElementById('offcanvasCategory');
        var form = document.getElementById('categoryForm');
        if (!offcanvasEl || !form) {
            return;
        }

        var title = document.getElementById('offcanvasCategoryTitle');
        var nameInput = document.getElementById('categoryName');
        var slugInput = document.getElementById('categorySlug');
        var descriptionInput = document.getElementById('categoryDescription');
        var iconInput = document.getElementById('categoryIcon');
        var colorInput = document.getElementById('categoryColor');
        var activeInput = document.getElementById('categoryActive');
        var slugTouched = false;

        function resetForm() {
            form.action = '/parts/categories';
            if (title) {
                title.textContent = 'Nova categoria';
            }
            if (nameInput) nameInput.value = '';
            if (slugInput) slugInput.value = '';
            if (descriptionInput) descriptionInput.value = '';
            if (iconInput) iconInput.value = 'package';
            if (colorInput) colorInput.value = 'primary';
            if (activeInput) activeInput.checked = true;
            slugTouched = false;
        }

        function populate(category) {
            form.action = '/parts/categories/' + category.id;
            if (title) {
                title.textContent = 'Editar categoria';
            }
            if (nameInput) nameInput.value = category.name || '';
            if (slugInput) slugInput.value = category.slug || '';
            if (descriptionInput) descriptionInput.value = category.description || '';
            if (iconInput) iconInput.value = category.icon || 'package';
            if (colorInput) colorInput.value = category.color || 'primary';
            if (activeInput) activeInput.checked = String(category.is_active) === '1';
            slugTouched = true;
        }

        offcanvasEl.addEventListener('show.bs.offcanvas', function (event) {
            var trigger = event.relatedTarget;
            var payload = trigger ? trigger.getAttribute('data-category') : null;

            if (payload) {
                try {
                    populate(JSON.parse(payload));
                    return;
                } catch (e) {
                    // payload inválido: abre em modo de criação
                }
            }
            resetForm();
        });

        if (nameInput && slugInput) {
            nameInput.addEventListener('input', function () {
                if (!slugTouched) {
                    slugInput.value = slugify(nameInput.value);
                }
            });
            slugInput.addEventListener('input', function () {
                slugTouched = slugInput.value.trim() !== '';
            });
        }
    });
})();
