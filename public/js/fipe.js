// =============================================
// FIPE — cascata marca → modelo → ano com busca
// (TomSelect quando disponível) + valor automático.
// =============================================
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var brandEl = document.getElementById('fipe_brand_code');
        var modelEl = document.getElementById('fipe_model_code');
        var yearEl = document.getElementById('fipe_year_code');
        var valueDisplay = document.getElementById('fipe_value_display');
        var valueInput = document.getElementById('fipe_value');

        if (!brandEl || !modelEl || !yearEl) {
            return;
        }

        var tsBrand = null;
        var tsModel = null;
        var tsYear = null;

        if (window.TomSelect) {
            var base = { create: false, allowEmptyOption: true, maxOptions: 1000, sortField: { field: 'text', direction: 'asc' } };
            tsBrand = new TomSelect(brandEl, base);
            tsModel = new TomSelect(modelEl, base);
            tsYear = new TomSelect(yearEl, base);
        }

        function setOptions(el, ts, placeholder, items) {
            if (ts) {
                ts.clear(true);
                ts.clearOptions();
                ts.addOption({ value: '', text: placeholder });
                items.forEach(function (item) {
                    ts.addOption({ value: item.value, text: item.text });
                });
                ts.refreshOptions(false);
                return;
            }
            el.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = placeholder;
            el.appendChild(empty);
            items.forEach(function (item) {
                var opt = document.createElement('option');
                opt.value = item.value;
                opt.textContent = item.text;
                el.appendChild(opt);
            });
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

        function onBrandChange() {
            setOptions(modelEl, tsModel, 'Selecione...', []);
            setOptions(yearEl, tsYear, 'Selecione...', []);
            setValue(null);
            if (!brandEl.value) {
                return;
            }
            fetch('/api/fipe/models?brand=' + encodeURIComponent(brandEl.value))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var items = (data || []).map(function (m) {
                        return { value: m.code, text: m.name };
                    });
                    setOptions(modelEl, tsModel, 'Selecione...', items);
                })
                .catch(function () {});
        }

        function onModelChange() {
            setOptions(yearEl, tsYear, 'Selecione...', []);
            setValue(null);
            if (!brandEl.value || !modelEl.value) {
                return;
            }
            fetch('/api/fipe/years?brand=' + encodeURIComponent(brandEl.value) + '&model=' + encodeURIComponent(modelEl.value))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var items = (data || []).map(function (y) {
                        return { value: y.code, text: y.name };
                    });
                    setOptions(yearEl, tsYear, 'Selecione...', items);
                })
                .catch(function () {});
        }

        function onYearChange() {
            setValue(null);
            if (!brandEl.value || !modelEl.value || !yearEl.value) {
                return;
            }
            fetch('/api/fipe/price?brand=' + encodeURIComponent(brandEl.value) + '&model=' + encodeURIComponent(modelEl.value) + '&year=' + encodeURIComponent(yearEl.value))
                .then(function (r) { return r.json(); })
                .then(setValue)
                .catch(function () {});
        }

        if (tsBrand) {
            tsBrand.on('change', onBrandChange);
            tsModel.on('change', onModelChange);
            tsYear.on('change', onYearChange);
        } else {
            brandEl.addEventListener('change', onBrandChange);
            modelEl.addEventListener('change', onModelChange);
            yearEl.addEventListener('change', onYearChange);
        }
    });
})();
