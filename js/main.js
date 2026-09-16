// =============================================
// AUTO MECÂNICA - SCRIPTS PRINCIPAIS
// =============================================

document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    document.body.classList.remove('loading');
    document.body.style.opacity = '1';
    initTooltips();
    initSidebar();
    initTheme();
    initMobileSidebar();
    initAutoCollapse();
    initWizard();
    initDynamicLists();
    initSuggestionToggles();
    initSelectCards();
    initNotifications();
    initSidebarUserMenu();
    initSidebarTooltips();
    initResponsiveFilters();
    initTableFilters();
    initColorPicker();
});

// =============================================
// TOOLTIPS DO BOOTSTRAP
// =============================================
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// =============================================
// SIDEBAR - EXPANDIR / COLAPSAR (persiste em localStorage)
// =============================================
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const btn = document.getElementById('sidebarCollapseBtn');
    if (!sidebar || !btn) return;

    function aplicar(collapsed) {
        sidebar.classList.toggle('collapsed', collapsed);
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        btn.setAttribute('title', collapsed ? 'Expandir menu' : 'Minimizar menu');
    }

    const saved = localStorage.getItem('sidebar');
    if (saved === 'collapsed' && window.innerWidth > 768) {
        aplicar(true);
    }

    btn.addEventListener('click', function() {
        if (window.innerWidth <= 768) return;
        const collapsed = !sidebar.classList.contains('collapsed');
        aplicar(collapsed);
        localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'expanded');
    });
}

// =============================================
// THEME SWITCH - DARK / LIGHT (persiste em localStorage)
// =============================================
function initTheme() {
    const toggle = document.getElementById('themeToggle');
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (saved === 'dark' || (!saved && prefersDark)) {
        document.body.classList.add('dark');
    }

    if (toggle) {
        toggle.addEventListener('click', function() {
            document.body.classList.toggle('dark');
            const isDark = document.body.classList.contains('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeIcon(toggle, isDark);
        });
    }

    updateThemeIcon(toggle, document.body.classList.contains('dark'));
}

function updateThemeIcon(toggle, isDark) {
    if (!toggle) return;
    const icon = toggle.querySelector('[data-lucide]');
    if (!icon) return;
    icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// =============================================
// MOBILE - SIDEBAR DRAWER + OVERLAY + BURGER
// =============================================
function initMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const burger = document.getElementById('mobileMenuBtn');
    const collapseBtn = document.getElementById('sidebarCollapseBtn');

    if (!sidebar || !overlay || !burger) return;

    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    }

    function openMobileSidebar() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('active');
        document.body.classList.add('sidebar-open');
    }

    burger.addEventListener('click', function() {
        if (sidebar.classList.contains('mobile-open')) {
            closeMobileSidebar();
        } else {
            openMobileSidebar();
        }
    });

    // Fecha ao clicar em qualquer link de navegação do menu
    sidebar.querySelectorAll('.sidebar-nav-item').forEach(function(item) {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) closeMobileSidebar();
        });
    });

    // No mobile, o botão de minimizar fecha o menu lateral
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            if (window.innerWidth <= 768) closeMobileSidebar();
        });
    }

    overlay.addEventListener('click', closeMobileSidebar);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMobileSidebar();
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) closeMobileSidebar();
    });
}

// =============================================
// AUTO-COLLAPSE EM TELAS MENORES QUE 1280px
// =============================================
function initAutoCollapse() {
    const sidebar = document.getElementById('sidebar');
    const btn = document.getElementById('sidebarCollapseBtn');
    if (!sidebar) return;

    function sincronizar() {
        if (!btn) return;
        const collapsed = sidebar.classList.contains('collapsed');
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        btn.setAttribute('title', collapsed ? 'Expandir menu' : 'Minimizar menu');
    }

    function checkViewport() {
        if (window.innerWidth <= 768) return;

        if (window.innerWidth < 1280) {
            if (localStorage.getItem('sidebar') !== 'expanded') {
                sidebar.classList.add('collapsed');
            }
        } else if (sidebar.classList.contains('collapsed')) {
            if (localStorage.getItem('sidebar') !== 'collapsed') {
                sidebar.classList.remove('collapsed');
            }
        }
        sincronizar();
    }

    checkViewport();
    window.addEventListener('resize', checkViewport);
}

