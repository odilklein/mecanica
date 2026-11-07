// =============================================
// CHAT / TICKET DA OS — carrega, envia e atualiza
// as mensagens do ticket da ordem de serviço.
// =============================================
(function () {
    'use strict';

    function esc(value) {
        var d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function initials(name) {
        var matches = String(name || '').match(/\b\w/g) || [];
        return matches.slice(0, 2).map(function (l) { return l.toUpperCase(); }).join('') || '?';
    }

    function timeLabel(value) {
        if (!value) {
            return '';
        }
        var date = new Date(String(value).replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return String(value);
        }
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    function boot() {
        var root = document.getElementById('osChat');
        if (!root) {
            return;
        }

        var endpoint = root.getAttribute('data-endpoint');
        var csrf = root.getAttribute('data-csrf') || '';
        var currentUserId = parseInt(root.getAttribute('data-current-user') || '0', 10);
        var list = document.getElementById('osChatList');
        var empty = document.getElementById('osChatEmpty');
        var body = document.getElementById('osChatBody');
        var form = document.getElementById('osChatForm');
        var input = document.getElementById('osChatInput');
        var palette = ['primary', 'info', 'success', 'warning', 'danger', 'secondary'];

        if (!endpoint || !list || !form || !input) {
            return;
        }

        function scrollToBottom() {
            if (body) {
                body.scrollTop = body.scrollHeight;
            }
        }

        function render(messages) {
            list.innerHTML = '';
            if (empty) {
                empty.hidden = messages.length > 0;
            }

            messages.forEach(function (m) {
                var mine = Number(m.user_id) === currentUserId;
                var color = palette[Math.abs(Number(m.user_id) || 0) % palette.length];
                var avatar = '<div class="user-avatar flex-shrink-0 ' + (mine ? 'ms-3' : 'me-3') + '">'
                    + '<div class="avatar avatar-sm">'
                    + '<span class="avatar-initial rounded-circle bg-label-' + color + '">' + esc(initials(m.user_name)) + '</span>'
                    + '</div></div>';

                var wrapper = '<div class="chat-message-wrapper flex-grow-1">'
                    + '<div class="chat-message-text"><p class="mb-0">' + esc(m.message).replace(/\n/g, '<br>') + '</p></div>'
                    + '<div class="text-body-secondary mt-1 ' + (mine ? 'text-end' : '') + '">'
                    + '<small>' + esc(m.user_name) + ' · ' + esc(timeLabel(m.created_at)) + '</small>'
                    + '</div></div>';

                var li = document.createElement('li');
                li.className = 'chat-message' + (mine ? ' chat-message-right' : '');
                li.innerHTML = '<div class="d-flex overflow-hidden">' + (mine ? wrapper + avatar : avatar + wrapper) + '</div>';
                list.appendChild(li);
            });

            scrollToBottom();
        }

        function load() {
            if (document.hidden) {
                return;
            }
            fetch(endpoint, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && Array.isArray(data.messages)) {
                        render(data.messages);
                    }
                })
                .catch(function () {});
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var message = input.value.trim();
            if (!message) {
                return;
            }

            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: 'csrf_token=' + encodeURIComponent(csrf) + '&message=' + encodeURIComponent(message),
                cache: 'no-store'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        input.value = '';
                        load();
                    } else {
                        window.alert('Não foi possível enviar a mensagem.');
                    }
                })
                .catch(function () {
                    window.alert('Não foi possível enviar a mensagem.');
                })
                .finally(function () {
                    if (button) {
                        button.disabled = false;
                    }
                });
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.requestSubmit();
            }
        });

        load();
        setInterval(load, 8000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
