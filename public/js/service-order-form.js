// =============================================
// EDIÇÃO DE OS — filtra os veículos pelo cliente.
// Os dados vêm de window.SO_VEHICLES (id, label, client_id).
// =============================================
(function () {
    'use strict';

    function boot() {
        var form = document.getElementById('formSO');
        if (!form) {
            return;
        }

        var $ = window.jQuery;
        var client = document.getElementById('client_id');
        var vehicle = document.getElementById('vehicle_id');
        var hint = document.getElementById('vehicleEmptyHint');
        if (!client || !vehicle) {
            return;
        }

        var vehicles = window.SO_VEHICLES || [];

        function rebuild() {
            var clientId = String(client.value || '');
            var selected = String(vehicle.value || '');
            var list = clientId === ''
                ? []
                : vehicles.filter(function (item) {
                    return String(item.client_id) === clientId;
                });

            vehicle.innerHTML = '';
            vehicle.appendChild(new Option('', '', false, false));
            list.forEach(function (item) {
                var option = new Option(item.label, item.id, false, false);
                option.selected = String(item.id) === selected;
                vehicle.appendChild(option);
            });

            if (hint) {
                hint.classList.toggle('d-none', !(clientId !== '' && list.length === 0));
            }

            if ($) {
                $(vehicle).trigger('change.select2');
            }
        }

        if ($) {
            $(client).on('change', rebuild);
        } else {
            client.addEventListener('change', rebuild);
        }

        rebuild();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
