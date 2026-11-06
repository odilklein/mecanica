// =============================================
// FIPE — selects em cascata (marca → modelo → ano)
// e preenchimento do valor no form de veículo.
// =============================================
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var brand = document.getElementById('fipe_brand_code');
        var model = document.getElementById('fipe_model_code');
        var year = document.getElementById('fipe_year_code');
        var valueDisplay = document.getElementById('fipe_value_display');
        var valueInput = document.getElementById('fipe_value');

        if (!brand || !model || !year) {
            return;
        }

        function reset(select) {
            select.innerHTML = '<option value="">Selecione...</option>';
        }

        function setValue(data) {
            var raw = data && data.value != null ? data.value : '';
            if (valueInput) {
                valueInput.value = raw;
            }
            if (valueDisplay) {
                valueDisplay.value = raw !== ''
                    ? Number(raw).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '';
            }
        }

        function loadModels() {
            reset(model);
            reset(year);
            setValue(null);
            if (!brand.value) {
                return;
            }
            fetch('/api/fipe/models?brand=' + encodeURIComponent(brand.value))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    (data || []).forEach(function (m) {
                        var option = document.createElement('option');
                        option.value = m.code;
                        option.textContent = m.name;
                        model.appendChild(option);
                    });
                })
                .catch(function () {});
        }

        function loadYears() {
            reset(year);
            setValue(null);
            if (!brand.value || !model.value) {
                return;
            }
            fetch('/api/fipe/years?brand=' + encodeURIComponent(brand.value) + '&model=' + encodeURIComponent(model.value))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    (data || []).forEach(function (y) {
                        var option = document.createElement('option');
                        option.value = y.code;
                        option.textContent = y.name;
                        year.appendChild(option);
                    });
                })
                .catch(function () {});
        }

        function loadPrice() {
            setValue(null);
            if (!brand.value || !model.value || !year.value) {
                return;
            }
            fetch('/api/fipe/price?brand=' + encodeURIComponent(brand.value) + '&model=' + encodeURIComponent(model.value) + '&year=' + encodeURIComponent(year.value))
                .then(function (r) { return r.json(); })
                .then(setValue)
                .catch(function () {});
        }

        brand.addEventListener('change', loadModels);
        model.addEventListener('change', loadYears);
        year.addEventListener('change', loadPrice);
    });
})();
