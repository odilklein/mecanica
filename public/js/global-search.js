/**
 * Busca global do header — mesmo comportamento visual do sistema
 * (botão de busca + overlay com sugestões), com resultados reais
 * vindos de /api/search.
 */
'use strict';
(function () {
    const container = document.querySelector('#global-search');
    if (!container || typeof autocomplete === 'undefined') {
        return;
    }

    const config = window.globalSearchConfig || {};
    const endpoint = config.endpoint || '/api/search';
    const suggestions = config.suggestions || {};

    const cache = new Map();
    let pendingQuery = null;
    let pendingPromise = null;

    function fetchResults(query) {
        if (cache.has(query)) {
            return Promise.resolve(cache.get(query));
        }
        if (pendingQuery === query && pendingPromise) {
            return pendingPromise;
        }

        pendingQuery = query;
        pendingPromise = fetch(endpoint + '?q=' + encodeURIComponent(query), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.ok ? response.json() : { groups: [] };
            })
            .then(function (json) {
                cache.set(query, json);
                if (cache.size > 40) {
                    cache.delete(cache.keys().next().value);
                }
                return json;
            })
            .catch(function () {
                return { groups: [] };
            })
            .finally(function () {
                if (pendingQuery === query) {
                    pendingPromise = null;
                }
            });

        return pendingPromise;
    }

    const groups = [
        { key: 'orders', label: 'Ordens de serviço' },
        { key: 'clients', label: 'Clientes' },
        { key: 'vehicles', label: 'Veículos' },
        { key: 'budgets', label: 'Orçamentos' },
        { key: 'part_orders', label: 'Pedidos de peças' },
        { key: 'tickets', label: 'Tickets' }
    ];

    function initialPanel(html) {
        const sections = Object.entries(suggestions).filter(function (entry) {
            return Array.isArray(entry[1]) && entry[1].length > 0;
        });

        return html`
            <div class="p-5 p-lg-12">
                <div class="row g-4">
                    ${sections.map(function ([section, items]) {
                        return html`
                            <div class="col-md-6 suggestion-section">
                                <p class="search-headings mb-2">${section}</p>
                                <div class="suggestion-items">
                                    ${items.map(function (item) {
                                        return html`
                                            <a href="${item.url}" class="suggestion-item d-flex align-items-center">
                                                <i class="icon-base ti ${item.icon || 'tabler-point'}"></i>
                                                <span>${item.name}</span>
                                            </a>
                                        `;
                                    })}
                                </div>
                            </div>
                        `;
                    })}
                </div>
            </div>
        `;
    }

    function messagePanel(html, icon, title, text) {
        return html`
            <div class="search-no-results-wrapper">
                <div class="d-flex justify-content-center align-items-center h-100">
                    <div class="text-center text-heading px-4">
                        <i class="icon-base ti ${icon} text-body-secondary" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-2 mb-1">${title}</h5>
                        <p class="text-body-secondary mb-0">${text}</p>
                    </div>
                </div>
            </div>
        `;
    }

    window.globalSearch = autocomplete({
        container: container,
        placeholder: 'Buscar [CTRL + K]',
        detachedMediaQuery: 'all',
        openOnFocus: true,
        classNames: {
            detachedContainer: 'd-flex flex-column',
            detachedFormContainer: 'd-flex align-items-center justify-content-between border-bottom',
            form: 'd-flex align-items-center',
            input: 'search-control border-none',
            detachedCancelButton: 'btn-search-close',
            panel: 'flex-grow content-wrapper overflow-auto position-relative',
            panelLayout: 'h-100',
            clearButton: 'd-none',
            item: 'd-block'
        },
        onStateChange: function (params) {
            const state = params.state;
            if (state.isOpen) {
                document.body.style.overflow = 'hidden';
                window.requestAnimationFrame(function () {
                    const cancel = document.querySelector('.aa-DetachedCancelButton');
                    if (cancel) {
                        cancel.innerHTML =
                            '<span class="text-body-secondary">[esc]</span> ' +
                            '<span class="icon-base icon-md ti tabler-x text-heading"></span>';
                    }
                });
            } else {
                if (state.status === 'idle' && params.setQuery) {
                    // mantém o termo ao reabrir para facilitar ajustes
                }
                document.body.style.overflow = '';
            }
        },
        render: function (args, root) {
            const { render, html, children, state } = args;

            if (!state.query) {
                render(initialPanel(html), root);
                return;
            }

            if (state.query.trim().length < 2) {
                render(
                    messagePanel(html, 'tabler-search', 'Digite para buscar', 'Informe pelo menos 2 caracteres.'),
                    root
                );
                return;
            }

            const hasItems = (state.collections || []).some(function (collection) {
                return collection.items && collection.items.length > 0;
            });

            if (state.status === 'loading' && !hasItems) {
                render(
                    messagePanel(html, 'tabler-loader-2', 'Buscando...', 'Consultando clientes, veículos, OS e mais.'),
                    root
                );
                return;
            }

            if (!hasItems) {
                render(
                    messagePanel(html, 'tabler-file-search', 'Nenhum resultado', 'Tente outro nome, placa, número de OS ou orçamento.'),
                    root
                );
                return;
            }

            render(children, root);
        },
        getSources: function () {
            return groups.map(function (group) {
                return {
                    sourceId: 'search-' + group.key,
                    getItems: function (params) {
                        const query = (params.query || '').trim();
                        if (query.length < 2) {
                            return [];
                        }
                        return fetchResults(query).then(function (json) {
                            const found = (json.groups || []).find(function (item) {
                                return item.key === group.key;
                            });
                            return found ? found.items : [];
                        });
                    },
                    getItemUrl: function (params) {
                        return params.item.url;
                    },
                    templates: {
                        header: function (params) {
                            if (!params.items.length) {
                                return null;
                            }
                            return params.html`<span class="search-headings">${group.label}</span>`;
                        },
                        item: function (params) {
                            const item = params.item;
                            const html = params.html;
                            return html`
                                <a href="${item.url}" class="d-flex align-items-center gap-2 w-100">
                                    <i class="icon-base ti ${item.icon || 'tabler-point'}"></i>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <h6 class="mb-0 text-truncate">${item.name}</h6>
                                        ${item.subtitle
                                            ? html`<small class="text-body-secondary d-block text-truncate">${item.subtitle}</small>`
                                            : ''}
                                    </div>
                                    ${item.badge
                                        ? html`<span class="badge ${item.badgeClass || 'bg-label-secondary'}">${item.badge}</span>`
                                        : ''}
                                </a>
                            `;
                        }
                    }
                };
            });
        }
    });

    // Atalho Ctrl/Cmd + K, igual ao padrão do sistema.
    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            const button = document.querySelector('.aa-DetachedSearchButton');
            if (button) {
                button.click();
            } else {
                container.click();
            }
        }
    });
})();
