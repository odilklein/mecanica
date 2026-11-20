// =============================================
// TICKETS — central de atendimento estilo app-chat.
// Lista, busca, filtros, conversa, envio e status.
// =============================================
(function () {
    'use strict';

    var COLORS = ['primary', 'info', 'success', 'warning', 'danger', 'secondary'];
    var STATUS_LABEL = { open: 'Em aberto', closed: 'Encerrado' };
    var STATUS_BADGE = { open: 'bg-label-success', closed: 'bg-label-secondary' };

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return parts.slice(0, 2).map(function (part) {
            return part.charAt(0).toUpperCase();
        }).join('') || '?';
    }

    function color(id) {
        return COLORS[Math.abs(parseInt(id, 10) || 0) % COLORS.length];
    }

    function timeLabel(value) {
        if (!value) {
            return '';
        }
        var date = new Date(String(value).replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return String(value);
        }
        var now = new Date();
        var sameDay = date.toDateString() === now.toDateString();
        if (sameDay) {
            return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
        var yesterday = new Date(now.getTime() - 86400000);
        if (date.toDateString() === yesterday.toDateString()) {
            return 'Ontem';
        }
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    }

    function boot() {
        var root = document.getElementById('ticketApp');
        if (!root) {
            return;
        }

        var listUrl = root.getAttribute('data-list-url') || '/tickets/list';
        var baseUrl = root.getAttribute('data-base-url') || '/tickets';
        var csrf = root.getAttribute('data-csrf') || '';
        var currentUserId = parseInt(root.getAttribute('data-current-user') || '0', 10);
        var selectedId = parseInt(root.getAttribute('data-selected') || '0', 10);

        var listEl = document.getElementById('ticketList');
        var searchEl = document.getElementById('ticketSearch');
        var historyBody = document.getElementById('ticketHistoryBody');
        var messagesEl = document.getElementById('ticketMessages');
        var emptyState = document.getElementById('ticketEmptyState');
        var noMessages = document.getElementById('ticketNoMessages');
        var footer = document.getElementById('ticketFooter');
        var closedNotice = document.getElementById('ticketClosedNotice');
        var form = document.getElementById('ticketForm');
        var input = document.getElementById('ticketInput');
        var subjectEl = document.getElementById('ticketSubject');
        var subtitleEl = document.getElementById('ticketSubtitle');
        var statusBadge = document.getElementById('ticketStatusBadge');
        var toggleStatus = document.getElementById('ticketToggleStatus');
        var headerActions = document.getElementById('ticketHeaderActions');
        var assignBtn = document.getElementById('ticketAssignBtn');
        var assignModalEl = document.getElementById('ticketAssignModal');
        var assignForm = document.getElementById('ticketAssignForm');
        var assignSelect = document.getElementById('ticketAssignSelect');
        var assignError = document.getElementById('ticketAssignError');
        var assignSubmit = document.getElementById('ticketAssignSubmit');
        var closeModalEl = document.getElementById('ticketCloseModal');
        var closeSubjectEl = document.getElementById('ticketCloseSubject');
        var closeConfirmBtn = document.getElementById('ticketCloseConfirm');
        var currentAssignedTo = assignBtn
            ? parseInt(assignBtn.getAttribute('data-assigned-to') || '0', 10)
            : 0;

        var params = new URLSearchParams(window.location.search);
        var scope = params.get('scope') || '';
        if (['open', 'unread', 'read', 'closed'].indexOf(scope) === -1) {
            scope = params.get('status') === 'closed' ? 'closed' : 'open';
        }
        var search = params.get('q') || '';
        if (searchEl && searchEl.value) {
            search = searchEl.value;
        }

        var CLOSED_PAGE = 30;
        var closedOffset = 0;
        var loadMoreBtn = document.getElementById('ticketLoadMore');

        function scrollBottom() {
            if (historyBody) {
                historyBody.scrollTop = historyBody.scrollHeight;
            }
        }

        function setActiveFilter() {
            root.querySelectorAll('.ticket-filter').forEach(function (btn) {
                var active = scope === btn.getAttribute('data-scope');
                btn.classList.toggle('btn-label-primary', active);
                btn.classList.toggle('btn-label-secondary', !active);
            });
        }

        function renderCounts(counts) {
            if (!counts) {
                return;
            }
            root.querySelectorAll('[data-count]').forEach(function (el) {
                var count = parseInt(counts[el.getAttribute('data-count')] || 0, 10);
                el.textContent = String(count);
                el.classList.toggle('d-none', count <= 0);
            });
        }

        function emptyLabel() {
            return {
                open: 'Nenhum ticket aberto.',
                unread: 'Nenhum ticket não lido.',
                read: 'Nenhum ticket lido.',
                closed: 'Nenhum ticket encerrado.'
            }[scope] || 'Nenhum ticket encontrado.';
        }

        function updateLoadMore(show) {
            if (loadMoreBtn) {
                loadMoreBtn.classList.toggle('d-none', !show);
            }
        }

        // Limpa a conversa quando o filtro atual não tem mais o ticket aberto.
        function clearSelection() {
            selectedId = 0;
            if (subjectEl) {
                subjectEl.textContent = 'Selecione um ticket';
            }
            if (subtitleEl) {
                subtitleEl.textContent = 'Escolha uma conversa na lista';
            }
            if (statusBadge) {
                statusBadge.className = 'badge bg-label-secondary';
                statusBadge.textContent = '';
            }
            if (footer) {
                footer.classList.add('d-none');
            }
            if (closedNotice) {
                closedNotice.classList.add('d-none');
            }
            if (headerActions) {
                headerActions.classList.add('d-none');
            }
            if (messagesEl) {
                messagesEl.innerHTML = '';
            }
            var contextWrap = document.getElementById('ticketContextWrap');
            if (contextWrap) {
                contextWrap.innerHTML = '';
                contextWrap.classList.add('d-none');
            }
            if (emptyState) {
                emptyState.classList.remove('d-none');
            }
            if (noMessages) {
                noMessages.classList.add('d-none');
            }
            if (listEl) {
                listEl.querySelectorAll('.chat-contact-list-item.active').forEach(function (li) {
                    li.classList.remove('active');
                });
            }
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', buildUrl(0));
            }
        }

        // Mantém a conversa aberta destacada também na sidebar.
        function syncSidebarActive() {
            document.querySelectorAll('#layout-menu .menu-ticket').forEach(function (li) {
                var link = li.querySelector('a.menu-link');
                var href = link ? (link.getAttribute('href') || '') : '';
                var active = selectedId > 0 && href.indexOf('ticket=' + selectedId) !== -1;
                li.classList.toggle('active', active);
                // Ao abrir a conversa ela é marcada como lida; some o ponto.
                if (active) {
                    var dot = li.querySelector('.menu-ticket-dot');
                    if (dot) {
                        dot.remove();
                    }
                }
            });
        }

        function buildUrl(ticketId) {
            var query = new URLSearchParams();
            if (search) {
                query.set('q', search);
            }
            if (scope !== 'open') {
                query.set('scope', scope);
            }
            if (ticketId) {
                query.set('ticket', ticketId);
            }
            var qs = query.toString();
            return baseUrl + (qs ? '?' + qs : '');
        }

        function scrollSelectedIntoView() {            if (!listEl || !selectedId) {
                return;
            }
            var item = listEl.querySelector('.chat-contact-list-item[data-ticket-id="' + selectedId + '"]');
            if (!item) {
                return;
            }
            var container = listEl.closest('.sidebar-body');
            if (!container) {
                item.scrollIntoView({ block: 'nearest' });
                return;
            }
            var itemRect = item.getBoundingClientRect();
            var containerRect = container.getBoundingClientRect();
            if (itemRect.top < containerRect.top || itemRect.bottom > containerRect.bottom) {
                container.scrollTop += itemRect.top - containerRect.top
                    - (container.clientHeight - itemRect.height) / 2;
            }
        }

        function removeSidebarTicket(ticketId) {
            document.querySelectorAll('#layout-menu .menu-ticket').forEach(function (li) {
                var link = li.querySelector('a.menu-link');
                var href = link ? link.getAttribute('href') || '' : '';
                if (href.indexOf('ticket=' + ticketId) !== -1) {
                    li.remove();
                }
            });
        }

        function sidebarTicketLabel(ticket) {
            var subject = String(ticket.subject || '').trim();
            var meta = '';
            var match = subject.match(/^OS\s*#(\d+)\s*[-–—]\s*(.+)$/u);
            if (match) {
                subject = match[2].trim();
                meta = '#' + match[1];
            }
            if (!subject) {
                subject = 'Ticket #' + ticket.id;
            }
            if (subject.length > 30) {
                subject = subject.slice(0, 29) + '…';
            }
            return { label: subject, meta: meta };
        }

        function renderSidebarTickets(tickets) {
            var parent = document.querySelector('#layout-menu [data-menu-key="tickets"]');
            if (!parent) {
                return;
            }
            var menu = parent.querySelector('.menu-sub');
            if (!menu) {
                return;
            }

            menu.innerHTML = '';

            tickets
                .filter(function (ticket) {
                    return (ticket.status || 'open') === 'open';
                })
                .slice(0, 6)
                .forEach(function (ticket) {
                    var info = sidebarTicketLabel(ticket);
                    var unread = parseInt(ticket.unread || 0, 10);
                    var li = document.createElement('li');
                    li.className = 'menu-item menu-ticket' + (parseInt(ticket.id, 10) === selectedId ? ' active' : '');
                    li.innerHTML = '<a href="/tickets?ticket=' + ticket.id + '" class="menu-link">'
                        + '<div class="menu-ticket-title">' + esc(info.label) + '</div>'
                        + (info.meta ? '<small class="menu-ticket-meta">' + esc(info.meta) + '</small>' : '')
                        + (unread > 0 ? '<span class="menu-ticket-dot" title="' + unread + ' não lida(s)"></span>' : '')
                        + '</a>';
                    menu.appendChild(li);
                });

            var all = document.createElement('li');
            all.className = 'menu-item menu-ticket-all';
            all.innerHTML = '<a href="/tickets" class="menu-link"><div>Ver todos os tickets</div></a>';
            menu.appendChild(all);

            syncSidebarActive();
        }

        function refreshSidebarTickets() {
            fetch(listUrl + '?status=open', {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && Array.isArray(data.tickets)) {
                        renderSidebarTickets(data.tickets);
                    }
                })
                .catch(function () {});
        }

        function syncSidebarUnread(tickets) {
            // A sidebar lista apenas tickets abertos: remove os que foram encerrados.
            tickets.forEach(function (ticket) {
                if (ticket.status === 'closed') {
                    removeSidebarTicket(ticket.id);
                }
            });

            var unreadByTicket = {};
            tickets.forEach(function (ticket) {
                unreadByTicket[String(ticket.id)] = parseInt(ticket.unread || 0, 10);
            });

            document.querySelectorAll('#layout-menu .menu-ticket').forEach(function (li) {
                var link = li.querySelector('a.menu-link');
                if (!link) {
                    return;
                }
                var match = (link.getAttribute('href') || '').match(/ticket=(\d+)/);
                if (!match) {
                    return;
                }
                var unread = unreadByTicket[match[1]] || 0;
                var dot = li.querySelector('.menu-ticket-dot');
                if (unread > 0) {
                    if (!dot) {
                        dot = document.createElement('span');
                        dot.className = 'menu-ticket-dot';
                        link.appendChild(dot);
                    }
                    dot.title = unread + ' não lida(s)';
                } else if (dot) {
                    dot.remove();
                }
            });
        }

        function renderList(tickets, append) {
            if (!listEl) {
                return;
            }

            var container = listEl.closest('.sidebar-body');
            var scrollTop = container ? container.scrollTop : 0;

            if (!append) {
                listEl.innerHTML = '';
            }

            if (!tickets.length && !append) {
                listEl.innerHTML = '<li class="text-center text-body-secondary py-6 px-4">' + esc(emptyLabel()) + '</li>';
                if (container) {
                    container.scrollTop = 0;
                }
                return;
            }

            tickets.forEach(function (ticket) {
                var item = document.createElement('li');
                var unread = parseInt(ticket.unread || 0, 10);
                var isSelected = parseInt(ticket.id, 10) === selectedId;
                var showUnread = scope !== 'closed' && unread > 0 && !isSelected;
                item.className = 'chat-contact-list-item mb-1' + (isSelected ? ' active' : '')
                    + (showUnread ? ' is-unread' : '');
                item.setAttribute('data-ticket-id', ticket.id);
                item.innerHTML = '<a href="' + buildUrl(ticket.id) + '" class="d-flex align-items-center text-reset text-decoration-none p-0">'
                    + '<div class="avatar avatar-sm flex-shrink-0">'
                    + '<span class="avatar-initial rounded-circle bg-label-' + color(ticket.id) + '">' + esc(initials(ticket.creator_name)) + '</span>'
                    + '</div>'
                    + '<div class="chat-contact-info flex-grow-1 ms-3 overflow-hidden">'
                    + '<div class="d-flex justify-content-between align-items-center gap-2">'
                    + '<h6 class="chat-contact-name text-truncate m-0 fw-semibold">' + esc(ticket.subject) + '</h6>'
                    + '<small class="chat-contact-list-item-time">' + esc(timeLabel(ticket.last_message_at || ticket.created_at)) + '</small>'
                    + '</div>'
                    + '<div class="d-flex justify-content-between align-items-center gap-2">'
                    + '<small class="chat-contact-status text-truncate">' + esc(ticket.last_message || 'Sem mensagens ainda') + '</small>'
                    + (showUnread ? '<span class="badge rounded-pill bg-primary unread-badge">' + unread + '</span>' : '')
                    + '</div>'
                    + '<div class="d-flex justify-content-between align-items-center gap-2 mt-1">'
                    + '<small class="chat-contact-assignee text-body-secondary text-truncate">'
                    + '<i class="icon-base ti tabler-user-check"></i> ' + esc(ticket.assigned_name || 'Sem responsável')
                    + '</small>'
                    + '</div>'
                    + '</div></a>';
                listEl.appendChild(item);
            });

            // Preserva a rolagem da lista entre as atualizações periódicas.
            if (container && !append) {
                container.scrollTop = scrollTop;
            }
        }

        function loadList(scrollToSelected, append, selectFirst) {
            var params = new URLSearchParams();
            if (search) {
                params.set('q', search);
            }
            if (scope !== 'open') {
                params.set('scope', scope);
            }
            if (scope === 'closed') {
                params.set('limit', String(CLOSED_PAGE));
                params.set('offset', String(closedOffset));
            }

            return fetch(listUrl + (params.toString() ? '?' + params.toString() : ''), {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data || !Array.isArray(data.tickets)) {
                        return;
                    }
                    renderList(data.tickets, append === true);
                    if (scope === 'open' || scope === 'unread') {
                        syncSidebarUnread(data.tickets);
                    }
                    renderCounts(data.counts);
                    updateLoadMore(scope === 'closed' && data.has_more === true);

                    if (selectFirst && !append) {
                        var stillListed = data.tickets.some(function (ticket) {
                            return parseInt(ticket.id, 10) === selectedId;
                        });
                        if (!stillListed) {
                            if (data.tickets.length && scope !== 'unread') {
                                selectTicket(parseInt(data.tickets[0].id, 10), true, true);
                            } else {
                                // Em "Não lidos", selecionar sozinho marcaria a conversa como lida.
                                clearSelection();
                            }
                        }
                    }

                    if (scrollToSelected) {
                        scrollSelectedIntoView();
                    }
                })
                .catch(function () {})
                .finally(function () {
                    if (loadMoreBtn) {
                        loadMoreBtn.disabled = false;
                    }
                });
        }

        function renderMessages(messages, scrollToBottom) {
            if (!messagesEl) {
                return;
            }

            var previousDistance = 0;
            if (historyBody) {
                previousDistance = historyBody.scrollHeight - historyBody.scrollTop - historyBody.clientHeight;
            }

            // O polling recria as mensagens a cada 10s: preserva os resumos abertos.
            var openSummaries = {};
            messagesEl.querySelectorAll('.collapse.show').forEach(function (el) {
                if (el.id) {
                    openSummaries[el.id] = true;
                }
            });

            messagesEl.innerHTML = '';
            messages.forEach(function (message) {
                if (message.kind === 'system') {
                    var summary = document.createElement('li');
                    summary.className = 'chat-message ticket-system-message';
                    var summaryId = 'ticketSummary-' + (message.id || Math.random().toString(36).slice(2));
                    summary.innerHTML = '<div class="ticket-system-card">'
                        + '<button type="button" class="btn btn-text-secondary p-0 d-flex align-items-center gap-2 w-100 text-start"'
                        + ' data-bs-toggle="collapse" data-bs-target="#' + summaryId + '"'
                        + ' aria-expanded="false" aria-controls="' + summaryId + '">'
                        + '<i class="icon-base ti tabler-clipboard-text text-primary"></i>'
                        + '<span class="fw-semibold text-primary">Resumo automático da OS</span>'
                        + '<i class="icon-base ti tabler-chevron-down ms-auto"></i>'
                        + '</button>'
                        + '<div class="collapse" id="' + summaryId + '">'
                        + '<div class="ticket-system-content mt-2">' + esc(message.message).replace(/\n/g, '<br>') + '</div>'
                        + '</div></div>';
                    messagesEl.appendChild(summary);
                    return;
                }

                var mine = parseInt(message.user_id, 10) === currentUserId;
                var avatar = '<div class="avatar avatar-sm"><span class="avatar-initial rounded-circle bg-label-'
                    + color(message.user_id) + '">' + esc(initials(message.user_name)) + '</span></div>';

                var item = document.createElement('li');
                item.className = 'chat-message' + (mine ? ' chat-message-right' : '');
                item.innerHTML = '<div class="d-flex overflow-hidden">'
                    + (mine ? '' : '<div class="user-avatar flex-shrink-0 me-3">' + avatar + '</div>')
                    + '<div class="chat-message-wrapper flex-grow-1">'
                    + '<div class="chat-message-text"><p class="mb-0">' + esc(message.message).replace(/\n/g, '<br>') + '</p></div>'
                    + '<div class="text-body-secondary mt-1' + (mine ? ' text-end' : '') + '">'
                    + '<small>' + esc(message.user_name) + ' · ' + esc(timeLabel(message.created_at)) + '</small>'
                    + '</div></div>'
                    + (mine ? '<div class="user-avatar flex-shrink-0 ms-3">' + avatar + '</div>' : '')
                    + '</div>';
                messagesEl.appendChild(item);
            });

            if (noMessages) {
                noMessages.classList.toggle('d-none', messages.length > 0);
            }

            // Reabre os resumos que estavam expandidos antes da atualização.
            Object.keys(openSummaries).forEach(function (id) {
                var body = document.getElementById(id);
                if (!body) {
                    return;
                }
                body.classList.add('show');
                var card = body.closest('.ticket-system-card');
                var toggle = card ? card.querySelector('[data-bs-target="#' + id + '"]') : null;
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });

            if (scrollToBottom !== false) {
                scrollBottom();
            } else if (historyBody) {
                // Mantém a posição relativa ao final quando o usuário está lendo mensagens antigas.
                historyBody.scrollTop = historyBody.scrollHeight - historyBody.clientHeight - previousDistance;
            }
        }

        function applyTicket(ticket, messages, contextHtml, preserveScroll) {
            selectedId = parseInt(ticket.id, 10);
            currentAssignedTo = parseInt(ticket.assigned_to || 0, 10);
            if (assignBtn) {
                assignBtn.setAttribute('data-assigned-to', String(currentAssignedTo));
                assignBtn.title = ticket.assigned_name
                    ? 'Responsável: ' + ticket.assigned_name
                    : 'Sem responsável';
            }
            syncSidebarActive();

            var contextWrap = document.getElementById('ticketContextWrap');
            if (contextWrap) {
                // O contexto é re-renderizado no polling: mantém o bloco aberto.
                var previousContextBody = contextWrap.querySelector('#ticketContextBody');
                var contextWasOpen = !!(previousContextBody && previousContextBody.classList.contains('show'));
                if (contextHtml) {
                    contextWrap.innerHTML = contextHtml;
                    if (contextWasOpen) {
                        var contextBody = contextWrap.querySelector('#ticketContextBody');
                        var contextToggle = contextWrap.querySelector('[data-bs-target="#ticketContextBody"]');
                        if (contextBody) {
                            contextBody.classList.add('show');
                        }
                        if (contextToggle) {
                            contextToggle.setAttribute('aria-expanded', 'true');
                        }
                    }
                    contextWrap.classList.remove('d-none');
                } else {
                    contextWrap.innerHTML = '';
                    contextWrap.classList.add('d-none');
                }
            }

            if (subjectEl) {
                subjectEl.textContent = ticket.subject;
            }
            if (subtitleEl) {
                var parts = [ticket.creator_name || 'Sistema'];
                if (ticket.assigned_name) {
                    parts.push('com ' + ticket.assigned_name);
                }
                if (ticket.order_number) {
                    parts.push('OS #' + String(ticket.order_number).padStart(4, '0'));
                }
                subtitleEl.textContent = parts.join(' · ');
            }
            if (statusBadge) {
                statusBadge.className = 'badge ' + (STATUS_BADGE[ticket.status] || 'bg-label-secondary');
                statusBadge.textContent = STATUS_LABEL[ticket.status] || ticket.status;
            }
            if (toggleStatus) {
                toggleStatus.setAttribute('data-status', ticket.status);
                toggleStatus.innerHTML = '<i class="icon-base ti ' + (ticket.status === 'closed' ? 'tabler-lock-open' : 'tabler-lock') + '"></i>';
            }
            if (emptyState) {
                emptyState.classList.add('d-none');
            }
            if (headerActions) {
                headerActions.classList.remove('d-none');
            }
            var isClosed = ticket.status === 'closed';
            if (footer) {
                footer.classList.toggle('d-none', isClosed);
            }
            if (closedNotice) {
                closedNotice.classList.toggle('d-none', !isClosed);
            }

            var shouldScroll = true;
            if (preserveScroll && historyBody) {
                var distanceFromBottom = historyBody.scrollHeight - historyBody.scrollTop - historyBody.clientHeight;
                shouldScroll = distanceFromBottom < 80;
            }
            renderMessages(messages || [], shouldScroll);

            root.querySelectorAll('.chat-contact-list-item').forEach(function (li) {
                var isActive = parseInt(li.getAttribute('data-ticket-id'), 10) === selectedId;
                li.classList.toggle('active', isActive);
                if (isActive) {
                    var unreadBadge = li.querySelector('.unread-badge');
                    if (unreadBadge) {
                        unreadBadge.remove();
                    }
                }
            });
        }

        function selectTicket(id, updateUrl, preserveScroll) {
            fetch(baseUrl + '/' + id, {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.ticket) {
                        return;
                    }
                    applyTicket(data.ticket, data.messages || [], data.context_html || '', preserveScroll);
                    if (updateUrl !== false && window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', buildUrl(id));
                    }
                    // Abrir a conversa marca como lida: atualiza as abas Lidos/Não lidos.
                    if (scope === 'unread' || scope === 'read') {
                        loadList();
                    }
                    var contacts = document.getElementById('app-chat-contacts');
                    if (contacts && window.innerWidth < 992) {
                        contacts.classList.remove('show');
                    }
                })
                .catch(function () {});
        }

        // Selecionar conversa
        if (listEl) {
            listEl.addEventListener('click', function (event) {
                var item = event.target.closest('.chat-contact-list-item');
                if (!item) {
                    return;
                }
                event.preventDefault();
                selectTicket(parseInt(item.getAttribute('data-ticket-id'), 10));
            });
        }

        // Busca
        var searchTimer = null;
        if (searchEl) {
            searchEl.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    search = searchEl.value.trim();
                    closedOffset = 0;
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', buildUrl(selectedId));
                    }
                    loadList(true, false, true);
                }, 300);
            });
        }

        // Filtros
        root.querySelectorAll('.ticket-filter').forEach(function (btn) {
            btn.addEventListener('click', function () {
                scope = btn.getAttribute('data-scope') || 'open';
                closedOffset = 0;
                setActiveFilter();
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', buildUrl(selectedId));
                }
                loadList(true, false, true);
            });
        });

        // Paginação da aba Encerrados
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function () {
                if (scope !== 'closed') {
                    return;
                }
                loadMoreBtn.disabled = true;
                closedOffset += CLOSED_PAGE;
                loadList(false, true);
            });
        }

        // Enviar mensagem
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!selectedId) {
                    return;
                }
                var message = (input && input.value ? input.value : '').trim();
                if (!message) {
                    return;
                }

                var button = document.getElementById('ticketSendBtn');
                if (button) {
                    button.disabled = true;
                }

                fetch(baseUrl + '/' + selectedId + '/messages', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        Accept: 'application/json'
                    },
                    body: 'csrf_token=' + encodeURIComponent(csrf) + '&message=' + encodeURIComponent(message),
                    cache: 'no-store'
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            if (input) {
                                input.value = '';
                            }
                            selectTicket(selectedId);
                            loadList();
                        }
                    })
                    .catch(function () {})
                    .finally(function () {
                        if (button) {
                            button.disabled = false;
                        }
                    });
            });
        }

        // Encerrar / reabrir
        function changeStatus(next) {
            if (!selectedId) {
                return Promise.resolve(false);
            }
            return fetch(baseUrl + '/' + selectedId + '/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    Accept: 'application/json'
                },
                body: 'csrf_token=' + encodeURIComponent(csrf) + '&status=' + encodeURIComponent(next),
                cache: 'no-store'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        if (statusBadge) {
                            statusBadge.className = 'badge ' + (STATUS_BADGE[next] || 'bg-label-secondary');
                            statusBadge.textContent = STATUS_LABEL[next] || next;
                        }
                        if (toggleStatus) {
                            toggleStatus.setAttribute('data-status', next);
                            toggleStatus.innerHTML = '<i class="icon-base ti ' + (next === 'closed' ? 'tabler-lock-open' : 'tabler-lock') + '"></i>';
                        }
                        if (next === 'closed') {
                            removeSidebarTicket(selectedId);
                        }
                        closedOffset = 0;
                        refreshSidebarTickets();
                        loadList();
                        selectTicket(selectedId, false, true);
                        return true;
                    }
                    return false;
                })
                .catch(function () { return false; });
        }

        if (toggleStatus) {
            toggleStatus.addEventListener('click', function () {
                if (!selectedId) {
                    return;
                }
                var next = toggleStatus.getAttribute('data-status') === 'closed' ? 'open' : 'closed';
                if (next === 'closed') {
                    if (closeSubjectEl) {
                        closeSubjectEl.textContent = subjectEl ? subjectEl.textContent : 'selecionado';
                    }
                    if (closeModalEl && window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(closeModalEl).show();
                        return;
                    }
                    var ticketLabel = subjectEl ? subjectEl.textContent : 'este ticket';
                    if (!window.confirm('Encerrar o ticket "' + ticketLabel + '"? Depois será necessário reabri-lo para enviar novas mensagens.')) {
                        return;
                    }
                }
                changeStatus(next);
            });
        }

        if (closeConfirmBtn) {
            closeConfirmBtn.addEventListener('click', function () {
                closeConfirmBtn.disabled = true;
                changeStatus('closed').then(function (ok) {
                    closeConfirmBtn.disabled = false;
                    if (ok && closeModalEl && window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(closeModalEl).hide();
                    }
                });
            });
        }

        // Alterar responsável
        if (assignBtn && assignForm) {
            assignBtn.addEventListener('click', function () {
                if (!selectedId) {
                    return;
                }
                if (assignSelect) {
                    assignSelect.value = currentAssignedTo ? String(currentAssignedTo) : '';
                }
                if (assignError) {
                    assignError.classList.add('d-none');
                }
                if (assignModalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(assignModalEl).show();
                }
            });

            assignForm.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!selectedId) {
                    return;
                }
                if (assignSubmit) {
                    assignSubmit.disabled = true;
                }
                if (assignError) {
                    assignError.classList.add('d-none');
                }

                var assignedTo = assignSelect ? assignSelect.value : '';
                fetch(baseUrl + '/' + selectedId + '/assign', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        Accept: 'application/json'
                    },
                    body: 'csrf_token=' + encodeURIComponent(csrf) + '&assigned_to=' + encodeURIComponent(assignedTo),
                    cache: 'no-store'
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data || !data.ok) {
                            throw new Error('assign_failed');
                        }
                        if (assignModalEl && window.bootstrap && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(assignModalEl).hide();
                        }
                        selectTicket(selectedId, false, true);
                        loadList();
                    })
                    .catch(function () {
                        if (assignError) {
                            assignError.textContent = 'Não foi possível alterar o responsável. Verifique os dados.';
                            assignError.classList.remove('d-none');
                        }
                    })
                    .finally(function () {
                        if (assignSubmit) {
                            assignSubmit.disabled = false;
                        }
                    });
            });
        }

        // Novo ticket
        var newForm = document.getElementById('ticketNewForm');
        if (newForm) {
            newForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var submit = document.getElementById('ticketNewSubmit');
                var errorBox = document.getElementById('ticketNewError');
                if (submit) {
                    submit.disabled = true;
                }
                if (errorBox) {
                    errorBox.classList.add('d-none');
                }

                var body = new URLSearchParams(new FormData(newForm));

                fetch(baseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        Accept: 'application/json'
                    },
                    body: body.toString(),
                    cache: 'no-store'
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data && data.ok && data.id) {
                            window.location.href = baseUrl + '?ticket=' + data.id;
                            return;
                        }
                        throw new Error('create_failed');
                    })
                    .catch(function () {
                        if (errorBox) {
                            errorBox.textContent = 'Não foi possível criar o ticket. Verifique os dados.';
                            errorBox.classList.remove('d-none');
                        }
                    })
                    .finally(function () {
                        if (submit) {
                            submit.disabled = false;
                        }
                    });
            });
        }

        setActiveFilter();
        syncSidebarActive();
        scrollBottom();
        window.setTimeout(scrollSelectedIntoView, 0);
        window.addEventListener('load', scrollSelectedIntoView);

        // Atualização periódica da conversa aberta e da lista.
        setInterval(function () {
            if (document.hidden || !selectedId) {
                return;
            }
            selectTicket(selectedId, false, true);
            // Encerrados tem paginação: não recarrega para não perder as páginas carregadas.
            if (scope !== 'closed') {
                loadList();
            }
        }, 10000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
