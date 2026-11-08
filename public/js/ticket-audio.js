/**
 * Botão de áudio dos tickets — grava, envia para /api/transcribe (Groq)
 * e preenche o campo de mensagem. A digitação manual continua funcionando.
 */
(function () {
    'use strict';

    var buttons = document.querySelectorAll('[data-ticket-audio]');
    if (!buttons.length) {
        return;
    }

    function toast(message, type) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
            return;
        }
        var container = document.getElementById('ticketAudioToasts');
        if (!container) {
            container = document.createElement('div');
            container.id = 'ticketAudioToasts';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1090';
            document.body.appendChild(container);
        }
        var el = document.createElement('div');
        el.className = 'toast align-items-center border-0 text-bg-' + (type === 'error' ? 'danger' : 'success');
        el.setAttribute('role', 'alert');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button></div>';
        el.querySelector('.toast-body').textContent = message;
        container.appendChild(el);
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        new bootstrap.Toast(el, { delay: 6000 }).show();
    }

    buttons.forEach(function (button) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
            button.classList.add('d-none');
            return;
        }

        var targetSelector = button.getAttribute('data-target') || '#ticketInput';
        var endpoint = button.getAttribute('data-endpoint') || '/api/transcribe';
        var csrf = button.getAttribute('data-csrf') || '';

        var recorder = null;
        var stream = null;
        var chunks = [];
        var busy = false;
        var maxTimer = null;
        var maxDurationMs = 120000;

        function clearMaxTimer() {
            if (maxTimer) {
                clearTimeout(maxTimer);
                maxTimer = null;
            }
        }

        function setRecording(on) {
            button.classList.toggle('is-recording', on);
            button.innerHTML = '<i class="icon-base ti ' + (on ? 'tabler-player-stop' : 'tabler-microphone') + '"></i>';
            button.title = on ? 'Parar e transcrever' : 'Gravar áudio e transcrever';
            button.setAttribute('aria-label', button.title);
        }

        function stopStream() {
            if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
            }
        }

        function transcriptError(code) {
            var messages = {
                disabled: 'Transcrição por áudio não está configurada.',
                transcription_failed: 'Não foi possível transcrever o áudio. Tente novamente.',
                empty_transcript: 'Nenhuma fala foi identificada no áudio.',
                audio_required: 'Áudio não recebido. Grave novamente.',
                too_large: 'Áudio muito grande. Grave um trecho mais curto.',
                csrf: 'Sessão expirada. Recarregue a página.'
            };
            return messages[code] || 'Falha ao transcrever o áudio.';
        }

        function transcribe(blob) {
            busy = true;
            button.disabled = true;
            button.classList.add('is-transcribing');

            var ext = blob.type.indexOf('mp4') !== -1 ? 'mp4' : (blob.type.indexOf('ogg') !== -1 ? 'ogg' : 'webm');
            var form = new FormData();
            form.append('csrf_token', csrf);
            form.append('audio', blob, 'gravacao.' + ext);

            fetch(endpoint, {
                method: 'POST',
                body: form,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data || !result.data.text) {
                        throw new Error((result.data && result.data.error) || 'transcription_failed');
                    }
                    var input = document.querySelector(targetSelector);
                    if (!input) {
                        throw new Error('target_missing');
                    }
                    var text = String(result.data.text).trim();
                    input.value = (input.value ? input.value.replace(/\s+$/, '') + ' ' : '') + text;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.focus();
                    toast('Áudio transcrito. Revise o texto antes de enviar.', 'success');
                })
                .catch(function (error) {
                    toast(transcriptError(String(error.message || error)), 'error');
                })
                .finally(function () {
                    busy = false;
                    button.disabled = false;
                    button.classList.remove('is-transcribing');
                });
        }

        button.addEventListener('click', function () {
            if (busy) {
                return;
            }

            if (recorder && recorder.state === 'recording') {
                recorder.stop();
                return;
            }

            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(function (mediaStream) {
                    stream = mediaStream;
                    chunks = [];

                    var mime = '';
                    ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'].some(function (type) {
                        if (MediaRecorder.isTypeSupported(type)) {
                            mime = type;
                            return true;
                        }
                        return false;
                    });

                    recorder = new MediaRecorder(stream, mime
                        ? { mimeType: mime, audioBitsPerSecond: 32000 }
                        : { audioBitsPerSecond: 32000 });

                    recorder.ondataavailable = function (event) {
                        if (event.data && event.data.size > 0) {
                            chunks.push(event.data);
                        }
                    };

                    recorder.onstop = function () {
                        clearMaxTimer();
                        var blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
                        stopStream();
                        setRecording(false);
                        recorder = null;
                        if (!blob.size) {
                            toast('Nada foi gravado. Tente novamente.', 'error');
                            return;
                        }
                        transcribe(blob);
                    };

                    recorder.start();
                    setRecording(true);
                    clearMaxTimer();
                    maxTimer = setTimeout(function () {
                        if (recorder && recorder.state === 'recording') {
                            toast('Limite de 2 minutos por gravação. O áudio será transcrito agora.', 'error');
                            recorder.stop();
                        }
                    }, maxDurationMs);
                })
                .catch(function () {
                    toast('Não foi possível acessar o microfone. Verifique a permissão do navegador.', 'error');
                });
        });
    });
})();