// =============================================
// FORMATAR CPF: 123.456.789-00
// =============================================
function formatarCPF(campo) {
    let valor = campo.value.replace(/\D/g, '');
    if (valor.length > 11) valor = valor.substring(0, 11);

    if (valor.length > 9) {
        valor = valor.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
    } else if (valor.length > 6) {
        valor = valor.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
    } else if (valor.length > 3) {
        valor = valor.replace(/(\d{3})(\d{1,3})/, '$1.$2');
    }
    campo.value = valor;
}

// =============================================
// FORMATAR TELEFONE: (11) 99999-9999
// =============================================
function formatarTelefone(campo) {
    let valor = campo.value.replace(/\D/g, '');
    if (valor.length > 11) valor = valor.substring(0, 11);

    if (valor.length > 10) {
        valor = valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (valor.length > 6) {
        valor = valor.replace(/(\d{2})(\d{4})(\d{1,4})/, '($1) $2-$3');
    } else if (valor.length > 2) {
        valor = valor.replace(/(\d{2})(\d{1,4})/, '($1) $2');
    }
    campo.value = valor;
}

// =============================================
// FORMATAR PLACA: ABC-1234
// =============================================
function formatarPlaca(campo) {
    let valor = campo.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (valor.length > 7) valor = valor.substring(0, 7);
    if (valor.length > 3) {
        valor = valor.substring(0, 3) + '-' + valor.substring(3);
    }
    campo.value = valor;
}

// =============================================
// FORMATAR CURRENCY: R$ 1.234,56
// =============================================
function formatarMoedaInput(campo) {
    let valor = campo.value.replace(/[^\d]/g, '');
    if (!valor) {
        campo.value = '';
        return;
    }
    valor = (parseInt(valor) / 100).toFixed(2);
    campo.value = 'R$ ' + valor.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Converter para número ao enviar formulário
function moedaParaNumero(campo) {
    let valor = campo.value.replace(/[^\d]/g, '');
    campo.value = valor ? (parseInt(valor) / 100) : 0;
    return true;
}

// =============================================
// CONFIRMAR EXCLUSÃO (uso nativo, sem libs)
// =============================================
function confirmarExclusao(formId, mensagem) {
    let texto = mensagem || 'Esta ação não pode ser desfeita!';
    if (confirm(texto)) {
        document.getElementById(formId).submit();
    }
    return false;
}

// =============================================
// FILTROS RESPONSIVOS (colapsáveis no mobile)
// Envolve o conteúdo de .filter-bar num painel e adiciona um botão
// que só aparece no mobile. Mostra um contador de filtros ativos.
// =============================================
function initResponsiveFilters() {
    document.querySelectorAll('.filter-bar').forEach(function (bar) {
        if (bar.dataset.filtroPronto === '1') return;
        bar.dataset.filtroPronto = '1';

        const body = document.createElement('div');
        body.className = 'filter-body';
        while (bar.firstChild) {
            body.appendChild(bar.firstChild);
        }

        const id = 'filtros' + Math.random().toString(36).slice(2, 8);
        body.id = id;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'filter-toggle';
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', id);
        btn.innerHTML =
            '<i data-lucide="sliders-horizontal"></i>' +
            '<span>Filtros</span>' +
            '<span class="filter-toggle-badge" hidden>0</span>' +
            '<span class="filter-toggle-chevron"><i data-lucide="chevron-down"></i></span>';

        bar.appendChild(btn);
        bar.appendChild(body);

        const badge = btn.querySelector('.filter-toggle-badge');

        function contarAtivos() {
            let n = 0;
            body.querySelectorAll('input, select').forEach(function (el) {
                if (el.type === 'hidden') return;
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.checked) n++;
                    return;
                }
                if ((el.value || '').trim() !== '') n++;
            });
            return n;
        }

        function atualizarBadge() {
            const n = contarAtivos();
            badge.textContent = String(n);
            badge.hidden = n === 0;
        }

        btn.addEventListener('click', function () {
            const aberto = bar.classList.toggle('aberto');
            btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });

        body.addEventListener('input', atualizarBadge);
        body.addEventListener('change', atualizarBadge);
        atualizarBadge();
    });

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}


