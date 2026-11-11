// =============================================
// FORMULÁRIO DE PEÇA — prévia da imagem e
// cálculo da margem em tempo real.
// =============================================
(function () {
    'use strict';

    function parseMoney(value) {
        var normalized = String(value || '').replace(/[^\d,.-]/g, '');
        if (normalized.indexOf(',') !== -1) {
            normalized = normalized.replace(/\./g, '').replace(',', '.');
        }
        var number = parseFloat(normalized);
        return isNaN(number) ? 0 : number;
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var imageInput = document.querySelector('[data-image-input]');
        var imagePreview = document.querySelector('[data-image-preview]');

        if (imageInput && imagePreview) {
            imageInput.addEventListener('input', function () {
                var url = imageInput.value.trim();
                imagePreview.innerHTML = '';

                if (!url) {
                    var empty = document.createElement('span');
                    empty.className = 'text-body-secondary small';
                    empty.textContent = 'Sem imagem';
                    imagePreview.appendChild(empty);
                    return;
                }

                var img = document.createElement('img');
                var safe = window.safeUrl ? window.safeUrl(url) : '';
                if (!safe) {
                    var invalid = document.createElement('span');
                    invalid.className = 'text-body-secondary small';
                    invalid.textContent = 'Informe uma URL http(s) válida';
                    imagePreview.appendChild(invalid);
                    return;
                }
                img.src = safe;
                img.alt = 'Prévia da peça';
                img.className = 'img-fluid rounded-2';
                img.onerror = function () {
                    imagePreview.innerHTML = '<span class="text-body-secondary small">Imagem não carregou</span>';
                };
                imagePreview.appendChild(img);
            });
        }

        var priceInput = document.querySelector('[data-price-input]');
        var costInput = document.querySelector('[data-cost-side]');
        var marginEl = document.querySelector('[data-margin]');

        function updateMargin() {
            if (!priceInput || !costInput || !marginEl) {
                return;
            }

            var price = parseMoney(priceInput.value);
            var cost = parseMoney(costInput.value);
            var margin = price > 0 ? ((price - cost) / price) * 100 : 0;

            marginEl.textContent = margin.toLocaleString('pt-BR', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }) + '%';

            marginEl.classList.remove('bg-label-primary', 'bg-label-success', 'bg-label-warning', 'bg-label-danger');
            if (margin < 0) {
                marginEl.classList.add('bg-label-danger');
            } else if (margin < 15) {
                marginEl.classList.add('bg-label-warning');
            } else {
                marginEl.classList.add('bg-label-success');
            }
        }

        function formatInput(input) {
            if (!input) {
                return;
            }
            var raw = input.value.trim();
            if (raw === '') {
                return;
            }
            input.value = formatMoney(parseMoney(raw));
            updateMargin();
        }

        if (priceInput) {
            if (priceInput.value.trim() !== '') {
                priceInput.value = formatMoney(parseMoney(priceInput.value));
            }
            priceInput.addEventListener('input', updateMargin);
            priceInput.addEventListener('blur', function () { formatInput(priceInput); });
        }
        if (costInput) {
            if (costInput.value.trim() !== '') {
                costInput.value = formatMoney(parseMoney(costInput.value));
            }
            costInput.addEventListener('input', updateMargin);
            costInput.addEventListener('blur', function () { formatInput(costInput); });
        }
        updateMargin();
    });
})();
