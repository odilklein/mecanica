/**
 * Seletor de cor do veículo (Pickr, do tema).
 * As cores padrão gravam o NOME ("Azul", "Prata"); o campo continua
 * aceitando texto livre para cores fora da paleta.
 */
(function () {
    'use strict';

    var input = document.getElementById('color');
    var holder = document.getElementById('colorPicker');
    if (!input || !holder || !window.Pickr) {
        return;
    }

    var COLORS = [
        ['Branco', '#FFFFFF'],
        ['Preto', '#000000'],
        ['Prata', '#C0C0C0'],
        ['Cinza', '#808080'],
        ['Vermelho', '#DC3545'],
        ['Azul', '#0D6EFD'],
        ['Verde', '#198754'],
        ['Amarelo', '#FFC107'],
        ['Laranja', '#FD7E14'],
        ['Roxo', '#6F42C1'],
        ['Marrom', '#8B4513'],
        ['Bege', '#F5F5DC'],
        ['Vinho', '#800000'],
        ['Dourado', '#D4AF37']
    ];

    var hexToName = {};
    var nameToHex = {};

    COLORS.forEach(function (pair) {
        hexToName[pair[1].toUpperCase()] = pair[0];
        nameToHex[pair[0].toLowerCase()] = pair[1];
    });

    function hexOf(value) {
        var text = String(value || '').trim();
        if (nameToHex[text.toLowerCase()]) {
            return nameToHex[text.toLowerCase()];
        }
        if (/^#[0-9a-f]{6}$/i.test(text)) {
            return text.toUpperCase();
        }

        return null;
    }

    var pickr = window.Pickr.create({
        el: holder,
        theme: 'classic',
        default: hexOf(input.value) || '#C0C0C0',
        swatches: COLORS.map(function (pair) { return pair[1]; }),
        components: {
            preview: true,
            opacity: false,
            hue: true,
            interaction: { hex: true, input: true, save: true }
        }
    });

    function applyColor(color) {
        if (!color) {
            return;
        }
        var hex = color.toHEXA().toString().slice(0, 7).toUpperCase();
        input.value = hexToName[hex] || hex;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        pickr.hide();
    }

    // Clicar numa cor da paleta já grava o nome (sem precisar salvar).
    pickr.on('swatchselect', applyColor);
    pickr.on('save', applyColor);

    input.addEventListener('change', function () {
        var hex = hexOf(input.value);
        if (hex) {
            pickr.setColor(hex, true);
        }
    });
})();
