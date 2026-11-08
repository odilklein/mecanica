// =============================================
// CATÁLOGO DE PEÇAS — DataTable no padrão
// e-commerce do Vuexy (produto, categoria,
// estoque, SKU, preço, status e ações).
// =============================================
(function () {
    'use strict';

    function esc(value) {
        var d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function fmtMoney(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }
        return 'R$ ' + Number(value)
            .toFixed(2)
            .replace('.', ',')
            .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function initials(name) {
        return (String(name || '').match(/\b\w/g) || [])
            .slice(0, 2)
            .map(function (i) { return i.toUpperCase(); })
            .join('');
    }

    function resolve(template, row) {
        return String(template || '').replace(/\{(\w+)\}/g, function (_, key) {
            return row[key] !== undefined && row[key] !== null ? row[key] : '';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var table = document.querySelector('.datatables-products');
        if (!table || typeof DataTable === 'undefined') {
            return;
        }

        var cfg = window.PARTS_DATATABLE || {};
        var basePath = cfg.basePath || '/parts';
        var csrf = cfg.csrf || '';

        var dt = new DataTable(table, {
            ajax: { url: cfg.url || '/parts/datatable', dataSrc: 'data' },
            columns: [
                { data: 'id', responsivePriority: 9 },
                { data: 'id', orderable: false, responsivePriority: 9, render: DataTable.render.select() },
                { data: 'name', responsivePriority: 1 },
                { data: 'category_name', responsivePriority: 5 },
                { data: 'stock', responsivePriority: 3 },
                { data: 'sku_display', responsivePriority: 8 },
                { data: 'price', responsivePriority: 6 },
                { data: 'stock', responsivePriority: 4 },
                { data: 'status', responsivePriority: 6 },
                { data: 'id', responsivePriority: 2 }
            ],
            columnDefs: [
                {
                    targets: 0,
                    className: 'control',
                    searchable: false,
                    orderable: false,
                    responsivePriority: 9,
                    render: function () { return ''; }
                },
                {
                    targets: 1,
                    orderable: false,
                    searchable: false,
                    responsivePriority: 9,
                    checkboxes: { selectAllRender: '<input type="checkbox" class="form-check-input">' },
                    render: function () {
                        return '<input type="checkbox" class="dt-checkboxes form-check-input">';
                    }
                },
                {
                    targets: 2,
                    responsivePriority: 1,
                    render: function (data, type, full) {
                        if (type !== 'display') {
                            return data;
                        }

                        var name = full.name || '';
                        var subtitle = full.subtitle || '';
                        var id = full.id;
                        var image = full.image || '';
                        var fallback = '<span class="avatar-initial rounded-2 bg-label-primary">' + esc(initials(full.brand || name)) + '</span>';
                        var output;

                        if (image && image.indexOf('indisponivel') === -1) {
                            output = '<img src="' + esc(image) + '" alt="' + esc(name) + '" class="part-thumb" loading="lazy"'
                                + ' onerror="this.hidden=true;this.nextElementSibling.hidden=false">'
                                + '<span class="avatar-initial rounded-2 bg-label-primary" hidden>' + esc(initials(full.brand || name)) + '</span>';
                        } else {
                            output = fallback;
                        }

                        return '<div class="d-flex justify-content-start align-items-center product-name">'
                            + '<div class="avatar-wrapper">'
                            + '<div class="avatar avatar me-2 me-sm-4 rounded-2 bg-label-secondary">' + output + '</div>'
                            + '</div>'
                            + '<div class="d-flex flex-column">'
                            + '<a href="' + esc(basePath + '/' + id) + '" class="text-heading fw-medium text-truncate">' + esc(name) + '</a>'
                            + (subtitle ? '<small class="text-truncate d-none d-sm-block text-body-secondary">' + esc(subtitle) + '</small>' : '')
                            + '</div></div>';
                    }
                },
                {
                    targets: 3,
                    responsivePriority: 5,
                    render: function (data, type, full) {
                        var name = full.category_name || 'Sem categoria';
                        if (type !== 'display') {
                            return name;
                        }

                        var color = full.category_color || 'secondary';
                        var icon = full.category_icon || 'package';

                        return '<span class="text-truncate d-flex align-items-center text-heading">'
                            + '<span class="w-px-30 h-px-30 rounded-circle d-flex justify-content-center align-items-center bg-label-' + esc(color) + ' me-2">'
                            + '<i class="icon-base ti tabler-' + esc(icon) + ' icon-18px"></i></span>'
                            + esc(name) + '</span>';
                    }
                },
                {
                    targets: 4,
                    orderable: false,
                    responsivePriority: 3,
                    render: function (data, type, full) {
                        var inStock = Number(full.stock || 0) > 0;
                        if (type !== 'display') {
                            return inStock ? 'Em estoque' : 'Esgotado';
                        }

                        return '<span class="text-truncate d-flex align-items-center">'
                            + '<label class="switch switch-primary switch-sm pe-none me-2">'
                            + '<input type="checkbox" class="switch-input" ' + (inStock ? 'checked' : '') + ' tabindex="-1">'
                            + '<span class="switch-toggle-slider"><span class="switch-on"></span><span class="switch-off"></span></span>'
                            + '</label>'
                            + '<span class="d-none">' + (inStock ? 'Em estoque' : 'Esgotado') + '</span>'
                            + '</span>';
                    }
                },
                {
                    targets: 5,
                    responsivePriority: 8,
                    render: function (data, type, full) {
                        return full.sku_display || '—';
                    }
                },
                {
                    targets: 6,
                    className: 'text-nowrap',
                    responsivePriority: 5,
                    render: function (data, type, full) {
                        if (type === 'sort' || type === 'type') {
                            return data === null || data === undefined || data === '' ? 0 : Number(data);
                        }

                        var link = '';
                        if (full.product_url) {
                            link = ' <a href="' + esc(full.product_url) + '" target="_blank" rel="noopener"'
                                + ' class="btn btn-icon btn-sm btn-text-primary rounded-pill waves-effect"'
                                + ' title="Abrir no Canal da Peça" aria-label="Abrir no Canal da Peça">'
                                + '<i class="icon-base ti tabler-external-link icon-18px"></i></a>';
                        }

                        return '<span class="d-inline-flex align-items-center">'
                            + '<span>' + fmtMoney(full.price) + '</span>' + link
                            + '</span>';
                    }
                },
                {
                    targets: 7,
                    responsivePriority: 4,
                    render: function (data, type, full) {
                        if (type !== 'display') {
                            return Number(full.stock || 0);
                        }
                        var stock = Number(full.stock || 0);
                        var min = Number(full.min_stock || 0);
                        var cls = stock <= 0 ? 'text-danger' : (stock <= min ? 'text-warning' : 'text-heading');
                        return '<span class="fw-medium ' + cls + '">' + stock + '</span>'
                            + '<small class="d-block text-body-secondary">mín. ' + min + '</small>';
                    }
                },
                {
                    targets: 8,
                    responsivePriority: 6,
                    render: function (data, type, full) {
                        if (type !== 'display') {
                            return full.status_label || data || '';
                        }
                        return '<span class="badge ' + esc(full.status_class || 'bg-label-secondary') + ' text-capitalized">'
                            + esc(full.status_label || data || '') + '</span>';
                    }
                },
                {
                    targets: 9,
                    title: 'Ações',
                    searchable: false,
                    orderable: false,
                    responsivePriority: 2,
                    render: function (data, type, full) {
                        if (type !== 'display') {
                            return '';
                        }

                        var id = full.id;
                        return '<div class="d-inline-block text-nowrap">'
                            + '<a href="' + esc(basePath + '/' + id + '/edit') + '" class="btn btn-icon btn-text-secondary rounded-pill waves-effect" title="Editar">'
                            + '<i class="icon-base ti tabler-edit icon-22px"></i></a>'
                            + '<button type="button" class="btn btn-icon btn-text-secondary rounded-pill waves-effect dropdown-toggle hide-arrow" data-bs-toggle="dropdown">'
                            + '<i class="icon-base ti tabler-dots-vertical icon-22px"></i></button>'
                            + '<div class="dropdown-menu dropdown-menu-end m-0">'
                            + '<a href="' + esc(basePath + '/' + id) + '" class="dropdown-item"><i class="icon-base ti tabler-eye me-2"></i>Ver detalhes</a>'
                            + '<a href="' + esc(basePath + '/create?duplicate=' + id) + '" class="dropdown-item"><i class="icon-base ti tabler-copy me-2"></i>Duplicar</a>'
                            + '<a href="javascript:;" class="dropdown-item text-danger part-delete" data-id="' + esc(id) + '"><i class="icon-base ti tabler-trash me-2"></i>Excluir</a>'
                            + '</div></div>';
                    }
                }
            ],
            select: { style: 'multi', selector: 'td:nth-child(2)' },
            order: [[2, 'asc']],
            displayLength: 10,
            layout: {
                topStart: {
                    rowClass: 'row mx-3 my-0 justify-content-between',
                    features: [
                        {
                            pageLength: {
                                menu: [7, 10, 25, 50, 100],
                                text: 'Mostrar _MENU_ peças'
                            }
                        }
                    ]
                },
                topEnd: {
                    search: { placeholder: 'Buscar peça, marca ou código...' }
                },
                bottomStart: {
                    rowClass: 'row mx-3 justify-content-between',
                    features: ['info']
                },
                bottomEnd: 'paging'
            },
            language: {
                search: 'Buscar:',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ peças',
                infoEmpty: 'Nenhuma peça encontrada',
                infoFiltered: '(filtrado de _MAX_ no total)',
                zeroRecords: 'Nenhuma peça encontrada',
                emptyTable: 'Nenhuma peça cadastrada',
                processing: 'Carregando...',
                lengthMenu: '_MENU_ peças por página',
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
                            return 'Detalhes de ' + (data.name || ('#' + data.id));
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

        bindFilters(dt);
        bindBulkDelete(dt);
        bindSingleDelete();

        function activeFilters() {
            var values = {};
            document.querySelectorAll('[data-parts-filter]').forEach(function (select) {
                values[select.getAttribute('data-parts-filter')] = select.value;
            });
            return values;
        }

        function bindFilters(tableApi) {
            var filters = activeFilters();

            DataTable.ext.search.push(function (settings, searchData, dataIndex) {
                if (settings.nTable !== table) {
                    return true;
                }

                var row = tableApi.row(dataIndex).data() || {};
                var current = activeFilters();

                if (current.status && String(row.status || '') !== current.status) {
                    return false;
                }
                if (current.category_id && String(row.category_id || '') !== current.category_id) {
                    return false;
                }
                if (current.stock_state && String(row.stock_state || '') !== current.stock_state) {
                    return false;
                }

                return true;
            });

            document.querySelectorAll('[data-parts-filter]').forEach(function (select) {
                select.addEventListener('change', function () {
                    filters = activeFilters();
                    tableApi.draw();
                });
            });
        }

        function selectedIds() {
            return dt.rows({ selected: true }).data().toArray().map(function (row) {
                return Number(row.id);
            }).filter(Boolean);
        }

        function bindBulkDelete(tableApi) {
            var button = document.getElementById('bulkDeleteParts');
            if (!button) {
                return;
            }

            function refresh() {
                button.disabled = selectedIds().length === 0;
            }

            tableApi.on('select deselect draw', refresh);
            refresh();

            button.addEventListener('click', function () {
                var ids = selectedIds();
                if (!ids.length || !window.confirm('Excluir ' + ids.length + ' peça(s) selecionada(s)?')) {
                    return;
                }

                var body = new URLSearchParams();
                body.append('csrf_token', csrf);
                body.append('ids', JSON.stringify(ids));

                fetch(cfg.bulkDeleteUrl || '/parts/bulk-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(function () {
                    window.location.reload();
                }).catch(function () {
                    window.alert('Não foi possível excluir as peças selecionadas.');
                });
            });
        }

        function bindSingleDelete() {
            document.addEventListener('click', function (e) {
                var target = e.target.closest('.part-delete');
                if (!target) {
                    return;
                }
                e.preventDefault();

                var id = target.getAttribute('data-id');
                if (!id || !window.confirm('Excluir esta peça?')) {
                    return;
                }

                var form = document.createElement('form');
                form.method = 'POST';
                form.action = basePath + '/' + id + '/delete';

                var token = document.createElement('input');
                token.type = 'hidden';
                token.name = 'csrf_token';
                token.value = csrf;
                form.appendChild(token);

                document.body.appendChild(form);
                form.submit();
            });
        }
    });
})();
