/**
 * Conversa do ticket embutida na página da OS.
 * Envia mensagens via /tickets/{id}/messages e alterna o status.
 */
(function () {
    'use strict';

    var root = document.getElementById('osTicket');
    if (!root) {
        return;
    }

    var sendUrl = root.getAttribute('data-send-url') || '';
    var statusUrl = root.getAttribute('data-status-url') || '';
    var csrf = root.getAttribute('data-csrf') || '';
    var currentUser = parseInt(root.getAttribute('data-current-user') || '0', 10);

    var messagesEl = document.getElementById('osTicketMessages');
    var form = document.getElementById('osTicketForm');
    var input = document.getElementById('osTicketInput');
    var sendBtn = document.getElementById('osTicketSendBtn');
    var statusBadge = document.getElementById('osTicketStatusBadge');
    var toggleStatus = document.getElementById('osTicketToggleStatus');

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

    function scrollBottom() {
        if (messagesEl) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function renderMessage(message) {
        if (!messagesEl) {
            return;
        }
        var mine = parseInt(message.user_id, 10) === currentUser;
        var el = document.createElement('div');
        el.className = 'os-ticket-message' + (mine ? ' is-mine' : '');
        el.innerHTML = (mine ? '' : '<span class="avatar avatar-sm flex-shrink-0"><span class="avatar-initial rounded-circle bg-label-primary">'
                + esc(initials(message.user_name)) + '</span></span>')
            + '<div class="os-ticket-bubble"><div class="os-ticket-text">'
            + esc(message.message).replace(/\n/g, '<br>')
            + '</div><small class="text-body-secondary">' + esc(message.user_name) + ' · agora</small></div>';
        messagesEl.appendChild(el);
        scrollBottom();
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var message = (input && input.value ? input.value : '').trim();
            if (!message) {
                return;
            }

            if (sendBtn) {
                sendBtn.disabled = true;
            }

            fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    Accept: 'application/json'
                },
                body: 'csrf_token=' + encodeURIComponent(csrf) + '&message=' + encodeURIComponent(message),
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && data.message) {
                        var empty = messagesEl ? messagesEl.querySelector('p.text-body-secondary') : null;
                        if (empty) {
                            empty.remove();
                        }
                        renderMessage(data.message);
                        if (input) {
                            input.value = '';
                            input.focus();
                        }
                    }
                })
                .catch(function () {})
                .finally(function () {
                    if (sendBtn) {
                        sendBtn.disabled = false;
                    }
                });
        });
    }

    if (toggleStatus && statusUrl) {
        toggleStatus.addEventListener('click', function () {
            var next = toggleStatus.getAttribute('data-status') === 'closed' ? 'open' : 'closed';
            toggleStatus.disabled = true;

            fetch(statusUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    Accept: 'application/json'
                },
                body: 'csrf_token=' + encodeURIComponent(csrf) + '&status=' + encodeURIComponent(next),
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        return;
                    }
                    toggleStatus.setAttribute('data-status', next);
                    if (statusBadge) {
                        statusBadge.className = 'badge ' + (next === 'closed' ? 'bg-label-secondary' : 'bg-label-success');
                        statusBadge.textContent = next === 'closed' ? 'Encerrado' : 'Em aberto';
                    }
                    toggleStatus.innerHTML = '<i class="icon-base ti ' + (next === 'closed' ? 'tabler-lock-open' : 'tabler-lock') + '"></i> '
                        + (next === 'closed' ? 'Reabrir' : 'Encerrar');
                })
                .catch(function () {})
                .finally(function () {
                    toggleStatus.disabled = false;
                });
        });
    }

    scrollBottom();
})();
