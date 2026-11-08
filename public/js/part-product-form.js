// =============================================
// FORMULÁRIO DE PEÇA — prévia da imagem e
// cálculo da margem em tempo real.
// =============================================
(function () {
    'use strict';

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
                img.src = url;
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

            var price = parseFloat(priceInput.value || '0');
            var cost = parseFloat(costInput.value || '0');
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

        if (priceInput) priceInput.addEventListener('input', updateMargin);
        if (costInput) costInput.addEventListener('input', updateMargin);
        updateMargin();
    });
})();
