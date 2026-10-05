// =============================================
// SHELL VUEXY — inicialização do layout, tema,
// menu vertical e notificações (sem jQuery).
// =============================================
(function () {
    'use strict';

    var base = window.CAMINHO_BASE || '';
    var apiNotif = base + '/api/notificacoes.php';

    // O motor do menu (menu.js) referencia a global `templateName`.
    window.templateName = 'vertical-menu-template';
    window.assetsPath = base + '/assets/';

    // ---------- Tema (data-bs-theme) ----------
    var STORAGE_KEY = 'mecanica-theme';

    function temaPreferido() {
        // O padrão do sistema é SEMPRE claro. Escuro/sistema só após
        // escolha explícita do usuário.
        var saved = localStorage.getItem(STORAGE_KEY);
        return (saved === 'dark' || saved === 'system') ? saved : 'light';
    }

    function resolverTema(pref) {
        if (pref === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return pref === 'dark' ? 'dark' : 'light';
    }

    function sincronizarMenuTema(pref) {
        document.querySelectorAll('[data-bs-theme-value]').forEach(function (btn) {
            var ativo = btn.getAttribute('data-bs-theme-value') === pref;
            btn.classList.toggle('active', ativo);
            btn.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        });
    }

    function aplicarTema(pref) {
        var theme = resolverTema(pref);
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.style.colorScheme = theme;
        var icon = document.getElementById('themeIcon');
        if (icon) {
            icon.setAttribute('data-icon', theme === 'dark' ? 'moon-stars' : 'sun');
            icon.className = 'icon-base ti ' + (theme === 'dark' ? 'tabler-moon-stars' : 'tabler-sun') + ' icon-22px theme-icon-active';
        }
        sincronizarMenuTema(pref);
    }

    function initTema() {
        aplicarTema(temaPreferido());
        document.querySelectorAll('[data-bs-theme-value]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pref = btn.getAttribute('data-bs-theme-value');
                localStorage.setItem(STORAGE_KEY, pref);
                aplicarTema(pref);
            });
        });
    }

    // ---------- Menu vertical ----------
    function initMenu() {
        var el = document.getElementById('layout-menu');
        if (el && window.Menu) {
            new window.Menu(el, { orientation: 'vertical', closeChildren: false });
        }
        document.querySelectorAll('.layout-menu-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                if (window.Helpers) {
                    window.Helpers.toggleCollapsed();
                }
            });
        });
    }

    // ---------- Tooltips ----------
    function initTooltips() {
        var list = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        list.forEach(function (el) {
            if (window.bootstrap) {
                new bootstrap.Tooltip(el);
            }
        });
    }

    // ---------- Notificações ----------
    var icones = {
        os_criada: 'clipboard-plus',
        os_atribuida: 'user-plus',
        os_status: 'arrow-right-left',
        diagnostico: 'stethoscope',
        tarefa: 'list-check',
        peca_pedido: 'package',
        peca_disponivel: 'package-check',
        orcamento: 'file-text',
        entrega: 'truck'
    };

    function iconeTipo(tipo) {
        return icones[tipo] || 'bell';
    }

    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = texto == null ? '' : String(texto);
        return div.innerHTML;
    }

    function renderNotificacoes(dados) {
        var countEl = document.getElementById('notifCount');
        var dotEl = document.getElementById('notifDot');
        var listEl = document.getElementById('notifList');

        var naoLidas = dados.nao_lidas || 0;
        if (countEl) {
            countEl.textContent = String(naoLidas);
            countEl.hidden = naoLidas === 0;
        }
        if (dotEl) {
            dotEl.hidden = naoLidas === 0;
        }
        if (!listEl) {
            return;
        }
        listEl.innerHTML = '';
        if (!dados.itens.length) {
            listEl.innerHTML = '<li class="list-group-item"><div class="text-center py-4 text-body-secondary"><i class="icon-base ti tabler-bell-off icon-md d-block mx-auto mb-2"></i>Nenhuma notificação</div></li>';
            return;
        }
        dados.itens.forEach(function (n) {
            var destino = n.os_id ? base + '/pages/ordens_servico/detalhes.php?id=' + n.os_id : '';
            var item = document.createElement('li');
            item.className = 'list-group-item list-group-item-action dropdown-notifications-item' + (n.lida ? ' marked-as-read' : '');
            item.setAttribute('data-id', n.id);
            item.setAttribute('data-destino', destino);
            item.innerHTML =
                '<div class="d-flex">' +
                '<div class="flex-shrink-0 me-3"><div class="avatar"><span class="avatar-initial rounded-circle bg-label-primary"><i class="icon-base ti tabler-' + iconeTipo(n.tipo) + '"></i></span></div></div>' +
                '<div class="flex-grow-1">' +
                '<h6 class="mb-1 small">' + escapar(n.mensagem) + '</h6>' +
                '<small class="text-body-secondary">' + escapar(n.tempo) + '</small>' +
                '</div>' +
                (n.lida ? '' : '<div class="flex-shrink-0"><span class="badge badge-dot"></span></div>') +
                '</div>';
            item.addEventListener('click', function () {
                marcarLida(n.id).then(function () {
                    carregarNotificacoes();
                    var dest = item.getAttribute('data-destino');
                    if (dest) {
                        window.location.href = dest;
                    }
                });
            });
            listEl.appendChild(item);
        });
    }

    function carregarNotificacoes() {
        fetch(apiNotif, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(renderNotificacoes)
            .catch(function () {});
    }

    function marcarLida(id) {
        var fd = new FormData();
        fd.append('acao', 'marcar_lida');
        fd.append('id', id);
        return fetch(apiNotif, {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' },
            body: fd
        });
    }

    function initNotificacoes() {
        var markAll = document.getElementById('notifMarkAll');
        if (markAll) {
            markAll.addEventListener('click', function (e) {
                e.stopPropagation();
                var fd = new FormData();
                fd.append('acao', 'marcar_todas_lidas');
                fetch(apiNotif, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' },
                    body: fd
                }).then(carregarNotificacoes);
            });
        }
        carregarNotificacoes();
        setInterval(carregarNotificacoes, 30000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTema();
        initMenu();
        initTooltips();
        initNotificacoes();
    });
})();