function buscarTabela(buscaId, tabelaId) {
    const input = document.getElementById(buscaId);
    const tabela = document.getElementById(tabelaId);
    const linhas = tabela.querySelectorAll('tbody tr');

    input.addEventListener('keyup', function() {
        const termo = this.value.toLowerCase();
        linhas.forEach(function(linha) {
            const texto = linha.textContent.toLowerCase();
            linha.style.display = texto.indexOf(termo) > -1 ? '' : 'none';
        });
    });
}

// =============================================
// CARREGAR VEÍCULOS DE UM CLIENTE E PREENCHER SELECT
// =============================================
function carregarVeiculos(clienteId, selectId, servicoId) {
    if (!clienteId) {
        document.getElementById(selectId).innerHTML = '<option value="">Selecione um cliente primeiro</option>';
        return;
    }

    fetch((window.CAMINHO_BASE || '') + '/api/veiculos.php?cliente_id=' + encodeURIComponent(clienteId))
        .then(res => res.json())
        .then(data => {
            let options = '<option value="">-- Selecione o veículo --</option>';
            data.forEach(v => {
                options += `<option value="${v.id}">${escaparHtml(v.marca)} ${escaparHtml(v.modelo)} - ${escaparHtml(v.placa)}</option>`;
            });
            document.getElementById(selectId).innerHTML = options;

            if (servicoId) {
                document.getElementById(selectId).value = servicoId;
            }
        })
        .catch(err => console.error('Erro ao carregar veículos:', err));
}

