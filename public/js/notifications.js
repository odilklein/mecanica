// =============================================
// SINO DE NOTIFICAÇÕES — polling + render no header.
// =============================================
(function () {
    'use strict';

    var icons = {
        os_status: 'arrow-right-left',
        os_created: 'clipboard-plus',
        task: 'list-check',
        part: 'package'
    };

    function esc(v) {
        var d = document.createElement('div');
        d.textContent = v == null ? '' : String(v);
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var badge = document.getElementById('notifBadge');
        var menu = document.getElementById('notifMenu');
        if (!badge || !menu) {
            return;
        }

        function render(data) {
            var unread = data.unread || 0;
            badge.textContent = String(unread);
            badge.hidden = unread === 0;

            var items = data.items || [];
            if (!items.length) {
                menu.innerHTML = '<li><div class="p-3 text-body-secondary text-center">Nenhuma notificação</div></li>';
                return;
            }

            menu.innerHTML = '';
            items.forEach(function (n) {
                var li = document.createElement('li');
                var unreadClass = n.read_at ? '' : ' bg-label-primary';
                var link = n.link
                    ? '<a href="' + esc(n.link) + '" class="small">Abrir</a>'
                    : '';
                li.innerHTML = '<div class="dropdown-item-text d-flex gap-2 p-3' + unreadClass + '">'
                    + '<span class="avatar avatar-sm"><span class="avatar-initial rounded-circle bg-label-primary"><i class="icon-base ti tabler-' + (icons[n.type] || 'bell') + '"></i></span></span>'
                    + '<div class="flex-grow-1"><span class="d-block small">' + esc(n.message) + '</span>'
                    + '<small class="text-body-secondary">' + esc(n.created_at) + '</small> ' + link + '</div></div>';
                menu.appendChild(li);
            });

            var all = document.createElement('li');
            all.innerHTML = '<a class="dropdown-item text-center border-top py-2" href="/notifications">Ver todas</a>';
            menu.appendChild(all);
        }

        function load() {
            fetch('/api/notifications', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () {});
        }

        load();
        setInterval(load, 30000);
    });
})();
