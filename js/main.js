// =============================================
// AUTO MECÂNICA - SCRIPTS PRINCIPAIS
// =============================================

document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    initTooltips();
    initSidebar();
    initTheme();
    initMobileSidebar();
    initAutoCollapse();
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

    const saved = localStorage.getItem('sidebar');
    const wasCollapsed = saved === 'collapsed';

    if (wasCollapsed && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
    }

    btn.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebar', isCollapsed ? 'collapsed' : 'expanded');
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

    if (!sidebar || !overlay || !burger) return;

    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    }

    burger.addEventListener('click', function() {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
    });

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
    if (!sidebar) return;

    function checkViewport() {
        const isMobile = window.innerWidth <= 768;
        if (isMobile) return;

        if (window.innerWidth < 1280) {
            const saved = localStorage.getItem('sidebar');
            if (saved !== 'expanded') {
                sidebar.classList.add('collapsed');
            }
        } else if (sidebar.classList.contains('collapsed')) {
            const saved = localStorage.getItem('sidebar');
            if (saved !== 'collapsed') {
                sidebar.classList.remove('collapsed');
            }
        }
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
// BUSCA EM TEMPO REAL (tabelas)
// =============================================
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

    fetch('../api/veiculos.php?cliente_id=' + clienteId)
        .then(res => res.json())
        .then(data => {
            let options = '<option value="">-- Selecione o veículo --</option>';
            data.forEach(v => {
                options += `<option value="${v.id}">${v.marca} ${v.modelo} - ${v.placa}</option>`;
            });
            document.getElementById(selectId).innerHTML = options;

            if (servicoId) {
                document.getElementById(selectId).value = servicoId;
            }
        })
        .catch(err => console.error('Erro ao carregar veículos:', err));
}