// Escapa texto antes de inserir via innerHTML
function escaparHtml(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// =============================================
// WIZARD STEP-BY-STEP
// =============================================
function initWizard() {
    const wizard = document.querySelector('.wizard-progress');
    if (!wizard) return;

    const steps = wizard.querySelectorAll('.wizard-step');
    const panels = document.querySelectorAll('.wizard-panel');
    const prevBtn = document.getElementById('wizardPrev');
    const nextBtn = document.getElementById('wizardNext');
    const form = document.getElementById('formOS');
    let current = 0;

    function stepValido(index, quiet) {
        var panel = panels[index];
        if (!panel) return true;
        var invalidos = panel.querySelectorAll('[required]');
        for (var i = 0; i < invalidos.length; i++) {
            if (typeof invalidos[i].value !== 'string' || invalidos[i].value.trim() === '') {
                if (!quiet) {
                    invalidos[i].focus();
                    showWizardMsg('Preencha o campo obrigatório "' + (invalidos[i].name || 'requerido') + '" antes de continuar.');
                }
                return false;
            }
        }
        return true;
    }

    function showStep(index) {
        steps.forEach(function(step, i) {
            step.classList.remove('active', 'completed');
            if (i < index) step.classList.add('completed');
            if (i === index) step.classList.add('active');
        });
        panels.forEach(function(panel, i) {
            panel.classList.toggle('active', i === index);
        });
        current = index;
        if (prevBtn) prevBtn.style.visibility = index === 0 ? 'hidden' : 'visible';
        if (nextBtn) {
            if (index === steps.length - 1) {
                nextBtn.innerHTML = '<i data-lucide="check"></i> Criar OS';
                nextBtn.type = 'submit';
            } else {
                nextBtn.innerHTML = 'Próximo <i data-lucide="arrow-right"></i>';
                nextBtn.type = 'button';
            }
        }
        if (index === steps.length - 1) renderReview();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (prevBtn) prevBtn.addEventListener('click', function() {
        if (current > 0) showStep(current - 1);
    });

    if (nextBtn) nextBtn.addEventListener('click', function(e) {
        if (current < steps.length - 1) {
            e.preventDefault();
            if (stepValido(current, false)) showStep(current + 1);
        }
    });

    steps.forEach(function(step, i) {
        step.addEventListener('click', function() {
            if (i <= current || i === current + 1) showStep(i);
        });
    });

    document.querySelectorAll('.review-section').forEach(function(sec) {
        sec.addEventListener('click', function() {
            var s = parseInt(sec.dataset.editStep, 10);
            if (!isNaN(s) && s >= 0 && s < steps.length) showStep(s);
        });
    });

    if (form) form.addEventListener('submit', function(e) {
        if (current !== steps.length - 1) {
            e.preventDefault();
            showWizardMsg('Continue até a etapa de revisão para criar a OS.');
            return;
        }
        for (var i = 0; i < panels.length; i++) {
            if (!stepValido(i, true)) {
                e.preventDefault();
                showStep(i);
                showWizardMsg('Corrija os campos obrigatórios antes de criar a OS.');
                return;
            }
        }
        showWizardMsg('');
    });

    showStep(0);
}

// =============================================
// REVISÃO (RESUMO ANTES DE CRIAR)
// =============================================
function renderReview() {
    function cap(id) {
        var el = document.getElementById(id);
        return el ? (el.value || '').trim() : '';
    }
    var prefixo = 'Não informado';

    var nome = cap('nome');
    var telefone = cap('telefone');
    var cCliente = document.getElementById('reviewCliente');
    if (cCliente) {
        cCliente.innerHTML = (nome ? escapeHtml(nome) : prefixo) + (telefone ? ' · ' + escapeHtml(telefone) : '');
    }

    var marcaModelo = [cap('marca'), cap('modelo')].filter(Boolean).join(' ');
    var placa = cap('placa');
    var cVeiculo = document.getElementById('reviewVeiculo');
    if (cVeiculo) {
        cVeiculo.innerHTML = (marcaModelo ? escapeHtml(marcaModelo) : prefixo) + (placa ? ' · <b>' + escapeHtml(placa) + '</b>' : '');
    }

    var prioridade = cap('prioridade') || 'normal';
    var rotulos = { urgente: 'Urgente', alta: 'Alta', normal: 'Normal', baixa: 'Baixa' };
    var queixa = cap('queixa_inicial') || '—';
    var obs = cap('observacoes_internas');
    var cServico = document.getElementById('reviewServico');
    if (cServico) {
        var html = '<b>' + escapeHtml(rotulos[prioridade] || prioridade) + '</b> · ' + escapeHtml(queixa);
        if (obs) html += '<br><small>Obs.: ' + escapeHtml(obs) + '</small>';
        cServico.innerHTML = html;
    }

    var funcs = [];
    document.querySelectorAll('.select-card-employee').forEach(function(card) {
        var cb = card.querySelector('input[type="checkbox"]');
        if (cb && cb.checked) {
            var nm = card.querySelector('.emp-name');
            if (nm) funcs.push(nm.textContent.trim());
        }
    });

    var tarefas = [];
    document.querySelectorAll('[data-dynamic-list-tarefas] .dynamic-list-item .item-text').forEach(function(t) {
        tarefas.push(t.textContent.trim());
    });
    var pecas = [];
    document.querySelectorAll('[data-dynamic-list-pecas] .dynamic-list-item .item-text').forEach(function(t) {
        pecas.push(t.textContent.trim());
    });

    var cEquipe = document.getElementById('reviewEquipe');
    if (cEquipe) {
        var eh = funcs.length
            ? '<b>' + funcs.length + ' funcionário' + (funcs.length > 1 ? 's' : '') + ':</b> ' + escapeHtml(funcs.join(', '))
            : '<em>Nenhum funcionário selecionado</em>';
        eh += '<br><b>Tarefas:</b> ' + (tarefas.length ? escapeHtml(tarefas.slice(0, 4).join(' | ')) + (tarefas.length > 4 ? ' …' : '') : '—');
        eh += '<br><b>Peças:</b> ' + (pecas.length ? escapeHtml(pecas.slice(0, 4).join(' | ')) + (pecas.length > 4 ? ' …' : '') : '—');
        cEquipe.innerHTML = eh;
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// =============================================
// LISTAS DINÂMICAS (TAREFAS, PEÇAS) + SUGESTÕES + ÁUDIO
// =============================================
function initDynamicLists() {
    document.querySelectorAll('[data-dynamic-list]').forEach(function(list) {
        var input = list.querySelector('.dynamic-list-input .form-control');
        var addBtn = list.querySelector('.btn-add');
        var container = list.querySelector('.dynamic-list-items');
        var hiddenInput = list.querySelector('input[type="hidden"]');

        if (!addBtn || !container) return;

        var items = [];

        function addItem(text) {
            text = String(text || '').trim();
            if (!text) return;
            items.push(text);

            var item = document.createElement('div');
            item.className = 'dynamic-list-item';
            item.innerHTML = '<i data-lucide="grip-vertical" class="item-grip"></i>' +
                '<span class="item-text">' + escapeHtml(text) + '</span>' +
                '<button type="button" class="item-remove" title="Remover"><i data-lucide="x"></i></button>';
            item.querySelector('.item-remove').addEventListener('click', function() {
                var idx = Array.prototype.indexOf.call(container.children, item);
                if (idx > -1) items.splice(idx, 1);
                item.style.opacity = '0';
                item.style.transform = 'translateX(-10px)';
                setTimeout(function() { item.remove(); syncList(); }, 200);
            });
            container.appendChild(item);
            if (typeof lucide !== 'undefined') lucide.createIcons();
            syncList();
            if (input) { input.value = ''; input.focus(); }
        }

        function syncList() {
            if (!hiddenInput) return;
            hiddenInput.value = items.join('\n');
        }

        if (addBtn) addBtn.addEventListener('click', function() {
            addItem(input ? input.value : '');
        });

        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addItem(input.value);
            }
        });

        list.querySelectorAll('.suggestion-chip').forEach(function(chip) {
            chip.addEventListener('click', function() {
                addItem(chip.textContent.trim());
            });
        });

        // Load existing values
        if (hiddenInput && hiddenInput.value.trim()) {
            hiddenInput.value.trim().split('\n').forEach(function(line) {
                if (line.trim()) addItem(line.trim());
            });
        }
    });
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showWizardMsg(msg) {
    var el = document.getElementById('wizardMsg');
    if (!el) return;
    el.textContent = msg || '';
}

// =============================================
// SUGESTÕES RÁPIDAS (EXPANDIR/RECOLHER)
// =============================================
function initSuggestionToggles() {
    document.querySelectorAll('.suggestions-toggle').forEach(function(btn) {
        var chips = btn.closest('.suggestions').querySelector('.suggestion-chips');
        if (!chips) return;
        var icon = btn.querySelector('[data-lucide]');

        function setIcons(open) {
            if (icon) icon.setAttribute('data-lucide', open ? 'chevron-up' : 'more-horizontal');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        btn.addEventListener('click', function() {
            var open = chips.hidden;
            chips.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            setIcons(open);
        });

        setIcons(false);
    });
}

// =============================================
// SELECT CARDS (FUNCIONÁRIOS DESIGNADOS)
// =============================================
function initSelectCards() {
    document.querySelectorAll('.select-card-employee').forEach(function(card) {
        var checkbox = card.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        function sync() {
            var selected = checkbox.checked;
            card.classList.toggle('selected', selected);
            card.setAttribute('aria-pressed', selected ? 'true' : 'false');
        }

        function toggle() {
            checkbox.checked = !checkbox.checked;
            sync();
        }

        card.addEventListener('click', toggle);

        checkbox.addEventListener('change', sync);

        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });

        sync();
    });
}

// =============================================
// SISTEMA DE NOTIFICAÇÕES (SINO NO TOPBAR)
// =============================================
function initNotifications() {
    const bell = document.getElementById('notifBell');
    const panel = document.getElementById('notifPanel');
    const list = document.getElementById('notifList');
    const countEl = document.getElementById('notifCount');
    const dotEl = document.getElementById('notifDot');
    const markAll = document.getElementById('notifMarkAll');
    if (!bell || !panel || !list) return;

    const base = window.CAMINHO_BASE || '';
    const url = base + '/api/notificacoes.php';
    const icones = { os_criada: 'clipboard-plus', os_atribuida: 'user-plus', os_status: 'arrow-right-left', diagnostico: 'stethoscope', tarefa: 'list-checks', peca_pedido: 'package-search', peca_disponivel: 'package-check', orcamento: 'file-text', entrega: 'truck' };

    function iconeTipo(tipo) { return icones[tipo] || 'bell'; }

    function render(data) {
        const naoLidas = data.nao_lidas || 0;
        window.__notifCount = naoLidas;
        countEl.textContent = naoLidas > 99 ? '99+' : String(naoLidas);
        countEl.hidden = naoLidas === 0;
        dotEl.hidden = naoLidas === 0;

        list.innerHTML = '';
        if (!data.itens.length) {
            list.innerHTML = '<div class="notifications-empty"><i data-lucide="bell-off"></i><span>Nenhuma notificação</span></div>';
        } else {
            data.itens.forEach(function(n) {
                const item = document.createElement('a');
                item.className = 'notification-item' + (n.lida ? ' is-read' : '');
                item.setAttribute('data-id', n.id);
                const destino = n.os_id ? base + '/pages/ordens_servico/detalhes.php?id=' + n.os_id : '';
                if (destino) item.href = destino;
                else item.setAttribute('data-sem-link', '1');
                item.innerHTML =
                    '<span class="notification-icon"><i data-lucide="' + iconeTipo(n.tipo) + '"></i></span>' +
                    '<span class="notification-body">' +
                        '<span class="notification-msg">' + escapeHtml(n.mensagem) + '</span>' +
                        '<span class="notification-time">' + escapeHtml(n.tempo) + '</span>' +
                    '</span>' +
                    (n.lida ? '' : '<span class="notification-dot-sm"></span>');
                list.appendChild(item);
            });
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function carregar() {
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(render)
            .catch(function() {});
    }

    function marcarLida(id) {
        var fd = new FormData();
        fd.append('acao', 'marcar_lida');
        fd.append('id', id);
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' },
            body: fd
        });
    }

    bell.addEventListener('click', function(e) {
        e.stopPropagation();
        const abrir = panel.hidden;
        panel.hidden = !abrir;
        bell.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        if (abrir) carregar();
    });

    if (markAll) markAll.addEventListener('click', function(e) {
        e.stopPropagation();
        const fd = new FormData();
        fd.append('acao', 'marcar_todas_lidas');
        fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' }, body: fd })
            .then(function() { carregar(); });
    });

    list.addEventListener('click', function(e) {
        const item = e.target.closest('.notification-item');
        if (!item) return;
        const id = item.getAttribute('data-id');
        const destino = item.getAttribute('data-sem-link') ? '' : item.getAttribute('href');
        if (id) {
            marcarLida(id).then(function() {
                carregar();
                if (destino) window.location.href = destino;
            }).catch(function() {
                if (destino) window.location.href = destino;
            });
        }
    });

    document.addEventListener('click', function(e) {
        if (!panel.hidden && !e.target.closest('.topbar-notifications')) {
            panel.hidden = true;
            bell.setAttribute('aria-expanded', 'false');
        }
    });

    carregar();
    setInterval(carregar, 30000);
}

// =============================================
// MENU DO USUÁRIO (3 PONTINHOS NA SIDEBAR)
// =============================================
function initSidebarUserMenu() {
    const usuario = document.getElementById('sidebarUser');
    const menu = document.getElementById('sidebarUserMenu');
    if (!usuario || !menu) return;

    function alternar(abrir) {
        menu.hidden = !abrir;
        usuario.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    }

    usuario.addEventListener('click', function(e) {
        e.stopPropagation();
        alternar(menu.hidden);
    });

    document.addEventListener('click', function(e) {
        if (!menu.hidden && !e.target.closest('.sidebar-user-menu') && !e.target.closest('#sidebarUser')) {
            alternar(false);
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !menu.hidden) alternar(false);
    });
}

// =============================================
// TOOLTIP FLUTUANTE DOS ÍCONES (SIDEBAR RECOLHIDA)
// =============================================
function initSidebarTooltips() {
    const sidebar = document.getElementById('sidebar');
    const tooltip = document.getElementById('sidebarTooltip');
    if (!sidebar || !tooltip) return;

    function mostrar(el) {
        if (window.innerWidth <= 768 || !sidebar.classList.contains('collapsed')) return;
        const texto = el.getAttribute('data-tooltip');
        if (!texto) return;

        const rect = el.getBoundingClientRect();
        tooltip.textContent = texto;
        tooltip.hidden = false;
        tooltip.style.top = (rect.top + rect.height / 2) + 'px';
        tooltip.style.left = (rect.right + 10) + 'px';
    }

    function esconder() {
        tooltip.hidden = true;
    }

    sidebar.querySelectorAll('[data-tooltip]').forEach(function(el) {
        el.addEventListener('mouseenter', function() { mostrar(el); });
        el.addEventListener('focus', function() { mostrar(el); });
        el.addEventListener('mouseleave', esconder);
        el.addEventListener('blur', esconder);
    });

    sidebar.addEventListener('mouseleave', esconder);
    window.addEventListener('scroll', esconder, true);
}

// =============================================
// FILTROS E BUSCA AVANÇADA EM LISTAGENS (tabelas)
//
// Uso: numa página de listagem inclua:
//   .filtros-bar  (barra com inputs de busca/select)
//   input[data-filtro-busca="tabelaId"]      -> busca por texto em todas as células
//   select[data-filtro-coluna="Cidade"][data-filtro-tabela="tabelaId"] -> filtra coluna exata
//   select[data-filtro-substring="nome"][data-filtro-tabela="tabelaId"] -> filtra coluna por trecho
//   .filtros-info contém [data-filtro-total="tabelaId"]
//   .filtros-vazio é exibido quando a busca não retorna nada
// =============================================
function initTableFilters() {
    document.querySelectorAll('input[data-filtro-busca]').forEach(input => {
        const tabelaId = input.getAttribute('data-filtro-busca');
        const tabela = document.getElementById(tabelaId);
        if (!tabela) return;

        const selects = document.querySelectorAll(
            `[data-filtro-tabela="${tabelaId}"]`
        );
        const totalEl = document.querySelector(`[data-filtro-total="${tabelaId}"]`);
        const vazioEl = document.querySelector(`[data-filtro-vazio="${tabelaId}"]`);

        function temFiltroSelecao() {
            return Array.from(selects).some(sel => sel.value);
        }

        function aplicar() {
            const termo = input.value.trim().toLowerCase();
            let visiveis = 0;

            Array.from(tabela.querySelectorAll('tbody tr')).forEach(tr => {
                if (tr.classList.contains('row-filtro-vazio')) {
                    tr.style.display = (visiveis === 0 && (termo || temFiltroSelecao())) ? '' : 'none';
                    return;
                }

                if (tr.classList.contains('row-empty')) {
                    tr.style.display = (visiveis === 0 && !(termo || temFiltroSelecao())) ? '' : 'none';
                    return;
                }

                let mostrar = true;

                if (termo) {
                    mostrar = (tr.textContent || '').toLowerCase().indexOf(termo) > -1;
                }

                if (mostrar) {
                    selects.forEach(sel => {
                        if (!mostrar || !sel.value) return;

                        const dataRange = sel.getAttribute('data-filtro-data-range');
                        if (dataRange) {
                            mostrar = filtroDataRange(tr, sel.getAttribute('data-filtro-coluna'), sel.value, dataRange);
                        } else {
                            const substring = sel.hasAttribute('data-filtro-substring');
                            const td = encontrarCelula(tr, sel);
                            if (!td) return;
                            const comparar = (td.getAttribute('data-filtro-normalizado') || td.textContent || '').trim().toLowerCase();
                            mostrar = substring
                                ? comparar.indexOf(sel.value.toLowerCase()) > -1
                                : comparar === sel.value.toLowerCase();
                        }
                    });
                }

                if (mostrar) {
                    tr.style.display = '';
                    visiveis++;
                } else {
                    tr.style.display = 'none';
                }
            });

            if (totalEl) totalEl.textContent = String(visiveis);
            if (vazioEl) vazioEl.style.display = (visiveis === 0 && (termo || temFiltroSelecao())) ? '' : 'none';
        }

        input.addEventListener('input', aplicar);
        selects.forEach(sel => sel.addEventListener('change', aplicar));

        aplicar();
    });
}

function encontrarCelula(tr, sel) {
    const coluna = sel.getAttribute('data-filtro-coluna') || sel.getAttribute('data-filtro-substring');
    if (!coluna) return null;
    return tr.querySelector(`td[data-label="${coluna}"]`);
}

function filtroDataRange(tr, coluna, valor, tipo) {
    const td = tr.querySelector(`td[data-label="${coluna}"]`);
    if (!td) return false;
    const normalizado = td.getAttribute('data-filtro-normalizado') || '';
    if (!normalizado) return true;
    const hoje = new Date();
    hoje.setHours(0,0,0,0);
    const data = new Date(normalizado + 'T00:00:00');
    if (isNaN(data.getTime())) return true;
    if (valor === 'hoje') {
        return data.getTime() === hoje.getTime();
    } else if (valor === 'ultimos7') {
        const sete = new Date(hoje); sete.setDate(sete.getDate() - 7);
        return data >= sete;
    } else if (valor === 'ultimos30') {
        const trinta = new Date(hoje); trinta.setDate(trinta.getDate() - 30);
        return data >= trinta;
    } else if (valor === 'proximos7') {
        const fim = new Date(hoje); fim.setDate(fim.getDate() + 7);
        return data >= hoje && data <= fim;
    }
    return true;
}

// =============================================
// SELETOR DE COR DE VEÍCULO (paleta + picker)
// =============================================
function initColorPicker() {
    const corInput = document.getElementById('cor');
    const palette = document.getElementById('corPalette');
    if (!corInput || !palette) return;
    const picker = document.getElementById('corPicker');

    const swatches = Array.from(palette.querySelectorAll('.color-swatch[data-cor]'));

    function marcarAtivo(button) {
        swatches.forEach(function (b) { b.classList.toggle('active', b === button); });
        if (picker) picker.setAttribute('aria-pressed', button ? 'true' : 'false');
    }

    swatches.forEach(function (button) {
        button.addEventListener('click', function () {
            corInput.value = button.getAttribute('data-cor');
            if (picker) picker.value = button.getAttribute('data-hex');
            marcarAtivo(button);
        });
    });

    if (picker) {
        picker.addEventListener('input', function () {
            corInput.value = picker.value.toUpperCase();
            marcarAtivo(null);
        });
    }

    // Pré-seleciona a cor atual ao abrir o formulário (edição)
    const atual = (corInput.value || '').trim();
    if (atual) {
        const normalizado = atual.toLowerCase();
        const achou = swatches.find(function (b) {
            return b.getAttribute('data-cor').toLowerCase() === normalizado;
        });
        if (achou) {
            marcarAtivo(achou);
            if (picker) picker.value = achou.getAttribute('data-hex');
        } else if (picker && /^#([0-9a-f]{6}|[0-9a-f]{3})$/i.test(atual)) {
            picker.value = atual.toUpperCase();
        } else if (picker && cor_hex_para_picker(corInput)) {
            picker.value = cor_hex_para_picker(corInput);
        }
    }
}

// Converte um valor arbitrário para o formato aceito pelo <input type="color">
function cor_hex_para_picker(campo) {
    const valor = (campo.value || '').trim();
    const m = valor.match(/^#([0-9a-fA-F]{6})$/);
    if (!m) return null;
    return '#' + m[1].toLowerCase();
}

// Limpa a barra de filtros de uma listagem e reaplica
function limparFiltros(tabelaId) {
    document.querySelectorAll(`input[data-filtro-busca="${tabelaId}"]`).forEach(i => i.value = '');
    document.querySelectorAll(`[data-filtro-tabela="${tabelaId}"]`).forEach(s => s.value = '');
    document.querySelectorAll(`input[data-filtro-busca="${tabelaId}"]`).forEach(i => i.dispatchEvent(new Event('input')));
}