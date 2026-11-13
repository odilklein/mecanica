/**
 * Preenchimento de formulário com IA.
 *
 * Fluxo:
 *  - botão "Preencher com IA" no cabeçalho do formulário;
 *  - modal com texto + microfone (transcrição via /api/transcribe);
 *  - POST /assistant/prefill devolve os campos extraídos;
 *  - o JS aplica os valores, respeitando o que já está preenchido;
 *  - campos derivados NÃO vêm da IA:
 *      CEP  → dispara o ViaCEP (rua, bairro, cidade, UF)
 *      FIPE → resolve marca → modelo → ano e o valor
 */
(function () {
    'use strict';

    var buttons = document.querySelectorAll('[data-ai-prefill]');
    if (!buttons.length) {
        return;
    }

    var modalEl = null;
    var modal = null;
    var textarea = null;
    var overwriteEl = null;
    var errorEl = null;
    var submitBtn = null;
    var spinner = null;
    var micBtn = null;
    var micLabel = null;
    var activeButton = null;
    var busy = false;

    var recorder = null;
    var micStream = null;
    var micChunks = [];
    var micTimer = null;
    var micStartedAt = 0;
    var micState = 'idle';

    /* ============================ UTIL ============================ */

    function norm(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function csrf() {
        var el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }

    function toast(message, tone) {
        var box = document.querySelector('.ai-toast');
        if (!box) {
            box = document.createElement('div');
            box.className = 'ai-toast';
            document.body.appendChild(box);
        }
        box.className = 'ai-toast is-' + (tone || 'info');
        box.textContent = message;
        box.classList.add('is-visible');
        clearTimeout(box._timer);
        box._timer = setTimeout(function () {
            box.classList.remove('is-visible');
        }, 5000);
    }

    function highlight(el) {
        if (!el) return;
        el.classList.add('ai-filled');
        setTimeout(function () {
            el.classList.remove('ai-filled');
        }, 2600);
    }

    function selectOption(select, value) {
        if (!select) return;
        if (window.jQuery) {
            window.jQuery(select).val(String(value)).trigger('change');
        } else {
            select.value = String(value);
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function selectByValue(select, value) {
        if (!select) return false;
        var target = String(value);
        for (var i = 0; i < select.options.length; i++) {
            if (String(select.options[i].value) === target) {
                selectOption(select, select.options[i].value);
                highlight(select);
                return true;
            }
        }
        return false;
    }

    function selectByText(select, value) {
        if (!select) return false;
        var target = norm(value);
        if (!target) return false;

        var starts = null;
        var contains = null;
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            if (!opt.value) continue;
            var text = norm(opt.text);
            if (!text) continue;
            if (text === target || norm(opt.value) === target) {
                selectOption(select, opt.value);
                highlight(select);
                return true;
            }
            if (!starts && text.indexOf(target) === 0) starts = opt;
            if (!contains && text.indexOf(target) !== -1) contains = opt;
        }

        var fallback = starts || contains;
        if (fallback) {
            selectOption(select, fallback.value);
            highlight(select);
            return true;
        }

        return false;
    }

    function findOption(select, text) {
        if (!select) return null;
        var target = norm(text);
        if (!target) return null;

        var starts = null;
        var contains = null;
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            if (!opt.value) continue;
            var label = norm(opt.text);
            if (!label) continue;
            if (label === target) return opt;
            if (!starts && label.indexOf(target) === 0) starts = opt;
            if (!contains && label.indexOf(target) !== -1) contains = opt;
        }

        return starts || contains;
    }

    function findOptions(select, text, limit) {
        if (!select) return [];
        var target = norm(text);
        if (!target) return [];

        var exact = [];
        var starts = [];
        var contains = [];

        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            if (!opt.value) continue;
            var label = norm(opt.text);
            if (!label) continue;
            if (label === target) {
                exact.push(opt);
            } else if (label.indexOf(target) === 0) {
                starts.push(opt);
            } else if (label.indexOf(target) !== -1) {
                contains.push(opt);
            }
        }

        return exact.concat(starts, contains).slice(0, limit || 5);
    }

    function waitOptions(select, timeout) {
        return new Promise(function (resolve) {
            if (!select) {
                resolve(false);
                return;
            }
            if (select.options.length > 1) {
                resolve(true);
                return;
            }

            var started = Date.now();
            (function check() {
                if (select.options.length > 1) {
                    resolve(true);
                    return;
                }
                if (Date.now() - started > (timeout || 7000)) {
                    resolve(false);
                    return;
                }
                setTimeout(check, 150);
            })();
        });
    }

    function setField(key, value, overwrite) {
        var el = document.getElementById(key);
        if (!el) {
            el = document.querySelector('[name="' + key + '"]');
        }
        if (!el || el.type === 'hidden' || el.type === 'file') {
            return false;
        }

        if (el.tagName === 'SELECT') {
            return selectByText(el, value);
        }

        if (!overwrite && String(el.value || '').trim() !== '') {
            return false;
        }

        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        highlight(el);
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });

        return true;
    }

    function triggerZip() {
        var zip = document.getElementById('zip');
        if (!zip) return;
        zip.dispatchEvent(new Event('input', { bubbles: true }));
        // o ViaCEP escuta "blur" neste campo
        zip.dispatchEvent(new Event('blur'));
    }

    function currentValues() {
        var values = {};
        var form = (activeButton && activeButton.closest('form')) || document.querySelector('form[method="POST"]');
        if (!form) return values;

        form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
            if (['csrf_token', 'password', 'password_confirmation'].indexOf(el.name) !== -1) return;
            if (['hidden', 'file', 'checkbox', 'radio'].indexOf(el.type) !== -1) return;
            var value = String(el.value || '').trim();
            if (value !== '') {
                values[el.name] = value.substring(0, 200);
            }
        });

        return values;
    }

    /* ============================ APLICAÇÃO ============================ */

    async function resolveFipe(fields) {
        var brand = document.getElementById('fipe_brand_code');
        if (!brand || !fields.brand) {
            return 0;
        }

        var brandOpt = findOption(brand, fields.brand);
        if (!brandOpt) {
            return 0;
        }

        selectOption(brand, brandOpt.value);
        highlight(brand);
        var count = 1;

        var model = document.getElementById('fipe_model_code');
        if (fields.model && await waitOptions(model)) {
            var candidates = findOptions(model, fields.model, 6);
            var year = document.getElementById('fipe_year_code');

            for (var i = 0; i < candidates.length; i++) {
                selectOption(model, candidates[i].value);
                highlight(model);
                count = Math.max(count, 2);

                if (!fields.year) {
                    break;
                }

                if (await waitOptions(year, 5000)) {
                    var yearOpt = findOption(year, String(fields.year));
                    if (yearOpt) {
                        selectOption(year, yearOpt.value);
                        highlight(year);
                        return count + 1;
                    }
                }
            }
        }

        return count;
    }

    async function apply(prefill, overwrite) {
        var fields = prefill.fields || {};
        var matches = prefill.matches || {};
        var count = 0;
        var skip = {};

        var clientSelect = document.getElementById('client_id');
        if (matches.client_id && clientSelect) {
            if (selectByValue(clientSelect, matches.client_id)) {
                count++;
                skip.client_name = true;
                skip.client_phone = true;
                skip.client_email = true;
                skip.cpf = true;
            }
        }

        var vehicleSelect = document.getElementById('vehicle_id');
        if (matches.vehicle_id && vehicleSelect) {
            // o wizard repopula as opções ao trocar o cliente
            await new Promise(function (resolve) { setTimeout(resolve, 120); });
            await waitOptions(vehicleSelect, 1500);
            if (selectByValue(vehicleSelect, matches.vehicle_id)) {
                count++;
                skip.plate = true;
                skip.brand = true;
                skip.model = true;
                skip.year = true;
                skip.color = true;
            }
        }

        if (fields.zip && !skip.zip) {
            if (setField('zip', fields.zip, overwrite)) {
                count++;
                triggerZip();
            }
        }

        if (!matches.vehicle_id && fields.brand && document.getElementById('fipe_brand_code')) {
            count += await resolveFipe(fields);
            skip.brand = true;
            skip.model = true;
            skip.year = true;
        }

        Object.keys(fields).forEach(function (key) {
            if (skip[key]) return;
            if (key === 'zip') return;
            if (setField(key, fields[key], overwrite)) {
                count++;
            }
        });

        return count;
    }

    /* ============================ ÁUDIO ============================ */

    function setMicState(state) {
        micState = state;
        if (state === 'recording') {
            micBtn.classList.add('is-recording');
            micLabel.textContent = '0:00';
        } else {
            micBtn.classList.remove('is-recording');
            micBtn.disabled = state === 'busy';
            micLabel.textContent = state === 'busy' ? 'Transcrevendo…' : 'Gravar';
        }
    }

    function stopStream() {
        if (micStream) {
            micStream.getTracks().forEach(function (track) { track.stop(); });
            micStream = null;
        }
    }

    function startMic() {
        if (micState !== 'idle') {
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
            toast('Seu navegador não suporta gravação de áudio.', 'error');
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            micStream = stream;
            micChunks = [];
            recorder = new MediaRecorder(stream);

            recorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) {
                    micChunks.push(event.data);
                }
            };

            recorder.onstop = function () {
                stopStream();
                var blob = new Blob(micChunks, { type: recorder.mimeType || 'audio/webm' });
                if (!blob.size) {
                    setMicState('idle');
                    return;
                }
                uploadAudio(blob);
            };

            recorder.start(250);
            micStartedAt = Date.now();
            setMicState('recording');

            micTimer = setInterval(function () {
                var elapsed = Math.floor((Date.now() - micStartedAt) / 1000);
                micLabel.textContent = Math.floor(elapsed / 60) + ':' + String(elapsed % 60).padStart(2, '0');
                if (elapsed >= 120) {
                    stopMic();
                }
            }, 250);
        }).catch(function (error) {
            var name = error && error.name ? error.name : '';
            toast(name === 'NotAllowedError'
                ? 'Permissão do microfone negada.'
                : 'Não foi possível acessar o microfone.', 'error');
            setMicState('idle');
        });
    }

    function stopMic() {
        if (micTimer) {
            clearInterval(micTimer);
            micTimer = null;
        }
        if (recorder && recorder.state !== 'inactive') {
            setMicState('busy');
            recorder.stop();
            return;
        }
        setMicState('idle');
    }

    function uploadAudio(blob) {
        setMicState('busy');
        var ext = blob.type.indexOf('mp4') !== -1 ? 'mp4' : (blob.type.indexOf('ogg') !== -1 ? 'ogg' : 'webm');
        var body = new FormData();
        body.append('csrf_token', csrf());
        body.append('audio', blob, 'gravacao.' + ext);

        fetch('/api/transcribe', {
            method: 'POST',
            body: body,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        }).then(function (result) {
            if (!result.ok || !result.data || !result.data.text) {
                throw new Error((result.data && result.data.error) || 'transcription_failed');
            }
            var text = String(result.data.text).trim();
            textarea.value = (textarea.value ? textarea.value.replace(/\s+$/, '') + ' ' : '') + text;
            textarea.focus();
        }).catch(function (error) {
            var messages = {
                disabled: 'Transcrição não configurada.',
                empty_transcript: 'Nenhuma fala identificada.',
                transcription_failed: 'Não foi possível transcrever o áudio.',
                audio_required: 'Áudio não recebido.',
                too_large: 'Áudio muito grande.',
                csrf: 'Sessão expirada. Recarregue a página.'
            };
            toast(messages[error.message] || 'Falha ao transcrever o áudio.', 'error');
        }).then(function () {
            setMicState('idle');
        });
    }

    /* ============================ MODAL ============================ */

    function buildModal() {
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML = ''
            + '<div class="modal-dialog modal-dialog-centered">'
            + '  <div class="modal-content">'
            + '    <div class="modal-header">'
            + '      <h5 class="modal-title"><i class="icon-base ti tabler-sparkles me-1"></i>Preencher com IA</h5>'
            + '      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>'
            + '    </div>'
            + '    <div class="modal-body">'
            + '      <p class="text-body-secondary small mb-2">Descreva os dados como faria no WhatsApp. O sistema preenche os campos e você revisa antes de salvar.</p>'
            + '      <textarea class="form-control" id="aiPrefillText" rows="5" maxlength="4000" '
            + '        placeholder="Ex.: Cliente João da Silva, telefone (11) 98888-7777, CEP 01310-100, nº 123. '
            + 'Veículo Gol 2019 prata, placa ABC1D23. Queixa: barulho na suspensão ao passar em lombada."></textarea>'
            + '      <div class="d-flex justify-content-between align-items-center gap-2 mt-2 flex-wrap">'
            + '        <button type="button" class="btn btn-sm btn-outline-secondary" id="aiPrefillMic">'
            + '          <i class="icon-base ti tabler-microphone"></i> <span id="aiPrefillMicLabel">Gravar</span>'
            + '        </button>'
            + '        <div class="form-check mb-0">'
            + '          <input class="form-check-input" type="checkbox" id="aiPrefillOverwrite">'
            + '          <label class="form-check-label small" for="aiPrefillOverwrite">Sobrescrever campos já preenchidos</label>'
            + '        </div>'
            + '      </div>'
            + '      <div class="alert alert-danger py-2 mt-3 mb-0 small" id="aiPrefillError" hidden></div>'
            + '    </div>'
            + '    <div class="modal-footer">'
            + '      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>'
            + '      <button type="button" class="btn btn-primary" id="aiPrefillSubmit">'
            + '        <span class="spinner-border spinner-border-sm me-1" id="aiPrefillSpinner" hidden></span>Preencher'
            + '      </button>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        document.body.appendChild(modalEl);

        textarea = modalEl.querySelector('#aiPrefillText');
        overwriteEl = modalEl.querySelector('#aiPrefillOverwrite');
        errorEl = modalEl.querySelector('#aiPrefillError');
        submitBtn = modalEl.querySelector('#aiPrefillSubmit');
        spinner = modalEl.querySelector('#aiPrefillSpinner');
        micBtn = modalEl.querySelector('#aiPrefillMic');
        micLabel = modalEl.querySelector('#aiPrefillMicLabel');

        if (window.bootstrap && window.bootstrap.Modal) {
            modal = new window.bootstrap.Modal(modalEl);
        }

        submitBtn.addEventListener('click', request);
        micBtn.addEventListener('click', function () {
            if (micState === 'recording') {
                stopMic();
            } else if (micState === 'idle') {
                startMic();
            }
        });

        textarea.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                request();
            }
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (micState === 'recording') {
                stopMic();
            }
        });
    }

    function showError(message) {
        errorEl.textContent = message || '';
        errorEl.hidden = !message;
    }

    function setBusy(state) {
        busy = state;
        submitBtn.disabled = state;
        spinner.hidden = !state;
    }

    function open(button) {
        activeButton = button;
        if (!modalEl) {
            buildModal();
        }

        textarea.value = '';
        overwriteEl.checked = false;
        showError('');
        setBusy(false);
        setMicState('idle');

        if (modal) {
            modal.show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }

        setTimeout(function () { textarea.focus(); }, 300);
    }

    function request() {
        if (busy || !activeButton) {
            return;
        }

        var text = textarea.value.trim();
        if (!text) {
            showError('Descreva os dados que deseja preencher.');
            return;
        }

        setBusy(true);
        showError('');

        var body = new URLSearchParams();
        body.set('csrf_token', csrf());
        body.set('form', activeButton.getAttribute('data-ai-prefill'));
        body.set('text', text);
        body.set('current', JSON.stringify(currentValues()));

        fetch('/assistant/prefill', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        }).then(function (result) {
            if (!result.ok || !result.data.ok) {
                throw new Error((result.data && result.data.error) || 'Não foi possível interpretar o texto.');
            }
            return apply(result.data.prefill, overwriteEl.checked);
        }).then(function (count) {
            if (modal) {
                modal.hide();
            }
            if (count > 0) {
                toast(count + ' campo' + (count === 1 ? '' : 's') + ' preenchido' + (count === 1 ? '' : 's') + '. Revise antes de salvar.', 'success');
            } else {
                toast('Nenhum campo novo para preencher.', 'info');
            }
        }).catch(function (error) {
            showError(error.message || 'Falha ao preencher o formulário.');
        }).then(function () {
            setBusy(false);
        });
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            open(button);
        });
    });
})();
