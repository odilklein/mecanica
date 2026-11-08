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
        var form = document.getElementById('ticketForm');
        var input = document.getElementById('ticketInput');
        var subjectEl = document.getElementById('ticketSubject');
        var subtitleEl = document.getElementById('ticketSubtitle');
        var statusBadge = document.getElementById('ticketStatusBadge');
        var toggleStatus = document.getElementById('ticketToggleStatus');

        var params = new URLSearchParams(window.location.search);
        var scope = params.get('scope') === 'unread' ? 'unread' : (params.get('status') === 'closed' ? 'closed' : 'all');
        var statusFilter = params.get('status') === 'closed' ? 'closed' : '';
        var search = params.get('q') || '';
        if (searchEl && searchEl.value) {
            search = searchEl.value;
        }

        function scrollBottom() {
            if (historyBody) {
                historyBody.scrollTop = historyBody.scrollHeight;
            }
        }

        function setActiveFilter() {
            root.querySelectorAll('.ticket-filter').forEach(function (btn) {
                var matchScope = btn.getAttribute('data-scope');
                var matchStatus = btn.getAttribute('data-status');
                var active = false;
                if (matchScope) {
                    active = scope === matchScope;
                } else if (matchStatus) {
                    active = statusFilter === matchStatus;
                }
                btn.classList.toggle('btn-label-primary', active);
                btn.classList.toggle('btn-label-secondary', !active);
            });
        }

        function renderList(tickets) {
            if (!listEl) {
                return;
            }

            listEl.innerHTML = '';
            if (!tickets.length) {
                listEl.innerHTML = '<li class="text-center text-body-secondary py-6 px-4">Nenhum ticket encontrado.</li>';
                return;
            }

            tickets.forEach(function (ticket) {
                var item = document.createElement('li');
                var unread = parseInt(ticket.unread || 0, 10);
                item.className = 'chat-contact-list-item mb-1' + (parseInt(ticket.id, 10) === selectedId ? ' active' : '');
                item.setAttribute('data-ticket-id', ticket.id);
                item.innerHTML = '<a href="' + baseUrl + '?ticket=' + ticket.id + '" class="d-flex align-items-center text-reset text-decoration-none p-0">'
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
                    + (unread > 0 ? '<span class="badge rounded-pill bg-primary">' + unread + '</span>' : '')
                    + '</div></div></a>';
                listEl.appendChild(item);
            });
        }

        function loadList() {
            var params = new URLSearchParams();
            if (search) {
                params.set('q', search);
            }
            if (scope === 'unread') {
                params.set('scope', 'unread');
            } else if (scope === 'closed') {
                params.set('status', 'closed');
            }

            fetch(listUrl + (params.toString() ? '?' + params.toString() : ''), {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && Array.isArray(data.tickets)) {
                        renderList(data.tickets);
                    }
                })
                .catch(function () {});
        }

        function renderMessages(messages) {
            if (!messagesEl) {
                return;
            }

            messagesEl.innerHTML = '';
            messages.forEach(function (message) {
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
            scrollBottom();
        }

        function applyTicket(ticket, messages) {
            selectedId = parseInt(ticket.id, 10);

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
            if (footer) {
                footer.classList.remove('d-none');
            }

            renderMessages(messages || []);

            root.querySelectorAll('.chat-contact-list-item').forEach(function (li) {
                li.classList.toggle('active', parseInt(li.getAttribute('data-ticket-id'), 10) === selectedId);
            });
        }

        function selectTicket(id, updateUrl) {
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
                    applyTicket(data.ticket, data.messages || []);
                    if (updateUrl !== false && window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', baseUrl + '?ticket=' + id);
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
                    loadList();
                }, 300);
            });
        }

        // Filtros
        root.querySelectorAll('.ticket-filter').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.hasAttribute('data-scope')) {
                    scope = btn.getAttribute('data-scope') || 'all';
                    statusFilter = '';
                } else {
                    statusFilter = btn.getAttribute('data-status') || '';
                    scope = statusFilter === 'closed' ? 'closed' : 'all';
                }
                setActiveFilter();
                loadList();
            });
        });

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
        if (toggleStatus) {
            toggleStatus.addEventListener('click', function () {
                if (!selectedId) {
                    return;
                }
                var next = toggleStatus.getAttribute('data-status') === 'closed' ? 'open' : 'closed';
                fetch(baseUrl + '/' + selectedId + '/status', {
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
                            toggleStatus.setAttribute('data-status', next);
                            toggleStatus.innerHTML = '<i class="icon-base ti ' + (next === 'closed' ? 'tabler-lock-open' : 'tabler-lock') + '"></i>';
                            loadList();
                        }
                    })
                    .catch(function () {});
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
        scrollBottom();

        // Atualização periódica da conversa aberta.
        setInterval(function () {
            if (document.hidden || !selectedId) {
                return;
            }
            selectTicket(selectedId, false);
        }, 10000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
