// =============================================
// FIPE — cascata marca → modelo → ano
// (Select2 / Live Search) + valor automático.
//
// Requer jQuery + Select2 (já inicializados pelo
// /js/select2-init.js). Funciona no formulário de
// veículo e no wizard de OS.
// =============================================
(function () {
    'use strict';

    function boot() {
        var $ = window.jQuery;
        if (!$) {
            return;
        }

        var $brand = $('#fipe_brand_code');
        var $model = $('#fipe_model_code');
        var $year = $('#fipe_year_code');
        var $valueDisplay = $('#fipe_value_display');
        var $valueInput = $('#fipe_value');

        if (!$brand.length || !$model.length || !$year.length) {
            return;
        }

        // Substitui as opções preservando o Select2 quando presente.
        function setOptions($select, items) {
            $select.empty();
            $select.append(new Option('', '', false, false));
            (items || []).forEach(function (item) {
                $select.append(new Option(item.text, item.value, false, false));
            });
            $select.trigger('change.select2');
        }

        function setValue(data) {
            var raw = data && data.value != null ? data.value : '';
            if ($valueInput.length) {
                $valueInput.val(raw);
            }
            if ($valueDisplay.length) {
                $valueDisplay.val(raw !== ''
                    ? Number(raw).toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })
                    : '');
            }
        }

        $brand.on('change', function () {
            var brand = $brand.val();
            setOptions($model, []);
            setOptions($year, []);
            setValue(null);

            if (!brand) {
                return;
            }

            $.ajax({
                url: '/api/fipe/models?brand=' + encodeURIComponent(brand),
                dataType: 'json',
                cache: false
            }).done(function (data) {
                setOptions($model, (data || []).map(function (m) {
                    return { value: m.code, text: m.name };
                }));
            });
        });

        $model.on('change', function () {
            var brand = $brand.val();
            var model = $model.val();
            setOptions($year, []);
            setValue(null);

            if (!brand || !model) {
                return;
            }

            $.ajax({
                url: '/api/fipe/years?brand=' + encodeURIComponent(brand) + '&model=' + encodeURIComponent(model),
                dataType: 'json',
                cache: false
            }).done(function (data) {
                setOptions($year, (data || []).map(function (y) {
                    return { value: y.code, text: y.name };
                }));
            });
        });

        $year.on('change', function () {
            var brand = $brand.val();
            var model = $model.val();
            var year = $year.val();
            setValue(null);

            if (!brand || !model || !year) {
                return;
            }

            $.ajax({
                url: '/api/fipe/price?brand=' + encodeURIComponent(brand)
                    + '&model=' + encodeURIComponent(model)
                    + '&year=' + encodeURIComponent(year),
                dataType: 'json',
                cache: false
            }).done(setValue);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
