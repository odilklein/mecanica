// =============================================
// AUTOCOMPLETE DE PEÇAS — Select2 AJAX
// Sugere peças da base local (nome/marca/código)
// e mostra o preço de referência. Texto livre
// continua permitido (tags).
// =============================================
(function () {
    'use strict';

    function esc(value) {
        var d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function formatResult(item) {
        if (item.loading) {
            return item.text;
        }
        if (!item.name) {
            return esc(item.text);
        }

        var meta = [item.brand, item.part_code].filter(Boolean).join(' · ');
        var price = item.price_label
            ? '<span class="badge bg-label-success ms-2">' + esc(item.price_label) + '</span>'
            : (item.is_new ? '<span class="badge bg-label-secondary ms-2">nova</span>' : '');
        var link = item.product_url
            ? '<a href="' + esc(item.product_url) + '" target="_blank" rel="noopener"'
                + ' class="btn btn-icon btn-sm btn-text-primary ms-1" title="Abrir no Canal da Peça"'
                + ' aria-label="Abrir no Canal da Peça"'
                + ' onclick="event.stopPropagation()" onmousedown="event.stopPropagation()">'
                + '<i class="icon-base ti tabler-external-link icon-16px"></i></a>'
            : '';

        return '<div class="d-flex justify-content-between align-items-center gap-2">'
            + '<div class="text-truncate">'
            + '<span class="fw-medium">' + esc(item.name) + '</span>'
            + (meta ? '<br><small class="text-body-secondary">' + esc(meta) + '</small>' : '')
            + '</div><div class="d-flex align-items-center">' + price + link + '</div></div>';
    }

    function formatSelection(item) {
        return esc(item.name || item.text || '');
    }

    /**
     * @param {string} selector
     * @param {(item:object)=>void} onSelect
     * @param {{minimumInputLength?:number}} options
     */
    function init(selector, onSelect, options) {
        var $ = window.jQuery;
        if (!$ || !$.fn.select2) {
            return;
        }

        var $el = $(selector);
        if (!$el.length || $el.hasClass('select2-hidden-accessible')) {
            return;
        }

        var $overlay = $el.closest('.modal, .offcanvas');
        var $parent = $overlay.length ? $overlay : $('body');

        $el.select2({
            width: '100%',
            dropdownParent: $parent,
            placeholder: $el.data('placeholder') || 'Buscar peça por nome, marca ou código...',
            minimumInputLength: (options && options.minimumInputLength) || 2,
            allowClear: true,
            tags: false,
            ajax: {
                url: '/api/parts/autocomplete',
                dataType: 'json',
                delay: 300,
                cache: true,
                data: function (params) {
                    return { q: params.term, limit: 10 };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                }
            },
            templateResult: formatResult,
            templateSelection: formatSelection,
            escapeMarkup: function (markup) {
                return markup;
            },
            language: {
                inputTooShort: function () {
                    return 'Digite pelo menos 2 caracteres';
                },
                noResults: function () {
                    return 'Nenhuma peça encontrada';
                },
                searching: function () {
                    return 'Buscando...';
                }
            }
        });

        $el.on('select2:select', function (e) {
            var item = e.params.data;
            if (typeof onSelect === 'function') {
                onSelect(item);
            }
            // Limpa para a próxima busca/adicição.
            $el.val(null).trigger('change.select2');
        });
    }

    window.PartAutocomplete = { init: init };
})();
