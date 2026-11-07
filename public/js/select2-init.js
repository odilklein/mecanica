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

            var $parent = $select.closest('.position-relative, .input-group');

            if (!$parent.length) {
                $select.wrap('<div class="position-relative"></div>');
                $parent = $select.parent();
            }

            var isMultiple = $select.prop('multiple');

            $select.select2({
                width: $select.data('width') || '100%',
                dropdownParent: $parent,
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
            });
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
