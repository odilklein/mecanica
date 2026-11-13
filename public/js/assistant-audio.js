/**
 * Áudio do assistente — gravação com visualizador em tempo real.
 *
 * Fluxo:
 *  - microfone ao lado do enviar;
 *  - durante a gravação o compositor mostra as ondas e o tempo;
 *  - "parar" transcreve e coloca o texto no campo para revisão;
 *  - "enviar" durante a gravação transcreve e envia direto;
 *  - "cancelar" descarta.
 *
 * Usa a Web Audio API (AnalyserNode) para o visualizador — sem dependência
 * externa. A transcrição reaproveita /api/transcribe (Groq Whisper).
 */
(function () {
    'use strict';

    var form = document.getElementById('assistantForm');
    var input = document.getElementById('assistantInput');
    var micBtn = document.getElementById('assistantAudio');
    var recorderBox = document.getElementById('assistantRecorder');
    var canvas = document.getElementById('assistantWave');
    var timeEl = document.getElementById('assistantRecTime');
    var stopBtn = document.getElementById('assistantRecStop');
    var cancelBtn = document.getElementById('assistantRecCancel');

    if (!form || !input || !micBtn || !recorderBox || !canvas) {
        return;
    }

    var cfg = {
        endpoint: form.getAttribute('data-audio-url') || '/api/transcribe',
        csrf: form.getAttribute('data-audio-csrf') || ''
    };

    var maxDurationMs = 120000;
    var state = 'idle';
    var mode = 'edit';
    var recorder = null;
    var stream = null;
    var audioCtx = null;
    var analyser = null;
    var sourceNode = null;
    var chunks = [];
    var raf = null;
    var timer = null;
    var startedAt = 0;
    var levels = [];
    var data = null;

    if (typeof MediaRecorder === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        micBtn.hidden = true;
        return;
    }

    function toast(message, tone) {
        var box = document.querySelector('.assistant-toast');
        if (!box) {
            box = document.createElement('div');
            box.className = 'assistant-toast';
            document.body.appendChild(box);
        }
        box.className = 'assistant-toast is-' + (tone || 'info');
        box.textContent = message;
        box.classList.add('is-visible');
        clearTimeout(box._timer);
        box._timer = setTimeout(function () {
            box.classList.remove('is-visible');
        }, 4200);
    }

    function pickMime() {
        var options = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/mp4',
            'audio/ogg;codecs=opus'
        ];
        for (var i = 0; i < options.length; i++) {
            if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(options[i])) {
                return options[i];
            }
        }
        return '';
    }

    function extensionFor(type) {
        if (type.indexOf('mp4') !== -1) {
            return 'mp4';
        }
        if (type.indexOf('ogg') !== -1) {
            return 'ogg';
        }
        return 'webm';
    }

    function formatTime(ms) {
        var total = Math.floor(ms / 1000);
        return Math.floor(total / 60) + ':' + String(total % 60).padStart(2, '0');
    }

    function setUi(recording) {
        recorderBox.hidden = !recording;
        input.hidden = recording;
        micBtn.hidden = recording;
        stopBtn.hidden = !recording;
        cancelBtn.hidden = !recording;

        var toolbar = document.querySelector('.assistant-toolbar');
        if (toolbar) {
            toolbar.hidden = recording;
        }
        form.classList.toggle('is-recording', recording);
    }

    function draw() {
        if (state !== 'recording') {
            return;
        }

        raf = requestAnimationFrame(draw);

        if (!analyser || !data) {
            return;
        }

        analyser.getByteFrequencyData(data);

        var dpr = window.devicePixelRatio || 1;
        var width = canvas.clientWidth;
        var height = canvas.clientHeight;
        if (!width || !height) {
            return;
        }

        if (canvas.width !== Math.round(width * dpr) || canvas.height !== Math.round(height * dpr)) {
            canvas.width = Math.round(width * dpr);
            canvas.height = Math.round(height * dpr);
        }

        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);

        var bars = Math.max(20, Math.min(56, Math.floor(width / 6)));
        var gap = 2;
        var barWidth = (width - gap * (bars - 1)) / bars;
        if (levels.length !== bars) {
            levels = new Array(bars).fill(0.05);
        }

        var color = getComputedStyle(canvas).color || '#00aba0';
        ctx.fillStyle = color;

        for (var i = 0; i < bars; i++) {
            var bin = Math.floor(Math.pow(i / bars, 1.35) * data.length * 0.62);
            var value = (data[bin] || 0) / 255;

            // suaviza a queda para o movimento parecer natural
            levels[i] = Math.max(value, levels[i] * 0.86);

            var barHeight = Math.max(2, levels[i] * (height - 8));
            var x = i * (barWidth + gap);
            var y = (height - barHeight) / 2;

            ctx.beginPath();
            if (ctx.roundRect) {
                ctx.roundRect(x, y, Math.max(1.5, barWidth), barHeight, Math.max(1, barWidth / 2));
            } else {
                ctx.rect(x, y, Math.max(1.5, barWidth), barHeight);
            }
            ctx.fill();
        }
    }

    function startTimer() {
        startedAt = Date.now();
        timeEl.textContent = '0:00';

        timer = setInterval(function () {
            var elapsed = Date.now() - startedAt;
            timeEl.textContent = formatTime(elapsed);

            if (elapsed >= maxDurationMs) {
                finish('edit');
            }
        }, 200);
    }

    function stopRecorder() {
        if (recorder && recorder.state !== 'inactive') {
            try {
                recorder.stop();
            } catch (error) {
                // ignora: o gravador pode já ter parado
            }
        }
    }

    function releaseStream() {
        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }
        if (audioCtx) {
            try {
                audioCtx.close();
            } catch (error) {
                // ignora
            }
            audioCtx = null;
        }
        analyser = null;
        sourceNode = null;
    }

    function cleanup() {
        if (raf) {
            cancelAnimationFrame(raf);
            raf = null;
        }
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        releaseStream();
        recorder = null;
        chunks = [];
        levels = [];
        state = 'idle';
        setUi(false);
        input.focus();
    }

    function reset() {
        cleanup();
    }

    function errorMessage(code) {
        return {
            disabled: 'Transcrição por áudio não está configurada.',
            transcription_failed: 'Não foi possível transcrever o áudio. Tente novamente.',
            empty_transcript: 'Nenhuma fala foi identificada.',
            audio_required: 'Áudio não recebido. Grave novamente.',
            too_large: 'Áudio muito grande. Grave um trecho mais curto.',
            csrf: 'Sessão expirada. Recarregue a página.'
        }[code] || 'Falha ao transcrever o áudio.';
    }

    function applyText(text, targetMode) {
        var clean = String(text || '').trim();
        if (clean === '') {
            throw new Error('Nenhuma fala foi identificada.');
        }

        input.value = (input.value ? input.value.replace(/\s+$/, '') + ' ' : '') + clean;
        input.dispatchEvent(new Event('input', { bubbles: true }));

        cleanup();

        if (targetMode === 'send') {
            form.requestSubmit();
            return;
        }

        input.focus();
        toast('Áudio transcrito. Revise o texto antes de enviar.', 'success');
    }

    function upload(blob, targetMode) {
        var formData = new FormData();
        formData.append('csrf_token', cfg.csrf);
        formData.append('audio', blob, 'gravacao.' + extensionFor(blob.type || ''));

        fetch(cfg.endpoint, {
            method: 'POST',
            body: formData,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (body) {
                return { ok: response.ok, body: body };
            });
        }).then(function (result) {
            if (!result.ok || !result.body || !result.body.text) {
                throw new Error(errorMessage((result.body && result.body.error) || 'transcription_failed'));
            }
            applyText(result.body.text, targetMode);
        }).catch(function (error) {
            cleanup();
            toast(error.message || 'Falha ao transcrever o áudio.', 'error');
        });
    }

    function finish(targetMode) {
        if (state !== 'recording') {
            return;
        }

        mode = targetMode;
        state = 'transcribing';
        timeEl.textContent = '…';

        stopRecorder();
    }

    function start() {
        if (state !== 'idle') {
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (mediaStream) {
            stream = mediaStream;
            chunks = [];
            state = 'recording';

            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                audioCtx = new AudioCtx();
                analyser = audioCtx.createAnalyser();
                analyser.fftSize = 512;
                analyser.smoothingTimeConstant = 0.75;
                data = new Uint8Array(analyser.frequencyBinCount);
                sourceNode = audioCtx.createMediaStreamSource(stream);
                sourceNode.connect(analyser);
            }

            var mime = pickMime();
            recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);

            recorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) {
                    chunks.push(event.data);
                }
            };

            recorder.onstop = function () {
                var blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
                var targetMode = mode;

                releaseStream();

                if (targetMode === 'cancel' || !blob.size) {
                    cleanup();
                    return;
                }

                upload(blob, targetMode);
            };

            recorder.start(250);
            setUi(true);
            startTimer();
            draw();
        }).catch(function (error) {
            state = 'idle';
            var name = error && error.name ? error.name : '';
            if (name === 'NotAllowedError' || name === 'SecurityError') {
                toast('Permissão do microfone negada. Libere o acesso no navegador.', 'error');
            } else if (name === 'NotFoundError') {
                toast('Nenhum microfone encontrado.', 'error');
            } else {
                toast('Não foi possível iniciar a gravação.', 'error');
            }
        });
    }

    // Enviar durante a gravação: transcreve e envia sem parar manualmente.
    form.addEventListener('submit', function (event) {
        if (state === 'recording') {
            event.preventDefault();
            event.stopImmediatePropagation();
            finish('send');
        }
    }, true);

    // Enter/Esc enquanto grava.
    document.addEventListener('keydown', function (event) {
        if (state !== 'recording') {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            finish('cancel');
        } else if (event.key === 'Enter') {
            event.preventDefault();
            event.stopImmediatePropagation();
            finish('send');
        }
    }, true);

    micBtn.addEventListener('click', start);
    stopBtn.addEventListener('click', function () {
        finish('edit');
    });
    cancelBtn.addEventListener('click', function () {
        finish('cancel');
    });

    window.addEventListener('resize', function () {
        if (state === 'recording') {
            levels = [];
        }
    });
})();
