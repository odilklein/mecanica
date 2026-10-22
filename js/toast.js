// =============================================
// TOASTS (Bootstrap Toast) — mesmo padrão do template de referência
// Uso: mostrarToast('Mensagem', 'success' | 'danger' | 'warning' | 'info')
// =============================================
(function () {
    'use strict';

    var ICONES = {
        success: 'tabler-circle-check-filled',
        danger: 'tabler-alert-triangle-filled',
        warning: 'tabler-alert-triangle-filled',
        info: 'tabler-info-circle-filled'
    };

    var TITULOS = {
        success: 'Tudo certo',
        danger: 'Atenção',
        warning: 'Atenção',
        info: 'Informação'
    };

    var container = null;

    function garantirContainer() {
        if (container && document.body.contains(container)) {
            return container;
        }
        container = document.querySelector('.toast-container-global');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container-global';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        }
        return container;
    }

    function mostrarToast(mensagem, tipo, titulo) {
        if (!mensagem) { return; }
        tipo = ICONES[tipo] ? tipo : 'info';

        var toast = document.createElement('div');
        toast.className = 'bs-toast toast toast-ex fade';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        var cabecalho = document.createElement('div');
        cabecalho.className = 'toast-header';

        var icone = document.createElement('i');
        icone.className = 'icon-base ti ' + ICONES[tipo] + ' icon-md me-2';

        var textoTitulo = document.createElement('strong');
        textoTitulo.className = 'me-auto';
        textoTitulo.textContent = titulo || TITULOS[tipo];

        var fechar = document.createElement('button');
        fechar.type = 'button';
        fechar.className = 'btn-close';
        fechar.setAttribute('data-bs-dismiss', 'toast');
        fechar.setAttribute('aria-label', 'Fechar');

        cabecalho.appendChild(icone);
        cabecalho.appendChild(textoTitulo);
        cabecalho.appendChild(fechar);

        var corpo = document.createElement('div');
        corpo.className = 'toast-body';
        corpo.textContent = mensagem;

        toast.appendChild(cabecalho);
        toast.appendChild(corpo);
        garantirContainer().appendChild(toast);

        var instancia = new bootstrap.Toast(toast, { delay: 4000 });
        toast.addEventListener('hidden.bs.toast', function () {
            toast.remove();
        });
        instancia.show();
        return instancia;
    }

    window.mostrarToast = mostrarToast;
})();