// =============================================
// FUNCIONÁRIOS — DataTables "Fixed Header" do Vuexy
// (tables-datatables-extensions), com endpoint
// server-side em /users/datatable.
// =============================================
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var table = document.querySelector('.dt-fixedheader');
        if (!table || typeof DataTable === 'undefined') {
            return;
        }

        var config = window.USERS_DATATABLE || {};
        var basePath = config.basePath || '/users';

        var dt = new DataTable(table, {
            ajax: {
                url: config.url || (basePath + '/datatable'),
                dataSrc: 'data'
            },
            processing: true,
            serverSide: true,
            columns: [
                { data: '' },
                { data: 'id', orderable: false, render: DataTable.render.select() },
                { data: 'id' },
                { data: 'name' },
                { data: 'email' },
                { data: 'hire_date' },
                { data: 'salary' },
                { data: 'status' },
                { data: '' }
            ],
            columnDefs: [
                {
                    className: 'control',
                    orderable: false,
                    targets: 0,
                    responsivePriority: 3,
                    render: function () {
                        return '';
                    }
                },
                {
                    // Checkboxes
                    targets: 1,
                    orderable: false,
                    render: function () {
                        return '<input type="checkbox" class="dt-checkboxes form-check-input">';
                    },
                    checkboxes: {
                        selectAllRender: '<input type="checkbox" class="form-check-input">'
                    },
                    responsivePriority: 4
                },
                {
                    targets: 2,
                    visible: false
                },
                {
                    // Avatar, nome e cargo
                    targets: 3,
                    render: function (data, type, full) {
                        const name = full.name || '';
                        const post = full.post || '';

                        let output;
                        const stateNum = Math.floor(Math.random() * 6);
                        const states = ['success', 'danger', 'warning', 'info', 'dark', 'primary', 'secondary'];
                        const state = states[stateNum];
                        const initials = (name.match(/\b\w/g) || []).map(i => i.toUpperCase()).join('');
                        output = `<span class="avatar-initial rounded-circle bg-label-${state}">${initials}</span>`;

                        return `
                          <div class="d-flex justify-content-start align-items-center">
                            <div class="avatar-wrapper">
                              <div class="avatar me-2">
                                ${output}
                              </div>
                            </div>
                            <div class="d-flex flex-column">
                              <span class="emp_name text-truncate">${name}</span>
                              <small class="emp_post text-truncate text-body-secondary">${post}</small>
                            </div>
                          </div>
                        `;
                    },
                    responsivePriority: 5
                },
                {
                    responsivePriority: 1,
                    targets: 4
                },
                {
                    responsivePriority: 2,
                    targets: 6
                },
                {
                    // Situação (badge)
                    targets: -2,
                    render: function (data) {
                        const statuses = {
                            1: { title: 'Ativo', class: 'bg-label-success' },
                            0: { title: 'Inativo', class: 'bg-label-secondary' }
                        };

                        if (typeof statuses[data] === 'undefined') {
                            return data;
                        }

                        return `
                          <span class="badge ${statuses[data].class}">
                            ${statuses[data].title}
                          </span>
                        `;
                    }
                },
                {
                    // Ações
                    targets: -1,
                    title: 'Ações',
                    className: 'd-flex align-items-center',
                    orderable: false,
                    render: function (data, type, full) {
                        return (
                            '<div class="d-inline-block">' +
                            '<a href="javascript:;" class="btn btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="icon-base ti tabler-dots-vertical"></i></a>' +
                            '<div class="dropdown-menu dropdown-menu-end m-0">' +
                            '<a href="' + basePath + '/' + full.id + '/edit" class="dropdown-item">Editar</a>' +
                            '<div class="dropdown-divider"></div>' +
                            '<a href="javascript:;" class="dropdown-item text-danger delete-record" data-id="' + full.id + '">Excluir</a>' +
                            '</div>' +
                            '</div>' +
                            '<a href="' + basePath + '/' + full.id + '/edit" class="btn btn-icon item-edit"><i class="icon-base ti tabler-pencil"></i></a>'
                        );
                    }
                }
            ],
            select: {
                style: 'multi',
                selector: 'td:nth-child(2)'
            },
            order: [[2, 'desc']],
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
                        placeholder: 'Buscar funcionário'
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
                info: 'Mostrando _START_ a _END_ de _TOTAL_ funcionários',
                infoEmpty: 'Nenhum funcionário encontrado',
                infoFiltered: '(filtrado de _MAX_ no total)',
                zeroRecords: 'Nenhum funcionário encontrado',
                emptyTable: 'Nenhum funcionário cadastrado',
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
                            return 'Detalhes de ' + data.name;
                        }
                    }),
                    type: 'column',
                    renderer: function (api, rowIdx, columns) {
                        const data = columns
                            .map(function (col) {
                                return col.title !== '' // não mostrar na modal se o título for vazio (checkbox)
                                    ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}">
                                          <td>${col.title}:</td>
                                          <td>${col.data}</td>
                                        </tr>`
                                    : '';
                            })
                            .join('');

                        if (data) {
                            const div = document.createElement('div');
                            div.classList.add('table-responsive');
                            const table = document.createElement('table');
                            div.appendChild(table);
                            table.classList.add('table');
                            const tbody = document.createElement('tbody');
                            tbody.innerHTML = data;
                            table.appendChild(tbody);
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
            const target = e.target.closest('.delete-record');
            if (!target) {
                return;
            }
            e.preventDefault();

            const id = target.getAttribute('data-id');
            if (!id || !window.confirm('Excluir este funcionário?')) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = basePath + '/' + id + '/delete';

            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = config.csrf || '';
            form.appendChild(token);

            document.body.appendChild(form);
            form.submit();
        });
    });
})();
