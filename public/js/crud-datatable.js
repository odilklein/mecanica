// =============================================
// DATATABLES "FIXED HEADER" (Vuexy) — inicializador
// genérico usado pelas listagens do CRUD.
//
// Cada Resource fornece window.CRUD_DATATABLE:
//   url, basePath, csrf, labels, columns, order
//
// Renderizadores suportados:
//   control, select, text, name, link, badge,
//   date, money, number, actions
//
// No mobile, as colunas com priority 1 (identificador
// principal) e 2 (ações) permanecem visíveis; as demais
// são escondidas pelo Responsive e ficam na modal.
// =============================================
(function () {
    'use strict';

    var AVATAR_STATES = ['success', 'danger', 'warning', 'info', 'dark', 'primary', 'secondary'];

    function esc(value) {
        var d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function dash(value) {
        return value === null || value === undefined || value === ''
            ? '—'
            : esc(value);
    }

    function fmtDate(value) {
        if (!value) {
            return '—';
        }
        var m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? m[3] + '/' + m[2] + '/' + m[1] : esc(value);
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
            .map(function (i) { return i.toUpperCase(); })
            .join('');
    }

    function resolve(template, row) {
        return String(template || '').replace(/\{(\w+)\}/g, function (_, key) {
            return row[key] !== undefined && row[key] !== null ? row[key] : '';
        });
    }

    var renderers = {
        control: function () {
            return function () { return ''; };
        },

        select: function () {
            return DataTable.render.select();
        },

        text: function () {
            return function (data, type) {
                return type === 'display' ? dash(data) : data;
            };
        },

        name: function (col) {
            return function (data, type, full) {
                var name = data == null ? '' : String(data);
                var secondary = col.secondary ? full[col.secondary] : '';

                if (type === 'filter') {
                    return name + ' ' + (secondary || '');
                }
                if (type !== 'display') {
                    return data;
                }

                var href = col.href ? resolve(col.href, full) : '';
                var label = href
                    ? '<a href="' + esc(href) + '" class="fw-medium">' + esc(name) + '</a>'
                    : '<span class="emp_name text-truncate">' + esc(name) + '</span>';
                var state = AVATAR_STATES[Math.floor(Math.random() * AVATAR_STATES.length)];
                var avatar = '<span class="avatar-initial rounded-circle bg-label-' + state + '">'
                    + esc(initials(name)) + '</span>';

                return '<div class="d-flex justify-content-start align-items-center">'
                    + '<div class="avatar-wrapper"><div class="avatar me-2">' + avatar + '</div></div>'
                    + '<div class="d-flex flex-column">' + label
                    + (secondary
                        ? '<small class="emp_post text-truncate text-body-secondary">' + esc(secondary) + '</small>'
                        : '')
                    + '</div></div>';
            };
        },

        link: function (col) {
            return function (data, type, full) {
                if (type !== 'display') {
                    return data;
                }
                var href = resolve(col.href || '', full);
                var label = dash(data);
                return href
                    ? '<a href="' + esc(href) + '" class="fw-medium">' + label + '</a>'
                    : label;
            };
        },

        badge: function (col) {
            return function (data, type) {
                var item = col.badge && col.badge[data];

                if (type === 'display') {
                    if (!item) {
                        return dash(data);
                    }
                    return '<span class="badge ' + esc(item.class) + '">' + esc(item.label) + '</span>';
                }

                return item ? item.label : data;
            };
        },

        date: function () {
            return function (data, type) {
                if (type === 'sort') {
                    return data;
                }
                return fmtDate(data);
            };
        },

        money: function () {
            return function (data, type) {
                if (type === 'sort' || type === 'type') {
                    return data === null || data === undefined || data === '' ? 0 : Number(data);
                }
                return fmtMoney(data);
            };
        },

        number: function () {
            return function (data, type) {
                if (type !== 'display') {
                    return data;
                }
                return data === null || data === undefined || data === ''
                    ? '—'
                    : esc(Number(data).toLocaleString('pt-BR'));
            };
        },

        actions: function (col) {
            return function (data, type, full) {
                if (type !== 'display') {
                    return '';
                }

                var items = (col.actions || []).map(function (action) {
                    if (action.delete) {
                        return '<a href="javascript:;" class="dropdown-item text-danger delete-record"'
                            + ' data-id="' + esc(full.id) + '">' + esc(action.label || 'Excluir') + '</a>';
                    }
                    return '<a href="' + esc(resolve(action.href || '', full)) + '" class="dropdown-item">'
                        + esc(action.label || '') + '</a>';
                }).join('');

                var primary = null;
                (col.actions || []).some(function (action) {
                    if (action.primary) {
                        primary = action;
                        return true;
                    }
                    return false;
                });

                var primaryHtml = primary
                    ? '<a href="' + esc(resolve(primary.href || '', full)) + '" class="btn btn-icon item-edit">'
                        + '<i class="icon-base ti tabler-' + esc(primary.icon || 'pencil') + '"></i></a>'
                    : '';

                return '<div class="d-inline-block">'
                    + '<a href="javascript:;" class="btn btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">'
                    + '<i class="icon-base ti tabler-dots-vertical"></i></a>'
                    + '<div class="dropdown-menu dropdown-menu-end m-0">' + items + '</div>'
                    + '</div>' + primaryHtml;
            };
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var table = document.querySelector('.dt-fixedheader');
        if (!table || typeof DataTable === 'undefined') {
            return;
        }

        var cfg = window.CRUD_DATATABLE || {};
        var basePath = cfg.basePath || '';
        var labels = cfg.labels || {};
        var plural = String(labels.plural || 'registros').toLowerCase();

        var columns = (cfg.columns || []).map(function (col) {
            var def = {
                data: col.data || null,
                title: col.title || '',
                orderable: col.orderable !== false,
                visible: col.visible !== false,
                className: col.className || '',
                responsivePriority: typeof col.priority === 'number' ? col.priority : 100
            };

            if (col.render && renderers[col.render]) {
                def.render = renderers[col.render](col);
            }

            return def;
        });

        var dt = new DataTable(table, {
            ajax: {
                url: cfg.url,
                dataSrc: 'data'
            },
            processing: true,
            columns: columns,
            columnDefs: [
                {
                    // Checkboxes da seleção múltipla
                    targets: 1,
                    orderable: false,
                    render: function () {
                        return '<input type="checkbox" class="dt-checkboxes form-check-input">';
                    },
                    checkboxes: {
                        selectAllRender: '<input type="checkbox" class="form-check-input">'
                    },
                    responsivePriority: 9
                }
            ],
            select: {
                style: 'multi',
                selector: 'td:nth-child(2)'
            },
            order: cfg.order || [[2, 'desc']],
            layout: {
                topStart: {
                    rowClass: 'row mx-3 my-0 justify-content-between',
                    features: [
                        {
                            pageLength: {
                                menu: [7, 10, 25, 50, 100],
                                text: 'Mostrar _MENU_ registros'
                            }
                        }
                    ]
                },
                topEnd: {
                    search: {
                        placeholder: 'Buscar ' + plural
                    }
                },
                bottomStart: {
                    rowClass: 'row mx-3 justify-content-between',
                    features: ['info']
                },
                bottomEnd: 'paging'
            },
            displayLength: 7,
            language: {
                search: 'Buscar:',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ ' + plural,
                infoEmpty: 'Nenhum registro encontrado',
                infoFiltered: '(filtrado de _MAX_ no total)',
                zeroRecords: 'Nenhum registro encontrado',
                emptyTable: 'Nenhum registro cadastrado',
                processing: 'Carregando...',
                lengthMenu: '_MENU_ registros por página',
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
                            return 'Detalhes de ' + (
                                data.name
                                || data.plate
                                || data.number_label
                                || data.order_number_label
                                || ('#' + data.id)
                            );
                        }
                    }),
                    type: 'column',
                    renderer: function (api, rowIdx, columns) {
                        var rows = columns.map(function (col) {
                            return col.title !== '' // não mostrar se o título for vazio (checkbox)
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

        // Fixed header
        if (window.Helpers && window.Helpers.isNavbarFixed()) {
            const navbar = document.getElementById('layout-navbar');
            const navHeight = navbar ? navbar.offsetHeight : 0;
            new DataTable.FixedHeader(dt).headerOffset(navHeight);
        } else {
            new DataTable.FixedHeader(dt);
        }

        // Exclusão (POST com CSRF, mantendo o padrão do sistema)
        document.addEventListener('click', function (e) {
            var target = e.target.closest('.delete-record');
            if (!target) {
                return;
            }
            e.preventDefault();

            var id = target.getAttribute('data-id');
            if (!id || !window.confirm('Excluir este registro?')) {
                return;
            }

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = basePath + '/' + id + '/delete';

            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = cfg.csrf || '';
            form.appendChild(token);

            document.body.appendChild(form);
            form.submit();
        });
    });
})();
