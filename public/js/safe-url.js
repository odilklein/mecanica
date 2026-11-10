// =============================================
// SAFE URL — só permite links http(s) no front-end.
// Evita javascript:/data: em URLs vindas de catálogo/API.
// =============================================
(function () {
    'use strict';

    window.safeUrl = function (value) {
        if (!value) {
            return '';
        }
        try {
            var url = new URL(String(value), window.location.origin);
            return (url.protocol === 'http:' || url.protocol === 'https:') ? url.href : '';
        } catch (e) {
            return '';
        }
    };
})();
