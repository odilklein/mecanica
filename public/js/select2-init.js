// =============================================
// SELECT2 — inicialização no padrão Vuexy
// (forms-selects: Bootstrap Select / Live Search).
//
// Marque qualquer <select> com class="select2" e ele
// vira um select com busca em tempo real.
// =============================================
(function () {
    'use strict';

    if (!window.jQuery) {
        return;
    }

    var PRIORITY_COLORS = {
        low: 'secondary',
        normal: 'info',
        high: 'warning',
        urgent: 'danger'
    };

    function priorityColor(item) {
        return PRIORITY_COLORS[item && item.id] || 'secondary';
    }

    function priorityResult(item) {
        if (item.loading) {
            return item.text;
        }
        if (!item.id) {
            return item.text;
        }
        return '<span class="badge bg-label-' + priorityColor(item) + '">' + item.text + '</span>';
    }

    function prioritySelection(item) {
        if (!item.id) {
            return item.text;
        }
        return '<span class="d-inline-flex align-items-center gap-2">'
            + '<span class="badge badge-dot bg-' + priorityColor(item) + '"></span>'
            + item.text + '</span>';
    }

    function initSelect2(selector) {
        var $ = window.jQuery;

        $(selector).each(function () {
            var $select = $(this);

            // Só inicializa quando visível (evita largura 0 em blocos ocultos).
            if ($select.closest('[hidden]').length) {
                return;
            }
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }
            if ($select.hasClass('part-autocomplete')) {
                return;
            }

            // Wrapper estável: sempre um bloco de largura total. Isso evita
            // que o Select2 redimensione o formulário ao abrir/fechar.
            var $wrapper = $select.closest('.select2-wrapper');
            if (!$wrapper.length) {
                $select.wrap('<div class="select2-wrapper"></div>');
                $wrapper = $select.parent();
            }

            // O dropdown é anexado ao body (ou ao modal/offcanvas) para não
            // ser recortado por cards/áreas com rolagem e não interferir no
            // layout (nem criar rolagem horizontal) do formulário.
            var $overlay = $select.closest('.modal, .offcanvas');
            var dropdownParent = $overlay.length ? $overlay : $('body');

            var isMultiple = $select.prop('multiple');

            var options = {
                width: $select.data('width') || '100%',
                dropdownParent: dropdownParent,
                placeholder: $select.data('placeholder') || 'Selecione...',
                allowClear: $select.data('allow-clear') === true && !isMultiple,
                closeOnSelect: !isMultiple,
                minimumResultsForSearch: $select.data('minimum-results-for-search') === 'Infinity'
                    ? Infinity
                    : 0,
                language: {
                    noResults: function () {
                        return 'Nenhum resultado encontrado';
                    },
                    searching: function () {
                        return 'Buscando...';
                    }
                }
            };

            if ($select.hasClass('select2-priority')) {
                options.templateResult = priorityResult;
                options.templateSelection = prioritySelection;
                options.escapeMarkup = function (markup) {
                    return markup;
                };
            }

            $select.select2(options);

            if ($select.hasClass('form-select-sm')) {
                $select.next('.select2-container').addClass('select2-sm');
            }
        });
    }

    // Exposto para selects que aparecem depois (ex.: veículos do cliente).
    window.initSelect2 = initSelect2;

    jQuery(function ($) {
        if (!$.fn.select2) {
            return;
        }
        initSelect2('select.select2');
    });
})();
