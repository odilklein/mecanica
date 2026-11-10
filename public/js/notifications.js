// =============================================
// NOTIFICAÇÕES — dropdown do header no padrão
// Vuexy (navbar-dropdown / dropdown-notifications).
// =============================================
(function () {
    'use strict';

    var TYPE_META = {
        os_status: { label: 'Ordem de serviço', icon: 'arrows-exchange', color: 'bg-label-info' },
        os_created: { label: 'Nova ordem de serviço', icon: 'clipboard-plus', color: 'bg-label-primary' },
        os_responsible: { label: 'Responsável', icon: 'user-check', color: 'bg-label-primary' },
        part: { label: 'Pedido de peça', icon: 'package', color: 'bg-label-warning' },
        task: { label: 'Tarefa', icon: 'list-check', color: 'bg-label-success' },
        ticket: { label: 'Ticket', icon: 'messages', color: 'bg-label-info' }
    };

    function esc(value) {
        var d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function initialsOf(name) {
        var matches = String(name || '').match(/\b\w/g) || [];
        return matches.slice(0, 2).map(function (letter) {
            return letter.toUpperCase();
        }).join('') || '?';
    }

    function timeAgo(value) {
        if (!value) {
            return '';
        }

        var date = new Date(String(value).replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return String(value);
        }

        var seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
        if (seconds < 60) {
            return 'agora';
        }
        if (seconds < 3600) {
            return 'há ' + Math.floor(seconds / 60) + ' min';
        }
        if (seconds < 86400) {
            return 'há ' + Math.floor(seconds / 3600) + ' h';
        }
        if (seconds < 2592000) {
            var days = Math.floor(seconds / 86400);
            return 'há ' + days + (days === 1 ? ' dia' : ' dias');
        }
        return date.toLocaleDateString('pt-BR');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('notifRoot');
        if (!root) {
            return;
        }

        var badge = document.getElementById('notifBadge');
        var count = document.getElementById('notifCount');
        var list = document.getElementById('notifList');
        var markAllBtn = document.getElementById('notifMarkAll');
        var csrf = root.getAttribute('data-csrf') || '';

        function setUnread(unread) {
            if (badge) {
                badge.hidden = unread === 0;
            }
            if (count) {
                count.hidden = unread === 0;
                count.textContent = unread + (unread === 1 ? ' nova' : ' novas');
            }
        }

        function itemHtml(n) {
            var meta = TYPE_META[n.type] || { label: 'Notificação', icon: 'bell', color: 'bg-label-secondary' };
            var unread = !n.read_at;
            var href = n.link || '';
            var actor = n.created_by_name || n.user_name || 'Sistema';
            var recipient = n.user_name || '';
            var subtitle = n.message
                ? esc(n.message)
                : (href ? '<a href="' + esc(href) + '">Abrir detalhes</a>' : '');

            return '<li class="list-group-item list-group-item-action dropdown-notifications-item'
                + (unread ? '' : ' marked-as-read')
                + '" data-id="' + esc(n.id) + '"'
                + (href ? ' data-href="' + esc(href) + '"' : '') + '>'
                + '<div class="d-flex">'
                + '<div class="flex-shrink-0 me-3">'
                + '<div class="avatar">'
                + '<span class="avatar-initial rounded-circle ' + meta.color + '">'
                + esc(initialsOf(actor))
                + '</span>'
                + '</div>'
                + '</div>'
                + '<div class="flex-grow-1">'
                + '<h6 class="mb-1 small">'
                + '<span class="fw-medium">' + esc(actor) + '</span>'
                + '<span class="fw-normal text-body-secondary"> · '
                + '<i class="icon-base ti tabler-' + meta.icon + ' icon-xs me-1"></i>'
                + esc(meta.label) + '</span>'
                + '</h6>'
                + '<small class="mb-1 d-block text-body">' + subtitle + '</small>'
                + '<small class="text-body-secondary">'
                + (recipient ? 'Para ' + esc(recipient) + ' · ' : '')
                + esc(timeAgo(n.created_at))
                + '</small>'
                + '</div>'
                + '<div class="flex-shrink-0 dropdown-notifications-actions">'
                + '<a href="javascript:void(0)" class="dropdown-notifications-read" title="Marcar como lida">'
                + '<span class="badge badge-dot"></span>'
                + '</a>'
                + '<a href="javascript:void(0)" class="dropdown-notifications-archive" title="Marcar como lida">'
                + '<span class="icon-base ti tabler-x"></span>'
                + '</a>'
                + '</div>'
                + '</div>'
                + '</li>';
        }

        function render(data) {
            var items = data.items || [];
            setUnread(data.unread || 0);

            if (!list) {
                return;
            }

            if (!items.length) {
                list.innerHTML = '<li class="list-group-item"><div class="p-3 text-body-secondary text-center">Nenhuma notificação</div></li>';
                return;
            }

            list.innerHTML = items.map(itemHtml).join('');
        }

        function load() {
            // Não redesenha enquanto o dropdown está aberto.
            if (root.querySelector('.dropdown-menu.show')) {
                return;
            }

            fetch('/api/notifications', {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () {});
        }

        function markRead(id) {
            return fetch('/notifications/' + encodeURIComponent(id) + '/read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'csrf_token=' + encodeURIComponent(csrf),
                cache: 'no-store'
            });
        }

        function markAllRead() {
            return fetch('/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'csrf_token=' + encodeURIComponent(csrf),
                cache: 'no-store'
            });
        }

        list.addEventListener('click', function (e) {
            var item = e.target.closest('.dropdown-notifications-item');
            if (!item) {
                return;
            }

            var id = item.getAttribute('data-id');
            var read = e.target.closest('.dropdown-notifications-read, .dropdown-notifications-archive');

            if (read) {
                e.preventDefault();
                e.stopPropagation();

                markRead(id).then(function () {
                    item.classList.add('marked-as-read');
                    var unread = list.querySelectorAll('.dropdown-notifications-item:not(.marked-as-read)').length;
                    setUnread(unread);
                }).catch(function () {});

                return;
            }

            var href = item.getAttribute('data-href');
            if (href) {
                // Marca como lida antes de navegar (não bloqueia se falhar).
                markRead(id).then(function () {
                    window.location.href = href;
                }).catch(function () {
                    window.location.href = href;
                });
            }
        });

        if (markAllBtn) {
            markAllBtn.addEventListener('click', function (e) {
                e.preventDefault();

                markAllRead().then(function () {
                    list.querySelectorAll('.dropdown-notifications-item').forEach(function (item) {
                        item.classList.add('marked-as-read');
                    });
                    setUnread(0);
                }).catch(function () {});
            });
        }

        load();
        setInterval(load, 30000);
    });
})();
