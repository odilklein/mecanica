/**
 * IA na OS — dois recursos:
 *
 * 1. "Ditar e melhorar": grava a queixa, transcreve e reescreve com a IA
 *    (mais detalhada e organizada), com opção de desfazer.
 * 2. "Texto para o cliente": gera uma mensagem de WhatsApp a partir dos dados
 *    da OS, com copiar e abrir o WhatsApp Web (sem API, sem automação).
 */
(function () {
    'use strict';

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

    function post(url, data) {
        var body = new URLSearchParams();
        body.set('csrf_token', csrf());
        Object.keys(data || {}).forEach(function (key) {
            body.set(key, String(data[key]));
        });

        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok, data: json };
            });
        }).then(function (result) {
            if (!result.ok || !result.data || result.data.error) {
                throw new Error((result.data && result.data.error) || 'Falha na solicitação.');
            }
            return result.data;
        });
    }

    /* ============================ QUEIXA ============================ */

    var noteTools = document.getElementById('osComplaintTools');
    var complaint = document.getElementById('initial_complaint');

    if (noteTools && complaint) {
        var micBtn = document.getElementById('osComplaintMic');
        var improveBtn = document.getElementById('osComplaintImprove');
        var statusEl = document.getElementById('osComplaintStatus');
        var micLabel = micBtn ? micBtn.querySelector('.os-note-label') : null;

        var recorder = null;
        var stream = null;
        var chunks = [];
        var timer = null;
        var startedAt = 0;
        var state = 'idle';
        var previousText = '';

        function setStatus(text, tone, undo) {
            if (!statusEl) return;
            statusEl.hidden = !text;
            statusEl.textContent = '';
            if (!text) return;

            statusEl.className = 'os-note-status' + (tone ? ' is-' + tone : '');
            statusEl.appendChild(document.createTextNode(text));

            if (undo) {
                var link = document.createElement('button');
                link.type = 'button';
                link.className = 'os-note-undo';
                link.textContent = 'Desfazer';
                link.addEventListener('click', function () {
                    complaint.value = previousText;
                    complaint.dispatchEvent(new Event('input', { bubbles: true }));
                    setStatus('Alteração desfeita.', 'info');
                });
                statusEl.appendChild(link);
            }
        }

        function vehicleContext() {
            var $ = window.jQuery;
            var bits = [];

            var selected = document.getElementById('vehicle_id');
            if (selected && selected.value && $) {
                bits.push($(selected).find('option:selected').text().trim());
            } else {
                var plate = document.getElementById('plate');
                if (plate && plate.value) bits.push(plate.value);
                if ($ && $('#fipe_brand_code').length) {
                    bits.push($('#fipe_brand_code').find('option:selected').text().trim());
                    bits.push($('#fipe_model_code').find('option:selected').text().trim());
                    bits.push($('#fipe_year_code').find('option:selected').text().trim());
                }
            }

            return bits.filter(Boolean).join(' · ').substring(0, 300);
        }

        function enhance(text, tone) {
            var clean = String(text || '').trim();
            if (clean.length < 3) {
                setStatus('Fale ou escreva a queixa primeiro.', 'error');
                return;
            }

            setStatus('Melhorando com IA…', 'info');
            if (improveBtn) improveBtn.disabled = true;

            post(noteTools.getAttribute('data-enhance-endpoint') || '/assistant/enhance', {
                text: clean,
                kind: 'complaint',
                context: vehicleContext()
            }).then(function (data) {
                previousText = complaint.value;
                complaint.value = data.text;
                complaint.dispatchEvent(new Event('input', { bubbles: true }));
                setStatus('Texto melhorado.', 'success', true);
                toast('Queixa reescrita. Revise antes de salvar.', 'success');
            }).catch(function (error) {
                setStatus(error.message || 'Não foi possível melhorar o texto.', 'error');
            }).then(function () {
                if (improveBtn) improveBtn.disabled = false;
            });
        }

        function stopStream() {
            if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
            }
        }

        function setMicState(next) {
            state = next;
            if (!micBtn) return;

            if (next === 'recording') {
                micBtn.classList.add('is-recording');
                if (micLabel) micLabel.textContent = '0:00';
            } else {
                micBtn.classList.remove('is-recording');
                micBtn.disabled = next === 'busy';
                if (micLabel) {
                    micLabel.textContent = next === 'busy' ? 'Processando…' : 'Ditar e melhorar';
                }
            }
        }

        function upload(blob) {
            setMicState('busy');
            setStatus('Transcrevendo o áudio…', 'info');

            var ext = blob.type.indexOf('mp4') !== -1 ? 'mp4' : (blob.type.indexOf('ogg') !== -1 ? 'ogg' : 'webm');
            var form = new FormData();
            form.append('csrf_token', csrf());
            form.append('audio', blob, 'queixa.' + ext);

            fetch(noteTools.getAttribute('data-endpoint') || '/api/transcribe', {
                method: 'POST',
                body: form,
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
                complaint.value = (complaint.value ? complaint.value.replace(/\s+$/, '') + ' ' : '') + text;
                complaint.dispatchEvent(new Event('input', { bubbles: true }));
                setMicState('idle');
                enhance(complaint.value, 'success');
            }).catch(function (error) {
                var messages = {
                    disabled: 'Transcrição não configurada.',
                    empty_transcript: 'Nenhuma fala identificada.',
                    transcription_failed: 'Não foi possível transcrever o áudio.',
                    audio_required: 'Áudio não recebido.',
                    too_large: 'Áudio muito grande.'
                };
                setMicState('idle');
                setStatus(messages[error.message] || 'Falha ao transcrever o áudio.', 'error');
            });
        }

        function startMic() {
            if (state !== 'idle') return;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
                setStatus('Seu navegador não suporta gravação de áudio.', 'error');
                return;
            }

            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (mediaStream) {
                stream = mediaStream;
                chunks = [];
                recorder = new MediaRecorder(stream);

                recorder.ondataavailable = function (event) {
                    if (event.data && event.data.size > 0) chunks.push(event.data);
                };

                recorder.onstop = function () {
                    stopStream();
                    var blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
                    if (!blob.size) {
                        setMicState('idle');
                        return;
                    }
                    upload(blob);
                };

                recorder.start(250);
                startedAt = Date.now();
                setMicState('recording');
                setStatus('Gravando…', 'recording');

                timer = setInterval(function () {
                    var elapsed = Math.floor((Date.now() - startedAt) / 1000);
                    if (micLabel) {
                        micLabel.textContent = Math.floor(elapsed / 60) + ':' + String(elapsed % 60).padStart(2, '0');
                    }
                    if (elapsed >= 120) stopMic();
                }, 250);
            }).catch(function (error) {
                var name = error && error.name ? error.name : '';
                setStatus(name === 'NotAllowedError'
                    ? 'Permissão do microfone negada.'
                    : 'Não foi possível acessar o microfone.', 'error');
            });
        }

        function stopMic() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
            if (recorder && recorder.state !== 'inactive') {
                recorder.stop();
                return;
            }
            setMicState('idle');
        }

        if (micBtn) {
            micBtn.addEventListener('click', function () {
                if (state === 'recording') {
                    stopMic();
                } else if (state === 'idle') {
                    startMic();
                }
            });
        }

        if (improveBtn) {
            improveBtn.addEventListener('click', function () {
                enhance(complaint.value);
            });
        }
    }

    /* ============================ MENSAGEM AO CLIENTE ============================ */

    var messageButtons = document.querySelectorAll('[data-os-message]');
    if (messageButtons.length) {
        var modalEl = null;
        var bsModal = null;
        var textarea = null;
        var whatsappLink = null;
        var subtitle = null;
        var copyBtn = null;
        var regenBtn = null;
        var currentOrder = 0;
        var currentPhone = '';
        var busy = false;

        function buildModal() {
            modalEl = document.createElement('div');
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.innerHTML = ''
                + '<div class="modal-dialog modal-dialog-centered">'
                + '  <div class="modal-content">'
                + '    <div class="modal-header">'
                + '      <div><h5 class="modal-title m-0"><i class="icon-base ti tabler-brand-whatsapp me-1"></i>Texto para o cliente</h5>'
                + '      <small class="text-body-secondary" id="osMsgSubtitle"></small></div>'
                + '      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>'
                + '    </div>'
                + '    <div class="modal-body">'
                + '      <p class="text-body-secondary small mb-2">Revise a mensagem. Ela não é enviada automaticamente — você copia e cola no seu WhatsApp.</p>'
                + '      <textarea class="form-control" id="osMsgText" rows="9"></textarea>'
                + '      <div class="alert alert-danger py-2 mt-3 mb-0 small" id="osMsgError" hidden></div>'
                + '    </div>'
                + '    <div class="modal-footer justify-content-between">'
                + '      <button type="button" class="btn btn-outline-secondary btn-sm" id="osMsgRegen">'
                + '        <i class="icon-base ti tabler-refresh"></i> Gerar novamente'
                + '      </button>'
                + '      <div class="d-flex gap-2">'
                + '        <button type="button" class="btn btn-outline-primary" id="osMsgCopy">'
                + '          <i class="icon-base ti tabler-copy"></i> Copiar'
                + '        </button>'
                + '        <a class="btn btn-success" id="osMsgWhatsapp" target="_blank" rel="noopener">'
                + '          <i class="icon-base ti tabler-brand-whatsapp"></i> Abrir WhatsApp'
                + '        </a>'
                + '      </div>'
                + '    </div>'
                + '  </div>'
                + '</div>';

            document.body.appendChild(modalEl);

            textarea = modalEl.querySelector('#osMsgText');
            whatsappLink = modalEl.querySelector('#osMsgWhatsapp');
            subtitle = modalEl.querySelector('#osMsgSubtitle');
            copyBtn = modalEl.querySelector('#osMsgCopy');
            regenBtn = modalEl.querySelector('#osMsgRegen');

            if (window.bootstrap && window.bootstrap.Modal) {
                bsModal = new window.bootstrap.Modal(modalEl);
            }

            textarea.addEventListener('input', updateWhatsapp);
            copyBtn.addEventListener('click', function () {
                var text = textarea.value.trim();
                if (!text) return;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        toast('Mensagem copiada.', 'success');
                    }).catch(function () {
                        toast('Não foi possível copiar.', 'error');
                    });
                } else {
                    textarea.select();
                    document.execCommand('copy');
                    toast('Mensagem copiada.', 'success');
                }
            });
            regenBtn.addEventListener('click', function () {
                generate(currentOrder);
            });
        }

        function updateWhatsapp() {
            if (!whatsappLink) return;
            var text = encodeURIComponent(textarea.value || '');
            var phone = currentPhone ? '55' + currentPhone.replace(/^55/, '') : '';
            whatsappLink.href = 'https://wa.me/' + phone + '?text=' + text;
        }

        function generate(orderId) {
            if (busy) return;
            busy = true;
            currentOrder = orderId;

            if (!modalEl) buildModal();
            if (bsModal) bsModal.show();

            var errorEl = modalEl.querySelector('#osMsgError');
            errorEl.hidden = true;
            textarea.value = '';
            textarea.placeholder = 'Gerando mensagem…';
            regenBtn.disabled = true;
            copyBtn.disabled = true;

            post('/assistant/customer-message', { order_id: orderId }).then(function (data) {
                textarea.placeholder = '';
                textarea.value = data.message || '';
                currentPhone = data.phone || '';
                subtitle.textContent = data.client_name ? 'Cliente: ' + data.client_name : '';
                copyBtn.disabled = false;
                updateWhatsapp();
            }).catch(function (error) {
                textarea.placeholder = '';
                errorEl.textContent = error.message || 'Não foi possível gerar a mensagem.';
                errorEl.hidden = false;
            }).then(function () {
                regenBtn.disabled = false;
                busy = false;
            });
        }

        messageButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                generate(parseInt(button.getAttribute('data-os-message'), 10) || 0);
            });
        });
    }
})();
