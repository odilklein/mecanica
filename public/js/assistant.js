/* =============================================
   ASSISTENTE — chat com streaming (SSE), timeline
   de ferramentas, widgets e aprovação de propostas.

   Regras deste arquivo:
   - nada de innerHTML com dados do modelo/servidor;
   - todo texto é inserido via textContent;
   - links só http(s) ou caminhos internos.
   ============================================= */

(function () {
    'use strict';

    var bootEl = document.getElementById('assistantBootstrap');
    if (!bootEl) {
        return;
    }

    var boot;
    try {
        boot = JSON.parse(bootEl.textContent || '{}');
    } catch (e) {
        return;
    }

    var log = document.getElementById('assistantLog');
    var form = document.getElementById('assistantForm');
    var input = document.getElementById('assistantInput');
    var sendBtn = document.getElementById('assistantSend');
    var stopBtn = document.getElementById('assistantStop');
    var slashBox = document.getElementById('assistantSlash');
    var pendingBox = document.getElementById('assistantPending');
    var pendingCount = document.getElementById('assistantPendingCount');
    var statusDot = document.getElementById('assistantStatusDot');
    var statusText = document.getElementById('assistantStatusText');
    var usageEl = document.getElementById('assistantUsage');
    var usageBar = document.getElementById('assistantUsageBar');
    var newBtn = document.getElementById('assistantNew');
    var copyAllBtn = document.getElementById('assistantCopyAll');
    var modelBtn = document.getElementById('assistantModelBtn');
    var modelLabel = document.getElementById('assistantModelLabel');
    var modelMenu = document.getElementById('assistantModelMenu');
    var modelChip = document.getElementById('assistantModelChip');
    var attachmentsEl = document.getElementById('assistantAttachments');
    var attachBtn = document.getElementById('assistantAttach');
    var fileInput = document.getElementById('assistantFile');
    var dropEl = document.getElementById('assistantDrop');
    var composerWrap = document.querySelector('.assistant-composer-wrap');

    if (!log || !form || !input) {
        return;
    }

    var state = {
        sessionId: boot.sessionId,
        streaming: false,
        controller: null,
        current: null,
        slashIndex: 0,
        slashItems: [],
        proposalIndex: boot.proposalIndex || {},
        model: boot.model,
        effort: '',
        mode: '',
        hasUserMessages: (boot.messages || []).some(function (message) {
            return message.role === 'user';
        }),
        attachments: [],
        uploading: 0
    };

    /* ============================ UTIL ============================ */

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }
        return node;
    }

    function money(value) {
        var n = Number(value || 0);
        return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatBytes(bytes) {
        var n = Number(bytes || 0);
        if (n < 1024) {
            return n + ' B';
        }
        if (n < 1024 * 1024) {
            return (n / 1024).toFixed(0) + ' KB';
        }
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // Título curto da conversa, no estilo dos chatbots: parte da primeira mensagem.
    function titleFromText(text) {
        var value = String(text || '').replace(/\s+/g, ' ').trim();
        value = value.replace(/^(oi|olá|ola|bom dia|boa tarde|boa noite)[,!\s]+/i, '');
        value = value.replace(/^(por favor|pfv|por gentileza)[,!\s]+/i, '');
        value = value.replace(/^(você pode|voce pode|vc pode|pode|poderia|consegue|me (ajuda|ajude|dá|da|dê|de|diz|diga|mostra|mostre|informa|informe)|gostaria de saber|quero saber|preciso saber|preciso de|existe|tem|há|ha|qual|quais)\s+/i, '');
        value = value.replace(/^(um|uma|o|a|os|as)\s+/i, '');
        value = value.replace(/[\s?!.;,]+$/, '');

        if (value.length > 52) {
            value = value.slice(0, 52);
            var cut = value.lastIndexOf(' ');
            if (cut > 24) {
                value = value.slice(0, cut);
            }
            value = value.replace(/[\s,.;:!?]+$/, '') + '…';
        }

        return value ? value.charAt(0).toUpperCase() + value.slice(1) : '';
    }

    function titleCurrentSession(text) {
        if (state.hasUserMessages) {
            return;
        }
        state.hasUserMessages = true;

        var title = titleFromText(text);
        var row = document.querySelector('.assistant-session-row.is-current');
        if (!title || !row) {
            return;
        }

        var label = row.querySelector('.assistant-session-text');
        if (label) {
            label.textContent = title;
        }
        var link = row.querySelector('.assistant-session');
        if (link) {
            link.title = title;
        }
        var remove = row.querySelector('.assistant-session-delete');
        if (remove) {
            remove.setAttribute('data-session-title', title);
        }
    }

    function attachmentIcon(kind) {
        return {
            image: 'tabler-photo',
            pdf: 'tabler-file-text',
            audio: 'tabler-microphone',
            text: 'tabler-file'
        }[kind] || 'tabler-file';
    }

    function timeLabel(value) {
        if (!value) {
            return '';
        }
        var iso = String(value).replace(' ', 'T');
        var date = new Date(iso);
        if (isNaN(date.getTime())) {
            return '';
        }
        var now = new Date();
        if (date.toDateString() === now.toDateString()) {
            return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
            + ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    function safeUrl(url) {
        var value = String(url || '');
        if (/^https?:\/\//i.test(value)) {
            return value;
        }
        if (value.charAt(0) === '/' && value.charAt(1) !== '/') {
            return value;
        }
        return null;
    }

    function toast(message, tone) {
        var box = document.querySelector('.assistant-toast');
        if (!box) {
            box = el('div', 'assistant-toast');
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

    function setStatus(mode, text) {
        if (statusDot) {
            statusDot.classList.toggle('is-live', mode === 'live');
            statusDot.classList.toggle('is-error', mode === 'error');
        }
        if (text && statusText) {
            statusText.textContent = text;
        }
    }

    /* ============================ MODO DE RESPOSTA ============================ */

    function modes() {
        return boot.modes || [];
    }

    function modeByKey(key) {
        return modes().filter(function (mode) {
            return mode.key === key;
        })[0] || null;
    }

    function modeFor(modelId, effort) {
        return modes().filter(function (mode) {
            return mode.model === modelId && mode.effort === effort;
        })[0] || null;
    }

    function currentMode() {
        return modeByKey(state.mode) || modes()[0] || null;
    }

    function renderModelMenu() {
        if (!modelMenu) {
            return;
        }
        modelMenu.innerHTML = '';

        var active = currentMode();
        modes().forEach(function (mode) {
            var isActive = !!active && mode.key === active.key;
            var item = el('button', 'assistant-model-item' + (isActive ? ' is-active' : ''));
            item.type = 'button';
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');

            var text = document.createElement('span');
            text.className = 'assistant-model-item-text';
            text.appendChild(el('strong', null, mode.label));
            text.appendChild(el('small', null, mode.hint));
            item.appendChild(text);

            if (mode.is_default) {
                var badges = document.createElement('span');
                badges.className = 'assistant-model-badges';
                badges.appendChild(el('span', 'assistant-model-default', 'padrão'));
                item.appendChild(badges);
            }

            item.addEventListener('click', function () {
                selectMode(mode.key);
            });

            modelMenu.appendChild(item);
        });
    }

    function applyMode(mode) {
        if (!mode) {
            return;
        }
        state.mode = mode.key;
        state.model = mode.model;
        state.effort = mode.effort;
        try {
            localStorage.setItem('mecanica-assistant-mode', mode.key);
        } catch (e) {}
        updateModelUi();
    }

    function selectMode(key) {
        applyMode(modeByKey(key));
        closeModelMenu();
    }

    function updateModelUi() {
        var mode = currentMode();

        if (modelLabel) {
            modelLabel.textContent = mode ? mode.label : 'Modo';
        }
        if (modelChip) {
            modelChip.textContent = mode ? mode.label : '';
        }

        renderModelMenu();
    }

    function openModelMenu() {
        if (!modelMenu) {
            return;
        }
        modelMenu.hidden = false;
        if (modelBtn) {
            modelBtn.setAttribute('aria-expanded', 'true');
        }
    }

    function closeModelMenu() {
        if (!modelMenu) {
            return;
        }
        modelMenu.hidden = true;
        if (modelBtn) {
            modelBtn.setAttribute('aria-expanded', 'false');
        }
    }

    /* ============================ ANEXOS ============================ */

    function guessKind(type, name) {
        var value = String(type || '');
        var lower = String(name || '').toLowerCase();

        if (value.indexOf('image/') === 0) {
            return 'image';
        }
        if (value === 'application/pdf' || /\.pdf$/.test(lower)) {
            return 'pdf';
        }
        if (value.indexOf('audio/') === 0) {
            return 'audio';
        }

        return 'text';
    }

    function attachmentChips(list, options) {
        var wrap = el('div', 'assistant-file-chips');
        options = options || {};

        (list || []).forEach(function (attachment) {
            var chip;
            if (options.link === false) {
                chip = el('span', 'assistant-file-chip');
            } else {
                chip = el('a', 'assistant-file-chip');
                chip.href = '/assistant/attachments/' + attachment.id;
                chip.target = '_blank';
                chip.rel = 'noopener';
            }

            chip.appendChild(el('i', 'icon-base ti ' + attachmentIcon(attachment.kind)));

            var text = document.createElement('span');
            text.className = 'assistant-file-chip-text';
            text.appendChild(el('strong', null, attachment.filename));
            var metaBits = [formatBytes(attachment.bytes)];
            if (attachment.pages) {
                metaBits.push(attachment.pages + ' pág.');
            }
            if (attachment.chars) {
                metaBits.push(attachment.chars < 1000
                    ? attachment.chars + ' chars'
                    : Math.round(attachment.chars / 1000) + 'k chars');
            }
            text.appendChild(el('small', null, metaBits.join(' · ')));
            chip.appendChild(text);

            wrap.appendChild(chip);
        });

        return wrap;
    }

    function renderAttachmentTray() {
        if (!attachmentsEl) {
            return;
        }

        attachmentsEl.innerHTML = '';
        if (!state.attachments.length) {
            attachmentsEl.hidden = true;
            return;
        }

        attachmentsEl.hidden = false;

        state.attachments.forEach(function (attachment) {
            var chip = el('span', 'assistant-file-chip' + (attachment.uploading ? ' is-uploading' : ''));

            if (attachment.uploading) {
                chip.appendChild(el('span', 'assistant-tool-spinner'));
            } else {
                chip.appendChild(el('i', 'icon-base ti ' + attachmentIcon(attachment.kind)));
            }

            var text = document.createElement('span');
            text.className = 'assistant-file-chip-text';
            text.appendChild(el('strong', null, attachment.filename || 'arquivo'));
            text.appendChild(el('small', null, attachment.uploading ? 'enviando…' : formatBytes(attachment.bytes)));
            chip.appendChild(text);

            if (!attachment.uploading) {
                var remove = el('button', 'assistant-file-remove', '×');
                remove.type = 'button';
                remove.title = 'Remover anexo';
                remove.addEventListener('click', function () {
                    removeAttachment(attachment);
                });
                chip.appendChild(remove);
            }

            attachmentsEl.appendChild(chip);
        });
    }

    function removeAttachment(attachment) {
        var index = state.attachments.indexOf(attachment);
        if (index >= 0) {
            state.attachments.splice(index, 1);
        }
        renderAttachmentTray();

        if (attachment.id) {
            var body = new URLSearchParams();
            body.set('csrf_token', boot.csrf);
            fetch('/assistant/attachments/' + attachment.id + '/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'same-origin'
            }).catch(function () {});
        }
    }

    function uploadFiles(fileList) {
        var files = Array.prototype.slice.call(fileList || []);
        if (!files.length) {
            return;
        }

        var limits = boot.attachmentLimits || {};
        var maxBytes = Number(limits.max_bytes || 10485760);
        var maxImageBytes = Number(limits.max_image_bytes || 5242880);
        var maxImages = Number(limits.max_images || 3);
        var imagesNow = state.attachments.filter(function (a) {
            return a.kind === 'image';
        }).length;

        files.forEach(function (file) {
            var kind = guessKind(file.type, file.name);

            if (file.size > maxBytes) {
                toast(file.name + ' passa de ' + formatBytes(maxBytes) + '.', 'error');
                return;
            }
            if (kind === 'image') {
                if (imagesNow >= maxImages) {
                    toast('Máximo de ' + maxImages + ' imagens por mensagem.', 'error');
                    return;
                }
                if (file.size > maxImageBytes) {
                    toast(file.name + ' passa de ' + formatBytes(maxImageBytes) + '.', 'error');
                    return;
                }
                imagesNow++;
            }

            var temp = {
                id: null,
                filename: file.name,
                kind: kind,
                bytes: file.size,
                uploading: true
            };
            state.attachments.push(temp);
            state.uploading++;
            renderAttachmentTray();
            updateSendState();

            var form = new FormData();
            form.append('csrf_token', boot.csrf);
            form.append('session_id', String(state.sessionId));
            form.append('file', file);

            fetch(boot.uploadUrl, {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                if (!result.ok || !result.data.ok) {
                    throw new Error(result.data.error || 'Falha no envio do arquivo.');
                }
                var index = state.attachments.indexOf(temp);
                if (index >= 0) {
                    state.attachments[index] = result.data.attachment;
                }
            }).catch(function (error) {
                var index = state.attachments.indexOf(temp);
                if (index >= 0) {
                    state.attachments.splice(index, 1);
                }
                toast(error.message || 'Falha no envio do arquivo.', 'error');
            }).then(function () {
                state.uploading = Math.max(0, state.uploading - 1);
                renderAttachmentTray();
                updateSendState();
            });
        });
    }

    function updateSendState() {
        if (sendBtn) {
            sendBtn.disabled = state.streaming || state.uploading > 0;
        }
    }

    /* ============================ MARKDOWN ============================ */

    // Placeholders de código usam \u0001, que não aparece em texto do modelo.
    var CODE_MARK = '\u0001';

    function inline(container, text) {
        var codes = [];
        var masked = String(text).replace(/`[^`]+`/g, function (match) {
            codes.push(match.slice(1, -1));
            return CODE_MARK + (codes.length - 1) + CODE_MARK;
        });

        var pattern = /(\*\*[^*]+\*\*)|(\*[^*\n]+\*)|(?<![\w])(_[^_\n]+_)(?![\w])|(\[[^\]]+\]\([^)\s]+\))/g;
        var last = 0;
        var match;

        while ((match = pattern.exec(masked)) !== null) {
            if (match.index > last) {
                appendRich(container, masked.slice(last, match.index), codes);
            }

            var token = match[0];

            if (token.slice(0, 2) === '**') {
                var strong = document.createElement('strong');
                appendRich(strong, token.slice(2, -2), codes);
                container.appendChild(strong);
            } else if (token.charAt(0) === '*' || token.charAt(0) === '_') {
                var em = document.createElement('em');
                appendRich(em, token.slice(1, -1), codes);
                container.appendChild(em);
            } else {
                var linkMatch = /^\[([^\]]+)\]\(([^)\s]+)\)$/.exec(token);
                var href = linkMatch ? safeUrl(linkMatch[2]) : null;
                if (href) {
                    var a = el('a', null, linkMatch[1]);
                    a.href = href;
                    if (/^https?:/i.test(href)) {
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                    }
                    container.appendChild(a);
                } else {
                    container.appendChild(document.createTextNode(token));
                }
            }

            last = pattern.lastIndex;
        }

        if (last < masked.length) {
            appendRich(container, masked.slice(last), codes);
        }
    }

    function appendRich(container, text, codes) {
        var pattern = new RegExp(CODE_MARK + '(\\d+)' + CODE_MARK, 'g');
        var last = 0;
        var match;

        while ((match = pattern.exec(text)) !== null) {
            if (match.index > last) {
                container.appendChild(document.createTextNode(text.slice(last, match.index)));
            }
            container.appendChild(el('code', null, codes[Number(match[1])] || ''));
            last = pattern.lastIndex;
        }

        if (last < text.length) {
            container.appendChild(document.createTextNode(text.slice(last)));
        }
    }

    function isTable(lines) {
        return lines.length >= 2
            && lines[0].indexOf('|') !== -1
            && /^\s*\|?[\s:|-]+\|[\s:|-]*$/.test(lines[1]);
    }

    function renderTable(lines) {
        var wrap = el('div', 'assistant-table-wrap');
        var table = el('table', 'assistant-table');
        var head = document.createElement('thead');
        var headRow = document.createElement('tr');
        var cells = lines[0].replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|');

        cells.forEach(function (cell) {
            headRow.appendChild(el('th', null, cell.trim()));
        });
        head.appendChild(headRow);
        table.appendChild(head);

        var body = document.createElement('tbody');
        lines.slice(2).forEach(function (line) {
            var row = document.createElement('tr');
            line.replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|').forEach(function (cell) {
                var td = document.createElement('td');
                inline(td, cell.trim());
                row.appendChild(td);
            });
            body.appendChild(row);
        });
        table.appendChild(body);
        wrap.appendChild(table);

        return wrap;
    }

    function renderBlocks(text) {
        var frag = document.createDocumentFragment();
        var blocks = text.split(/\n{2,}/);

        blocks.forEach(function (block) {
            var lines = block.split('\n').filter(function (line) {
                return line.trim() !== '';
            });
            if (!lines.length) {
                return;
            }

            if (isTable(lines)) {
                frag.appendChild(renderTable(lines));
                return;
            }

            if (/^#{1,4}\s+/.test(lines[0]) && lines.length === 1) {
                var level = Math.min(4, lines[0].match(/^#+/)[0].length);
                var heading = el('h' + Math.min(6, level + 2));
                inline(heading, lines[0].replace(/^#+\s+/, ''));
                frag.appendChild(heading);
                return;
            }

            if (lines.every(function (line) { return /^\s*[-*]\s+/.test(line); })) {
                var ul = document.createElement('ul');
                lines.forEach(function (line) {
                    var li = document.createElement('li');
                    inline(li, line.replace(/^\s*[-*]\s+/, ''));
                    ul.appendChild(li);
                });
                frag.appendChild(ul);
                return;
            }

            if (lines.every(function (line) { return /^\s*\d+[.)]\s+/.test(line); })) {
                var ol = document.createElement('ol');
                lines.forEach(function (line) {
                    var li = document.createElement('li');
                    inline(li, line.replace(/^\s*\d+[.)]\s+/, ''));
                    ol.appendChild(li);
                });
                frag.appendChild(ol);
                return;
            }

            if (lines[0].charAt(0) === '>') {
                var quote = el('blockquote');
                inline(quote, lines.map(function (line) {
                    return line.replace(/^>\s?/, '');
                }).join(' '));
                frag.appendChild(quote);
                return;
            }

            var p = el('p');
            lines.forEach(function (line, index) {
                if (index > 0) {
                    p.appendChild(document.createElement('br'));
                }
                inline(p, line);
            });
            frag.appendChild(p);
        });

        return frag;
    }

    function renderMarkdown(text) {
        var frag = document.createDocumentFragment();
        var source = String(text || '');
        var fence = /```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g;
        var last = 0;
        var match;

        while ((match = fence.exec(source)) !== null) {
            if (match.index > last) {
                frag.appendChild(renderBlocks(source.slice(last, match.index)));
            }
            var pre = document.createElement('pre');
            var code = document.createElement('code');
            code.textContent = match[2].replace(/\n$/, '');
            pre.appendChild(code);
            frag.appendChild(pre);
            last = fence.lastIndex;
        }

        if (last < source.length) {
            frag.appendChild(renderBlocks(source.slice(last)));
        }

        return frag;
    }

    /* ============================ WIDGETS ============================ */

    function widgetShell(widget, flush) {
        var card = el('div', 'assistant-widget');
        var head = el('div', 'assistant-widget-head');
        head.appendChild(el('span', null, widget.title || 'Resultado'));

        if (widget.url) {
            var href = safeUrl(widget.url);
            if (href) {
                var link = el('a', null, 'abrir');
                link.href = href;
                head.appendChild(link);
            }
        }
        card.appendChild(head);

        var body = el('div', 'assistant-widget-body' + (flush ? ' is-flush' : ''));
        card.appendChild(body);

        return { card: card, body: body };
    }

    function widgetTable(widget) {
        var shell = widgetShell(widget, true);
        var wrap = el('div', 'assistant-table-wrap');
        var table = el('table', 'assistant-table');
        var thead = document.createElement('thead');
        var headRow = document.createElement('tr');

        (widget.columns || []).forEach(function (col) {
            headRow.appendChild(el('th', null, col.label || col.key));
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        (widget.rows || []).forEach(function (row) {
            var tr = document.createElement('tr');
            (widget.columns || []).forEach(function (col, index) {
                var td = document.createElement('td');
                var value = row[col.key];
                var text = value === null || value === undefined ? '' : String(value);

                if (col.money) {
                    td.className = 'assistant-money';
                    text = money(value);
                }

                if (index === 0 && widget.link && row[widget.link.key] !== undefined) {
                    var href = safeUrl(widget.link.base + row[widget.link.key]);
                    if (href) {
                        var a = el('a', null, text);
                        a.href = href;
                        td.appendChild(a);
                    } else {
                        td.textContent = text;
                    }
                } else {
                    td.textContent = text;
                }

                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        shell.body.appendChild(wrap);

        return shell.card;
    }

    function widgetKpi(widget) {
        var shell = widgetShell(widget);
        var grid = el('div', 'assistant-kpis');

        (widget.items || []).forEach(function (item) {
            var box = el('div', 'assistant-kpi');
            box.appendChild(el('div', 'assistant-kpi-value', item.value));
            box.appendChild(el('div', 'assistant-kpi-label', item.label));
            grid.appendChild(box);
        });

        shell.body.appendChild(grid);
        return shell.card;
    }

    function widgetBars(widget) {
        var shell = widgetShell(widget);
        var wrap = el('div', 'assistant-bars');
        var max = 1;

        (widget.items || []).forEach(function (item) {
            max = Math.max(max, Number(item.value || 0));
        });

        (widget.items || []).forEach(function (item) {
            var row = el('div', 'assistant-bar');
            row.appendChild(el('span', 'assistant-bar-label', item.label));
            var track = el('span', 'assistant-bar-track');
            var fill = el('span', 'assistant-bar-fill');
            fill.style.width = Math.max(0, Math.min(100, (Number(item.value || 0) / max) * 100)) + '%';
            track.appendChild(fill);
            row.appendChild(track);
            row.appendChild(el('span', 'assistant-bar-value', item.value));
            wrap.appendChild(row);
        });

        shell.body.appendChild(wrap);
        return shell.card;
    }

    function widgetEmpty(widget) {
        var shell = widgetShell(widget);
        shell.body.appendChild(el('div', 'assistant-empty', widget.text || 'Nada encontrado.'));
        return shell.card;
    }

    function widgetOsDetail(widget) {
        var shell = widgetShell(widget);
        var os = widget.os || {};
        var wrap = el('div', 'assistant-os');

        var top = el('div', 'assistant-os-top');
        top.appendChild(el('span', 'assistant-os-number', 'OS ' + String(os.number || '').padStart(4, '0')));
        top.appendChild(el('span', 'assistant-pill is-primary', os.status_label || os.status));
        top.appendChild(el('span', 'assistant-pill', os.priority_label || os.priority));
        wrap.appendChild(top);

        var grid = el('div', 'assistant-os-grid');
        [
            ['Cliente', (widget.client || {}).name],
            ['Telefone', (widget.client || {}).phone],
            ['Veículo', [(widget.vehicle || {}).plate, (widget.vehicle || {}).model].filter(Boolean).join(' ')],
            ['Ano / KM', [(widget.vehicle || {}).year || '', (widget.vehicle || {}).km ? Number((widget.vehicle || {}).km).toLocaleString('pt-BR') + ' km' : ''].filter(Boolean).join(' · ')],
            ['Entrada', (os.entry_date || '').split('-').reverse().join('/')],
            ['Queixa', os.complaint || '—']
        ].forEach(function (pair) {
            var field = el('div', 'assistant-os-field');
            field.appendChild(el('span', null, pair[0]));
            field.appendChild(el('strong', null, pair[1] || '—'));
            grid.appendChild(field);
        });
        wrap.appendChild(grid);

        var tasks = widget.tasks || [];
        if (tasks.length) {
            wrap.appendChild(el('div', 'assistant-os-block-title', 'Tarefas'));
            var list = el('ul', 'assistant-proposal-items');
            tasks.forEach(function (task) {
                var li = document.createElement('li');
                li.appendChild(el('span', null, task.description));
                li.appendChild(el('span', null, task.status === 'done' ? 'concluída'
                    : (task.status === 'in_progress' ? 'em andamento' : 'pendente')));
                list.appendChild(li);
            });
            wrap.appendChild(list);
        }

        var parts = widget.part_orders || [];
        if (parts.length) {
            wrap.appendChild(el('div', 'assistant-os-block-title', 'Pedidos de peças'));
            var partList = el('ul', 'assistant-proposal-items');
            parts.forEach(function (part) {
                var li = document.createElement('li');
                li.appendChild(el('span', null, part.items || ('Pedido #' + part.id)));
                li.appendChild(el('span', null, part.status));
                partList.appendChild(li);
            });
            wrap.appendChild(partList);
        }

        if (widget.budget) {
            wrap.appendChild(el('div', 'assistant-os-block-title', 'Orçamento'));
            wrap.appendChild(el('div', 'assistant-os-field', money(widget.budget.total)));
        }

        shell.body.appendChild(wrap);

        if (widget.url) {
            var link = safeUrl(widget.url);
            if (link) {
                var foot = el('div', 'assistant-proposal-foot');
                var a = el('a', 'assistant-btn', 'Abrir OS no sistema');
                a.href = link;
                foot.appendChild(a);
                shell.card.appendChild(foot);
            }
        }

        return shell.card;
    }

    function proposalStatusLabel(status) {
        return {
            pending: 'aguardando aprovação',
            approved: 'aprovada',
            applied: 'aplicada',
            rejected: 'rejeitada',
            expired: 'expirada',
            failed: 'falhou',
            superseded: 'substituída'
        }[status] || status;
    }

    function confidenceLabel(value) {
        return { high: 'confiança alta', medium: 'confiança média', low: 'confiança baixa' }[value] || 'sem fonte';
    }

    function fitmentClass(value) {
        return {
            'compatível': 'is-success',
            'provável': 'is-warning',
            'verificar': 'is-danger'
        }[value] || '';
    }

    function widgetProposal(widget) {
        var proposal = widget.proposal || {};
        var fresh = state.proposalIndex[proposal.id];
        if (fresh) {
            proposal.status = fresh.status || proposal.status;
            proposal.intent = fresh.intent || proposal.intent;
            proposal.result = fresh.result || null;
            proposal.error = fresh.error || null;
        }

        var card = el('div', 'assistant-proposal is-' + (proposal.status || 'pending'));
        card.id = 'proposal-' + proposal.id;
        card.dataset.proposalId = String(proposal.id);
        if (proposal.intent) {
            card.dataset.intent = proposal.intent;
        }

        if (proposal.status === 'pending' && proposal.intent) {
            supersedeSiblings(proposal.intent, proposal.id);
        }

        var head = el('div', 'assistant-proposal-head');
        var headText = document.createElement('div');
        headText.appendChild(el('strong', null, proposal.title || 'Proposta'));
        headText.appendChild(el('span', null, proposal.subtitle || ''));
        head.appendChild(headText);

        var statusClass = {
            pending: 'is-warning',
            approved: 'is-primary',
            applied: 'is-success',
            rejected: 'is-danger',
            expired: 'is-danger',
            failed: 'is-danger',
            superseded: ''
        }[proposal.status] || '';
        head.appendChild(el('span', 'assistant-pill ' + statusClass, proposalStatusLabel(proposal.status)));
        card.appendChild(head);

        var body = el('div', 'assistant-proposal-body');

        var fields = proposal.fields || [];
        if (fields.length) {
            var grid = el('div', 'assistant-proposal-fields');
            fields.forEach(function (field) {
                var box = el('div', 'assistant-proposal-field');
                box.appendChild(el('span', null, field.label));
                box.appendChild(el('strong', null, field.value || '—'));
                grid.appendChild(box);
            });
            body.appendChild(grid);
        }

        var items = proposal.items || [];
        if (items.length) {
            body.appendChild(el('div', 'assistant-os-block-title', 'Itens'));
            var list = el('ul', 'assistant-proposal-items');
            items.forEach(function (item) {
                var li = document.createElement('li');
                li.appendChild(el('span', null, item.description));
                var right = 'x' + (item.quantity || 1);
                if (item.unit_price !== undefined && item.unit_price !== null) {
                    right += ' · ' + money(item.unit_price);
                }
                li.appendChild(el('span', null, right));
                list.appendChild(li);
            });
            body.appendChild(list);
        }

        var warnings = proposal.warnings || [];
        if (warnings.length) {
            var warnList = el('ul', 'assistant-warnings');
            warnings.forEach(function (warning) {
                var li = document.createElement('li');
                li.appendChild(document.createTextNode('⚠ '));
                li.appendChild(document.createTextNode(warning));
                warnList.appendChild(li);
            });
            body.appendChild(warnList);
        }

        card.appendChild(body);

        var foot = el('div', 'assistant-proposal-foot');
        var note = el('span', 'assistant-note');

        if (proposal.status === 'pending') {
            note.textContent = 'Nada foi gravado. Aprovação necessária.';
            var approve = el('button', 'assistant-btn is-primary');
            approve.type = 'button';
            approve.appendChild(document.createTextNode(proposal.approve_label || 'Aprovar'));
            approve.addEventListener('click', function () {
                decide(proposal.id, 'apply', card, note);
            });

            var reject = el('button', 'assistant-btn is-danger', 'Rejeitar');
            reject.type = 'button';
            reject.addEventListener('click', function () {
                decide(proposal.id, 'reject', card, note);
            });

            foot.appendChild(note);
            foot.appendChild(reject);
            foot.appendChild(approve);
        } else {
            if (proposal.status === 'applied' && proposal.result && proposal.result.message) {
                note.textContent = proposal.result.message;
            } else if (proposal.status === 'failed' && proposal.error) {
                note.textContent = 'Falhou: ' + proposal.error;
            } else {
                note.textContent = 'Proposta ' + proposalStatusLabel(proposal.status) + '.';
            }
            foot.appendChild(note);

            var target = proposal.result && safeUrl(proposal.result.url);
            if (target) {
                var open = el('a', 'assistant-btn', 'Abrir registro');
                open.href = target;
                foot.appendChild(open);
            }
        }

        card.appendChild(foot);

        return card;
    }

    function widgetPartsChoice(widget) {
        var shell = widgetShell(widget);
        var wrap = el('div', 'assistant-parts');

        var spec = widget.spec || {};
        var specBox = el('div', 'assistant-spec' + (spec.known ? '' : ' is-unknown'));
        var specTop = el('div', 'assistant-spec-top');
        specTop.appendChild(el('i', 'icon-base ti ' + (spec.known ? 'tabler-clipboard-check' : 'tabler-alert-triangle')));
        specTop.appendChild(el('strong', null, spec.known ? 'Ficha do veículo' : 'Ficha não cadastrada'));
        if (spec.known) {
            specTop.appendChild(el('span', 'assistant-pill ' + (spec.confidence === 'high' ? 'is-success' : (spec.confidence === 'low' ? 'is-danger' : 'is-warning')), confidenceLabel(spec.confidence)));
        }
        specBox.appendChild(specTop);
        specBox.appendChild(el('div', 'assistant-spec-value', spec.label || '—'));
        if (spec.source) {
            specBox.appendChild(el('small', null, 'fonte: ' + spec.source));
        }
        wrap.appendChild(specBox);

        var list = el('div', 'assistant-offers');
        (widget.offers || []).forEach(function (offer) {
            var card = el('div', 'assistant-offer');

            var head = el('div', 'assistant-offer-head');
            head.appendChild(el('span', 'assistant-offer-source', offer.source));
            if (offer.fitment) {
                head.appendChild(el('span', 'assistant-pill ' + fitmentClass(offer.fitment), offer.fitment));
            }
            card.appendChild(head);

            card.appendChild(el('div', 'assistant-offer-title', offer.title));

            var meta = [offer.brand, offer.code].filter(Boolean).join(' · ');
            if (meta) {
                card.appendChild(el('div', 'assistant-offer-meta', meta));
            }

            var foot = el('div', 'assistant-offer-foot');
            foot.appendChild(el('strong', 'assistant-offer-price', offer.price !== null && offer.price !== undefined ? money(offer.price) : 'sob consulta'));

            if (offer.stock !== null && offer.stock !== undefined) {
                foot.appendChild(el('span', 'assistant-offer-stock', offer.stock > 0 ? offer.stock + ' em estoque' : 'sem estoque'));
            }

            var actions = el('div', 'assistant-offer-actions');
            var link = offer.url && safeUrl(offer.url);
            if (link) {
                var anchor = el('a', 'assistant-btn', 'conferir');
                anchor.href = link;
                anchor.target = '_blank';
                anchor.rel = 'noopener';
                actions.appendChild(anchor);
            }

            if (offer.token && widget.can_select) {
                var use = el('button', 'assistant-btn is-primary', 'Usar esta peça');
                use.type = 'button';
                use.addEventListener('click', function () {
                    chooseOffer(offer, use, shell.card);
                });
                actions.appendChild(use);
            }

            foot.appendChild(actions);
            card.appendChild(foot);
            list.appendChild(card);
        });

        if (!(widget.offers || []).length) {
            list.appendChild(el('div', 'assistant-empty', 'Nenhuma oferta compatível encontrada.'));
        }
        wrap.appendChild(list);

        if (!widget.can_select) {
            wrap.appendChild(el('div', 'assistant-note-line', 'Informe a OS para transformar a escolha em pedido de peças.'));
        }

        (widget.notes || []).forEach(function (note) {
            wrap.appendChild(el('div', 'assistant-note-line', note));
        });

        var links = el('div', 'assistant-offer-links');
        (widget.links || []).forEach(function (item) {
            var url = safeUrl(item.url);
            if (!url) {
                return;
            }
            var a = el('a', 'assistant-link-chip');
            a.href = url;
            a.target = '_blank';
            a.rel = 'noopener';
            a.appendChild(el('i', 'icon-base ti tabler-external-link'));
            a.appendChild(document.createTextNode(item.label));
            links.appendChild(a);
        });
        if (links.childNodes.length) {
            wrap.appendChild(links);
        }

        shell.body.appendChild(wrap);
        return shell.card;
    }

    function chooseOffer(offer, button, cardEl) {
        var body = new URLSearchParams();
        body.set('csrf_token', boot.csrf);
        body.set('session_id', String(state.sessionId));
        body.set('token', offer.token);

        button.disabled = true;
        button.textContent = 'Adicionando…';

        fetch('/assistant/parts/select', {
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
                throw new Error(result.data.error || 'Não foi possível adicionar a peça.');
            }

            var proposal = result.data.proposal;
            state.proposalIndex[proposal.id] = { status: 'pending' };

            var container = cardEl.closest('.assistant-widgets') || log;
            var node = renderWidget({ type: 'proposal', proposal: proposal });
            if (node) {
                container.appendChild(node);
                node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            button.textContent = 'Adicionado';
            button.classList.remove('is-primary');
            toast(result.data.message || 'Peça adicionada à proposta.', 'success');
            refreshPending();
        }).catch(function (error) {
            button.disabled = false;
            button.textContent = 'Usar esta peça';
            toast(error.message || 'Falha ao adicionar a peça.', 'error');
        });
    }

    function widgetSpec(widget) {
        var shell = widgetShell(widget);
        var wrap = el('div', 'assistant-spec-detail');
        var vehicle = widget.vehicle || {};

        var title = [vehicle.brand, vehicle.model, vehicle.year].filter(Boolean).join(' ');
        wrap.appendChild(el('div', 'assistant-os-number', title + (vehicle.plate ? ' · ' + vehicle.plate : '')));

        if (widget.known) {
            wrap.appendChild(el('div', 'assistant-spec-value', widget.label || '—'));
        } else {
            wrap.appendChild(el('div', 'assistant-note-line', 'Não há ficha cadastrada. Confirme a especificação no manual antes de solicitar.'));
        }

        if (widget.source) {
            wrap.appendChild(el('small', 'assistant-spec-source', 'fonte: ' + widget.source + ' · ' + confidenceLabel(widget.confidence)));
        }

        if ((widget.codes || []).length) {
            var table = el('table', 'assistant-table');
            var tbody = document.createElement('tbody');
            widget.codes.forEach(function (row) {
                var tr = document.createElement('tr');
                tr.appendChild(el('td', null, row.label));
                tr.appendChild(el('td', null, row.value));
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            var tableWrap = el('div', 'assistant-table-wrap');
            tableWrap.appendChild(table);
            wrap.appendChild(tableWrap);
        }

        if (widget.notes) {
            wrap.appendChild(el('div', 'assistant-note-line', widget.notes));
        }

        shell.body.appendChild(wrap);
        return shell.card;
    }

    function supersedeSiblings(intent, keepId) {
        var selector = '.assistant-proposal.is-pending[data-intent="' + intent.replace(/"/g, '\\"') + '"]';
        document.querySelectorAll(selector).forEach(function (other) {
            if (other.dataset.proposalId === String(keepId)) {
                return;
            }

            other.classList.remove('is-pending');
            other.classList.add('is-superseded');

            var badge = other.querySelector('.assistant-proposal-head .assistant-pill');
            if (badge) {
                badge.textContent = 'substituída';
                badge.className = 'assistant-pill';
            }

            var foot = other.querySelector('.assistant-proposal-foot');
            if (foot) {
                foot.innerHTML = '';
                foot.appendChild(el('span', 'assistant-note', 'Substituída por uma proposta mais recente.'));
            }
        });
    }

    function renderWidget(widget) {
        switch (widget && widget.type) {
            case 'table':
                return widgetTable(widget);
            case 'kpi':
                return widgetKpi(widget);
            case 'bars':
                return widgetBars(widget);
            case 'os_detail':
                return widgetOsDetail(widget);
            case 'proposal':
                return widgetProposal(widget);
            case 'parts_choice':
                return widgetPartsChoice(widget);
            case 'spec':
                return widgetSpec(widget);
            case 'empty':
                return widgetEmpty(widget);
            default:
                return null;
        }
    }

    /* ============================ MENSAGENS ============================ */

    function scrollToBottom(force) {
        if (!log) {
            return;
        }
        var nearBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 160;
        if (force || nearBottom) {
            log.scrollTop = log.scrollHeight;
        }
    }

    function messageShell(role) {
        var msg = el('div', 'assistant-msg is-' + role);
        var avatar = el('div', 'assistant-msg-avatar', role === 'user' ? initials(boot.userName) : 'AI');
        var body = el('div', 'assistant-msg-body');
        var head = el('div', 'assistant-msg-head');
        head.appendChild(el('strong', null, role === 'user' ? 'Você' : 'Assistente'));
        head.appendChild(el('span', null, timeLabel(new Date().toISOString())));
        body.appendChild(head);

        msg.appendChild(avatar);
        msg.appendChild(body);
        log.appendChild(msg);

        return { root: msg, body: body, head: head };
    }

    function initials(name) {
        var parts = String(name || '?').trim().split(/\s+/).slice(0, 2);
        return parts.map(function (part) {
            return part.charAt(0).toUpperCase();
        }).join('') || '?';
    }

    function addUserMessage(text, attachments) {
        var shell = messageShell('user');
        var md = el('div', 'assistant-md');
        md.appendChild(renderMarkdown(text || ''));
        shell.body.appendChild(md);

        if (attachments && attachments.length) {
            shell.body.appendChild(attachmentChips(attachments));
        }

        scrollToBottom(true);

        return shell;
    }

    function addAssistantMessage() {
        var shell = messageShell('assistant');
        var reasoning = null;

        if (boot.canReasoning) {
            reasoning = buildReasoningPanel();
            shell.body.appendChild(reasoning.root);
        }

        var notices = el('div', 'assistant-notices');
        var tools = el('div', 'assistant-tools');
        var md = el('div', 'assistant-md assistant-cursor');
        var widgets = el('div', 'assistant-widgets');
        var meta = el('div', 'assistant-msg-meta');

        shell.body.appendChild(notices);
        shell.body.appendChild(tools);
        shell.body.appendChild(md);
        shell.body.appendChild(widgets);
        shell.body.appendChild(meta);

        scrollToBottom(true);

        return {
            shell: shell,
            reasoning: reasoning,
            notices: notices,
            tools: tools,
            md: md,
            widgets: widgets,
            meta: meta,
            text: '',
            reasoningText: '',
            renders: 0,
            toolsCount: 0,
            modelLabel: '',
            usage: null,
            cost: 0
        };
    }

    function buildReasoningPanel() {
        var root = el('div', 'assistant-reasoning');
        root.hidden = true;

        var head = el('button', 'assistant-reasoning-head');
        head.type = 'button';
        head.appendChild(el('i', 'icon-base ti tabler-brain'));
        head.appendChild(el('span', 'assistant-reasoning-label', 'Pensando…'));
        head.appendChild(el('span', 'assistant-reasoning-chevron', '▾'));

        var body = el('div', 'assistant-reasoning-body');
        body.hidden = false;

        head.addEventListener('click', function () {
            body.hidden = !body.hidden;
            head.classList.toggle('is-collapsed', body.hidden);
        });

        root.appendChild(head);
        root.appendChild(body);

        return { root: root, head: head, body: body, label: head.querySelector('.assistant-reasoning-label'), start: 0 };
    }

    function addToolRow(container, name, label) {
        var row = el('div', 'assistant-tool is-running');
        var head = el('button', 'assistant-tool-head');
        head.type = 'button';

        var spinner = el('span', 'assistant-tool-spinner');
        head.appendChild(spinner);
        head.appendChild(el('span', null, label || name));

        var detail = el('div', 'assistant-tool-detail');
        detail.hidden = true;

        head.addEventListener('click', function () {
            detail.hidden = !detail.hidden;
        });

        row.appendChild(head);
        row.appendChild(detail);
        container.appendChild(row);

        return { row: row, head: head, detail: detail, spinner: spinner };
    }

    function finishToolRow(tool, payload) {
        tool.row.classList.remove('is-running');
        tool.row.classList.toggle('is-error', !payload.ok);
        tool.spinner.remove();

        var icon = el('i', 'icon-base ti ' + (payload.ok ? 'tabler-check' : 'tabler-alert-triangle'));
        tool.head.insertBefore(icon, tool.head.firstChild);

        if (payload.summary) {
            tool.detail.textContent = payload.summary;
        }
    }

    function addNotice(current, message) {
        if (!message || !current.notices) {
            return;
        }
        var notice = el('div', 'assistant-notice');
        notice.appendChild(el('i', 'icon-base ti tabler-info-circle'));
        notice.appendChild(el('span', null, message));
        current.notices.appendChild(notice);
    }

    function renderStaticAssistant(message) {
        var meta = message.meta || {};
        var shell = messageShell('assistant');

        if (boot.canReasoning && meta.reasoning) {
            var panel = buildReasoningPanel();
            panel.root.hidden = false;
            panel.label.textContent = 'Raciocínio';
            panel.body.textContent = meta.reasoning;
            panel.body.hidden = true;
            panel.head.classList.add('is-collapsed');
            shell.body.appendChild(panel.root);
        }

        var toolsData = meta.tools || [];
        if (toolsData.length) {
            var toolsBox = el('div', 'assistant-tools');
            toolsData.forEach(function (tool) {
                var row = el('div', 'assistant-tool' + (tool.ok === false ? ' is-error' : ''));
                var head = el('div', 'assistant-tool-head');
                head.appendChild(el('i', 'icon-base ti ' + (tool.ok === false ? 'tabler-alert-triangle' : 'tabler-check')));
                head.appendChild(el('span', null, tool.label || tool.name));
                row.appendChild(head);
                toolsBox.appendChild(row);
            });
            shell.body.appendChild(toolsBox);
        }

        var md = el('div', 'assistant-md');
        md.appendChild(renderMarkdown(message.content || ''));
        shell.body.appendChild(md);

        var renders = meta.renders || [];
        if (renders.length) {
            var widgets = el('div', 'assistant-widgets');
            renders.forEach(function (widget) {
                var node = renderWidget(widget);
                if (node) {
                    widgets.appendChild(node);
                }
            });
            shell.body.appendChild(widgets);
        }

        var metaBox = el('div', 'assistant-msg-meta');

        var copy = el('button', 'assistant-copy', 'copiar');
        copy.type = 'button';
        copy.addEventListener('click', function () {
            copyText(message.content || '');
        });
        metaBox.appendChild(copy);
        shell.body.appendChild(metaBox);

        scrollToBottom(true);
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                toast('Copiado.', 'success');
            }).catch(function () {
                toast('Não foi possível copiar.', 'error');
            });
            return;
        }
        var area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        try {
            document.execCommand('copy');
            toast('Copiado.', 'success');
        } catch (e) {
            toast('Não foi possível copiar.', 'error');
        }
        area.remove();
    }

    function renderEmptyState() {
        var wrap = el('div', 'assistant-empty-state');
        var orb = el('div', 'assistant-empty-orb');
        orb.appendChild(el('i', 'icon-base ti tabler-sparkles'));
        wrap.appendChild(orb);
        wrap.appendChild(el('h2', null, 'Como posso ajudar?'));
        wrap.appendChild(el('p', null, 'Pergunte sobre clientes, veículos, OS e peças — ou prepare uma alteração.'));

        var grid = el('div', 'assistant-suggestions');
        suggestions().forEach(function (item) {
            var button = el('button', 'assistant-suggestion');
            button.type = 'button';
            button.appendChild(el('span', 'assistant-suggestion-label', item.label));
            button.addEventListener('click', function () {
                input.value = item.prompt;
                resizeInput();
                input.focus();
                if (item.send) {
                    submit();
                }
            });
            grid.appendChild(button);
        });
        wrap.appendChild(grid);
        log.appendChild(wrap);
    }

    function suggestions() {
        var items = [
            { label: 'Resumo do dia', icon: 'tabler-chart-histogram', prompt: 'Me dê o resumo operacional do dia.', send: true },
            { label: 'OS aguardando peça', icon: 'tabler-clock-pause', prompt: 'Quais ordens de serviço estão aguardando peça?', send: true }
        ];

        if (['admin', 'secretary', 'lead_mechanic'].indexOf(boot.role) !== -1) {
            items.push({ label: 'Buscar cliente', icon: 'tabler-user-search', prompt: 'Buscar cliente: ', send: false });
            items.push({ label: 'Abrir uma OS', icon: 'tabler-clipboard-plus', prompt: 'Quero abrir uma OS para o cliente ', send: false });
        } else {
            items.push({ label: 'Minhas OS', icon: 'tabler-clipboard-list', prompt: 'Quais são as minhas ordens de serviço em andamento?', send: true });
            items.push({ label: 'Solicitar peça', icon: 'tabler-truck', prompt: 'Quero solicitar peças para a OS ', send: false });
        }

        return items;
    }

    /* ============================ PROPOSTAS ============================ */

    function decide(id, action, card, note) {
        var buttons = card.querySelectorAll('button');
        buttons.forEach(function (button) {
            button.disabled = true;
        });
        note.textContent = action === 'apply' ? 'Aplicando…' : 'Rejeitando…';

        var body = new URLSearchParams();
        body.set('csrf_token', boot.csrf);

        fetch('/assistant/proposals/' + id + '/' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        }).then(function (result) {
            if (!result.ok || result.data.ok === false) {
                throw new Error(result.data.error || 'Não foi possível concluir.');
            }

            var status = action === 'apply' ? 'applied' : 'rejected';
            state.proposalIndex[id] = state.proposalIndex[id] || {};
            state.proposalIndex[id].status = status;
            state.proposalIndex[id].result = result.data;
            state.proposalIndex[id].error = null;

            card.className = 'assistant-proposal is-' + status;
            var badge = card.querySelector('.assistant-proposal-head .assistant-pill');
            if (badge) {
                badge.textContent = proposalStatusLabel(status);
                badge.className = 'assistant-pill ' + (status === 'applied' ? 'is-success' : 'is-danger');
            }

            var foot = card.querySelector('.assistant-proposal-foot');
            if (foot) {
                foot.innerHTML = '';
                foot.appendChild(el('span', 'assistant-note', result.data.message || 'Concluído.'));

                var target = result.data.url && safeUrl(result.data.url);
                if (target) {
                    var link = el('a', 'assistant-btn', 'Abrir registro');
                    link.href = target;
                    foot.appendChild(link);
                }
            }

            toast(result.data.message || 'Concluído.', 'success');
            refreshPending();
        }).catch(function (error) {
            note.textContent = error.message || 'Falha ao concluir.';
            note.style.color = 'var(--bs-danger)';
            buttons.forEach(function (button) {
                button.disabled = false;
            });
            toast(error.message || 'Falha ao concluir.', 'error');
        });
    }

    function refreshPending() {
        fetch(boot.proposalsUrl, { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                renderPending((data && data.items) || []);
            })
            .catch(function () {});
    }

    function renderPending(items) {
        if (!pendingBox) {
            return;
        }
        pendingBox.innerHTML = '';

        if (!items.length) {
            pendingBox.appendChild(el('div', 'assistant-pending-empty', 'Nada aguardando aprovação.'));
        } else {
            items.forEach(function (proposal) {
                var preview = proposal.preview || {};
                var link = el('a', 'assistant-proposal-compact');
                link.href = '/assistant?s=' + (proposal.session_id || state.sessionId) + '#proposal-' + proposal.id;

                var title = el('strong', null, preview.title || 'Proposta');
                var sub = el('small', null, preview.subtitle || '');
                link.appendChild(title);
                link.appendChild(sub);

                link.addEventListener('click', function (event) {
                    var target = document.getElementById('proposal-' + proposal.id);
                    if (target) {
                        event.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        target.animate(
                            [{ boxShadow: '0 0 0 0 rgba(0,0,0,0)' }, { boxShadow: '0 0 0 4px rgba(0,0,0,0.12)' }, { boxShadow: '0 0 0 0 rgba(0,0,0,0)' }],
                            { duration: 1200 }
                        );
                    }
                });

                pendingBox.appendChild(link);
            });
        }

        if (pendingCount) {
            pendingCount.textContent = String(items.length);
        }
    }

    function updateUsage(turns) {
        var used = Number(boot.limits.turns_today || 0) + turns;
        boot.limits.turns_today = used;
        if (usageEl) {
            usageEl.textContent = used + '/' + (boot.limits.day || 200);
        }
        if (usageBar && boot.limits.day) {
            usageBar.style.width = Math.min(100, (used / boot.limits.day) * 100) + '%';
        }
    }

    /* ============================ STREAMING ============================ */

    function submit() {
        var text = input.value.trim();
        if ((!text && !state.attachments.length) || state.streaming) {
            return;
        }
        if (state.uploading > 0) {
            toast('Aguarde o envio dos anexos terminar.', 'info');
            return;
        }

        var slash = matchSlash(text);
        if (slash && slash.action === 'new') {
            input.value = '';
            resizeInput();
            startNewSession();
            return;
        }
        if (slash && slash.prompt) {
            text = typeof slash.prompt === 'function' ? slash.prompt(slash.arg) : slash.prompt;
            if (!text) {
                return;
            }
        }

        if (!boot.enabled) {
            toast('Configure LLM_API_KEY no .env para usar o assistente.', 'error');
            return;
        }

        var empty = log.querySelector('.assistant-empty-state');
        if (empty) {
            empty.remove();
        }

        var sentAttachments = state.attachments.slice();
        state.attachments = [];
        renderAttachmentTray();

        input.value = '';
        resizeInput();
        addUserMessage(text, sentAttachments);
        titleCurrentSession(text);

        var assistant = addAssistantMessage();
        state.current = assistant;
        state.streaming = true;
        state.controller = new AbortController();

        sendBtn.disabled = true;
        stopBtn.hidden = false;
        setStatus('live', 'Pensando…');

        var body = new URLSearchParams();
        body.set('csrf_token', boot.csrf);
        body.set('session_id', String(state.sessionId));
        body.set('message', text);
        body.set('model', state.model);
        body.set('effort', state.effort);
        body.set('attachments', sentAttachments.map(function (attachment) {
            return attachment.id;
        }).filter(Boolean).join(','));

        fetch(boot.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin',
            signal: state.controller.signal
        }).then(function (response) {
            if (!response.ok) {
                return response.json().catch(function () {
                    return {};
                }).then(function (data) {
                    throw new Error(data.error || 'Falha ao iniciar o assistente.');
                });
            }
            if (!response.body) {
                throw new Error('Seu navegador não suporta streaming.');
            }

            var reader = response.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';

            function pump() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        return;
                    }

                    buffer += decoder.decode(result.value, { stream: true });

                    var index;
                    while ((index = buffer.indexOf('\n\n')) !== -1) {
                        handleFrame(buffer.slice(0, index));
                        buffer = buffer.slice(index + 2);
                    }

                    return pump();
                });
            }

            return pump();
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                finalize('Interrompido.');
                return;
            }
            fail(error && error.message ? error.message : 'Falha de conexão.');
        }).then(function () {
            if (state.streaming) {
                finalize();
            }
        });
    }

    function handleFrame(raw) {
        var event = 'message';
        var data = '';

        raw.split('\n').forEach(function (line) {
            if (line.indexOf('event:') === 0) {
                event = line.slice(6).trim();
            } else if (line.indexOf('data:') === 0) {
                data += line.slice(5).trim();
            }
        });

        if (!data) {
            return;
        }

        var payload;
        try {
            payload = JSON.parse(data);
        } catch (e) {
            return;
        }

        dispatch(event, payload);
    }

    function dispatch(event, payload) {
        var current = state.current;
        if (!current) {
            return;
        }

        if (event === 'notice') {
            addNotice(current, payload.message);
            return;
        }

        if (event === 'reasoning_start') {
            if (current.reasoning) {
                current.reasoning.root.hidden = false;
                current.reasoning.start = Date.now();
                current.reasoning.label.textContent = 'Pensando…';
                current.reasoning.root.classList.add('is-live');
            }
            return;
        }

        if (event === 'reasoning_delta') {
            if (current.reasoning) {
                current.reasoningText += payload.text || '';
                current.reasoning.body.textContent = current.reasoningText;
                scrollToBottom(false);
            }
            return;
        }

        if (event === 'reasoning_end') {
            if (current.reasoning) {
                current.reasoning.root.classList.remove('is-live');
                var seconds = payload.ms ? (payload.ms / 1000).toFixed(1).replace('.', ',') : null;
                current.reasoning.label.textContent = seconds
                    ? 'Pensou por ' + seconds + 's'
                    : 'Raciocínio';
            }
            return;
        }

        if (event === 'start') {
            if (payload.model_label) {
                current.modelLabel = payload.model_label;
            }
            return;
        }

        if (event === 'delta') {
            if (current.reasoning && !current.reasoning.body.hidden && !current.answerStarted) {
                current.answerStarted = true;
                current.reasoning.body.hidden = true;
                current.reasoning.head.classList.add('is-collapsed');
            }
            current.text += payload.text || '';
            scheduleMarkdown(current);
            return;
        }

        if (event === 'tool_start') {
            current.toolsCount++;
            current.tool = addToolRow(current.tools, payload.name, payload.label);
            setStatus('live', payload.label || 'Executando…');
            scrollToBottom(false);
            return;
        }

        if (event === 'tool_end') {
            if (current.tool) {
                finishToolRow(current.tool, payload);
            }
            scrollToBottom(false);
            return;
        }

        if (event === 'render') {
            if (payload.widget && payload.widget.type !== 'proposal') {
                current.renders++;
                var node = renderWidget(payload.widget);
                if (node) {
                    current.widgets.appendChild(node);
                }
            } else if (payload.widget) {
                current.renders++;
                var proposal = renderWidget(payload.widget);
                if (proposal) {
                    current.widgets.appendChild(proposal);
                }
                refreshPending();
            }
            scrollToBottom(false);
            return;
        }

        if (event === 'done') {
            current.usage = payload.usage || null;
            current.cost = payload.cost || 0;
            if (payload.model_label) {
                current.modelLabel = payload.model_label;
            }
            return;
        }

        if (event === 'error') {
            fail(payload.message || 'Erro ao falar com o provedor de IA.');
        }
    }

    var markdownFrame = null;

    function scheduleMarkdown(current) {
        if (markdownFrame) {
            return;
        }
        markdownFrame = requestAnimationFrame(function () {
            markdownFrame = null;
            current.md.innerHTML = '';
            current.md.appendChild(renderMarkdown(current.text));
            scrollToBottom(false);
        });
    }

    function finalize(note) {
        if (!state.current) {
            return;
        }

        var current = state.current;
        current.md.classList.remove('assistant-cursor');

        if (note) {
            current.md.appendChild(el('p', null, note));
        }

        if (!current.text && !current.renders) {
            current.md.appendChild(el('p', null, 'Não recebi resposta. Tente novamente.'));
        }

        var copy = el('button', 'assistant-copy', 'copiar');
        copy.type = 'button';
        copy.addEventListener('click', function () {
            copyText(current.text);
        });
        current.meta.appendChild(copy);

        state.streaming = false;
        state.current = null;
        state.controller = null;
        updateSendState();
        stopBtn.hidden = true;
        updateUsage(1);
        setStatus('idle', 'Pergunte sobre clientes, veículos, OS, peças e relatórios.');
        input.focus();
        scrollToBottom(false);
    }

    function fail(message) {
        if (state.current) {
            state.current.md.classList.remove('assistant-cursor');
            var box = el('div', 'assistant-tool is-error');
            var head = el('div', 'assistant-tool-head');
            head.appendChild(el('i', 'icon-base ti tabler-alert-triangle'));
            head.appendChild(el('span', null, message));
            box.appendChild(head);
            state.current.shell.body.insertBefore(box, state.current.md);
        }

        setStatus('error', message);
        state.streaming = false;
        state.controller = null;
        updateSendState();
        stopBtn.hidden = true;
        toast(message, 'error');
        state.current = null;
    }

    /* ============================ SLASH ============================ */

    var COMMANDS = [
        { cmd: '/resumo', desc: 'Resumo operacional do dia', prompt: 'Me dê o resumo operacional do dia.' },
        { cmd: '/os', desc: 'Buscar OS por número, placa ou cliente', prompt: function (arg) { return arg ? 'Buscar OS: ' + arg : 'Buscar OS: '; } },
        { cmd: '/cliente', desc: 'Buscar cliente', roles: ['admin', 'secretary', 'lead_mechanic'], prompt: function (arg) { return arg ? 'Buscar cliente: ' + arg : 'Buscar cliente: '; } },
        { cmd: '/peca', desc: 'Buscar peça no catálogo', roles: ['admin', 'secretary', 'lead_mechanic'], prompt: function (arg) { return arg ? 'Buscar peça: ' + arg : 'Buscar peça: '; } },
        { cmd: '/abrir-os', desc: 'Preparar proposta de abertura de OS', roles: ['admin', 'secretary', 'lead_mechanic'], prompt: function (arg) { return 'Quero abrir uma OS para o cliente ' + (arg || ''); } },
        { cmd: '/nova', desc: 'Começar uma nova conversa', action: 'new' },
        { cmd: '/ajuda', desc: 'O que o assistente sabe fazer', prompt: 'O que você pode fazer neste sistema? Seja específico sobre as ferramentas disponíveis para o meu perfil.' }
    ];

    function availableCommands() {
        return COMMANDS.filter(function (command) {
            return !command.roles || command.roles.indexOf(boot.role) !== -1;
        });
    }

    function matchSlash(text) {
        if (text.charAt(0) !== '/') {
            return null;
        }
        var parts = text.split(/\s+/);
        var command = availableCommands().filter(function (item) {
            return item.cmd === parts[0];
        })[0];

        if (!command) {
            return null;
        }

        return {
            action: command.action,
            prompt: command.prompt,
            arg: text.slice(parts[0].length).trim()
        };
    }

    function updateSlash() {
        var value = input.value;

        if (value.charAt(0) !== '/' || value.indexOf(' ') !== -1) {
            hideSlash();
            return;
        }

        var items = availableCommands().filter(function (command) {
            return command.cmd.indexOf(value) === 0;
        });

        if (!items.length) {
            hideSlash();
            return;
        }

        state.slashItems = items;
        state.slashIndex = Math.min(state.slashIndex, items.length - 1);

        slashBox.innerHTML = '';
        items.forEach(function (command, index) {
            var button = el('button', 'assistant-slash-item' + (index === state.slashIndex ? ' is-active' : ''));
            button.type = 'button';
            button.appendChild(el('span', 'assistant-slash-cmd', command.cmd));
            button.appendChild(el('span', 'assistant-slash-desc', command.desc));
            button.addEventListener('mousedown', function (event) {
                event.preventDefault();
                applySlash(command);
            });
            slashBox.appendChild(button);
        });

        slashBox.hidden = false;
    }

    function hideSlash() {
        slashBox.hidden = true;
        slashBox.innerHTML = '';
        state.slashItems = [];
        state.slashIndex = 0;
    }

    function applySlash(command) {
        if (command.action === 'new') {
            input.value = '';
            hideSlash();
            startNewSession();
            return;
        }
        input.value = command.cmd + ' ';
        hideSlash();
        resizeInput();
        input.focus();
    }

    /* ============================ AÇÕES ============================ */

    function resizeInput() {
        input.style.height = 'auto';
        input.style.height = Math.min(180, input.scrollHeight) + 'px';
    }

    function startNewSession() {
        var body = new URLSearchParams();
        body.set('csrf_token', boot.csrf);

        fetch(boot.newSessionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data && data.session_id) {
                window.location.href = '/assistant?s=' + data.session_id;
            }
        }).catch(function () {
            toast('Não foi possível criar a conversa.', 'error');
        });
    }

    /* ============================ EXCLUSÃO ============================ */

    var confirmEl = document.getElementById('assistantConfirm');
    var confirmTitleEl = document.getElementById('assistantConfirmTitle');
    var confirmTextEl = document.getElementById('assistantConfirmText');
    var confirmOkEl = document.getElementById('assistantConfirmOk');
    var confirmInstance = (window.bootstrap && confirmEl)
        ? window.bootstrap.Modal.getOrCreateInstance(confirmEl)
        : null;
    var confirmAction = null;

    function askConfirm(options) {
        if (!confirmInstance) {
            if (window.confirm(options.text)) {
                options.onOk();
            }
            return;
        }

        confirmTitleEl.textContent = options.title || 'Confirmar';
        confirmTextEl.textContent = options.text || '';
        confirmOkEl.textContent = options.ok || 'Confirmar';
        confirmAction = options.onOk;
        confirmInstance.show();
    }

    if (confirmOkEl) {
        confirmOkEl.addEventListener('click', function () {
            var action = confirmAction;
            confirmAction = null;
            if (confirmInstance) {
                confirmInstance.hide();
            }
            if (action) {
                action();
            }
        });
    }

    if (confirmEl) {
        confirmEl.addEventListener('hidden.bs.modal', function () {
            confirmAction = null;
        });
    }

    function postSessionAction(url) {
        var body = new URLSearchParams();
        body.set('csrf_token', boot.csrf);

        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || data.ok === false) {
                    throw new Error(data.error || 'Não foi possível excluir.');
                }
                return data;
            });
        });
    }

    function goToSession(sessionId) {
        window.location.href = sessionId ? '/assistant?s=' + sessionId : '/assistant';
    }

    function deleteSession(id, title) {
        if (!id) {
            return;
        }

        askConfirm({
            title: 'Excluir conversa',
            text: 'Excluir "' + (title || 'esta conversa') + '"? As mensagens e os anexos serão removidos.',
            ok: 'Excluir',
            onOk: function () {
                postSessionAction('/assistant/sessions/' + id + '/delete')
                    .then(function (data) {
                        goToSession(data.session_id || 0);
                    })
                    .catch(function (error) {
                        toast(error.message || 'Falha ao excluir a conversa.', 'error');
                    });
            }
        });
    }

    function deleteAllSessions() {
        askConfirm({
            title: 'Excluir todas as conversas',
            text: 'Isso remove todas as suas conversas, mensagens e anexos. Não dá para desfazer.',
            ok: 'Excluir tudo',
            onOk: function () {
                postSessionAction(boot.deleteAllUrl)
                    .then(function (data) {
                        goToSession(data.session_id || 0);
                    })
                    .catch(function (error) {
                        toast(error.message || 'Falha ao excluir as conversas.', 'error');
                    });
            }
        });
    }

    /* ============================ EVENTOS ============================ */

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        submit();
    });

    input.addEventListener('keydown', function (event) {
        if (!slashBox.hidden) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                var delta = event.key === 'ArrowDown' ? 1 : -1;
                state.slashIndex = (state.slashIndex + delta + state.slashItems.length) % state.slashItems.length;
                updateSlash();
                return;
            }
            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                if (state.slashItems[state.slashIndex]) {
                    applySlash(state.slashItems[state.slashIndex]);
                }
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                hideSlash();
                return;
            }
        }

        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }

        if (event.key === 'Escape' && state.streaming) {
            event.preventDefault();
            stop();
        }
    });

    input.addEventListener('input', function () {
        resizeInput();
        updateSlash();
    });

    input.addEventListener('blur', function () {
        setTimeout(hideSlash, 120);
    });

    stopBtn.addEventListener('click', stop);

    function stop() {
        if (state.controller) {
            state.controller.abort();
        }
    }

    if (newBtn) {
        newBtn.addEventListener('click', startNewSession);
    }

    var newMobileBtn = document.getElementById('assistantNewMobile');
    if (newMobileBtn) {
        newMobileBtn.addEventListener('click', startNewSession);
    }

    var deleteAllBtn = document.getElementById('assistantDeleteAll');
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', deleteAllSessions);
    }

    document.querySelectorAll('[data-session-delete]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            deleteSession(
                parseInt(button.getAttribute('data-session-delete'), 10),
                button.getAttribute('data-session-title') || ''
            );
        });
    });

    /* ============================ RAIL ============================ */

    var shellEl = document.getElementById('assistantShell');
    var railToggleBtn = document.getElementById('assistantRailToggle');
    var railOpenBtn = document.getElementById('assistantRailOpen');
    var railBackdrop = document.getElementById('assistantRailBackdrop');
    var RAIL_KEY = 'mecanica-assistant-rail';

    function isMobileRail() {
        return window.matchMedia('(max-width: 991.98px)').matches;
    }

    function setRailCollapsed(collapsed) {
        if (!shellEl || isMobileRail()) {
            return;
        }
        shellEl.classList.toggle('is-collapsed', collapsed);
        if (railToggleBtn) {
            railToggleBtn.title = collapsed ? 'Expandir histórico' : 'Recolher histórico';
            railToggleBtn.setAttribute('aria-label', railToggleBtn.title);
        }
        try {
            localStorage.setItem(RAIL_KEY, collapsed ? '1' : '0');
        } catch (e) {}
    }

    function toggleMobileRail(open) {
        if (!shellEl) {
            return;
        }
        shellEl.classList.toggle('is-rail-open', open);
        if (railBackdrop) {
            railBackdrop.hidden = !open;
        }
    }

    if (shellEl) {
        var savedRail = null;
        try {
            savedRail = localStorage.getItem(RAIL_KEY);
        } catch (e) {}
        if (savedRail === '1' && !isMobileRail()) {
            setRailCollapsed(true);
        }
    }

    if (railToggleBtn) {
        railToggleBtn.addEventListener('click', function () {
            if (isMobileRail()) {
                toggleMobileRail(false);
                return;
            }
            setRailCollapsed(!shellEl.classList.contains('is-collapsed'));
        });
    }

    if (railOpenBtn) {
        railOpenBtn.addEventListener('click', function () {
            toggleMobileRail(true);
        });
    }

    if (railBackdrop) {
        railBackdrop.addEventListener('click', function () {
            toggleMobileRail(false);
        });
    }

    window.addEventListener('resize', function () {
        if (!isMobileRail()) {
            toggleMobileRail(false);
        }
    });

    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function () {
            var lines = [];
            log.querySelectorAll('.assistant-msg').forEach(function (msg) {
                var role = msg.classList.contains('is-user') ? 'Você' : 'Assistente';
                var md = msg.querySelector('.assistant-md');
                if (md) {
                    lines.push(role + ': ' + md.innerText.trim());
                }
            });
            copyText(lines.join('\n\n'));
        });
    }

    document.addEventListener('keydown', function (event) {
        if (!(event.ctrlKey || event.metaKey)) {
            return;
        }

        // Ctrl/Cmd+K é a busca global do sistema; o chat usa Shift+K.
        if (event.shiftKey && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            startNewSession();
            return;
        }
        if (event.shiftKey && event.key.toLowerCase() === 'a') {
            event.preventDefault();
            if (fileInput) {
                fileInput.click();
            }
        }
    });

    /* ============================ INIT ============================ */

    // Modo de resposta (preferência do navegador).
    (function initModeSelection() {
        var savedMode = null;
        try {
            savedMode = localStorage.getItem('mecanica-assistant-mode');
        } catch (e) {}

        var mode = modeByKey(savedMode);

        if (!mode) {
            // Compatibilidade com a preferência antiga (modelo + esforço).
            var legacyModel = null;
            var legacyEffort = null;
            try {
                legacyModel = localStorage.getItem('mecanica-assistant-model');
                legacyEffort = localStorage.getItem('mecanica-assistant-effort');
            } catch (e) {}

            mode = modeFor(legacyModel, legacyEffort)
                || modeByKey(boot.defaultMode)
                || modes()[0];
        }

        applyMode(mode);
    })();

    if (modelBtn) {
        modelBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            if (modelMenu && modelMenu.hidden) {
                openModelMenu();
            } else {
                closeModelMenu();
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (!modelMenu || modelMenu.hidden) {
            return;
        }
        var target = event.target;
        if (target && target.closest && target.closest('#assistantModel')) {
            return;
        }
        closeModelMenu();
    });

    if (attachBtn && fileInput) {
        attachBtn.addEventListener('click', function () {
            fileInput.click();
        });
        fileInput.addEventListener('change', function () {
            uploadFiles(fileInput.files);
            fileInput.value = '';
        });
    }

    if (input) {
        input.addEventListener('paste', function (event) {
            var files = event.clipboardData && event.clipboardData.files;
            if (files && files.length) {
                event.preventDefault();
                uploadFiles(files);
            }
        });
    }

    if (composerWrap && dropEl) {
        ['dragenter', 'dragover'].forEach(function (name) {
            composerWrap.addEventListener(name, function (event) {
                if (!event.dataTransfer || !event.dataTransfer.types) {
                    return;
                }
                if (Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') === -1) {
                    return;
                }
                event.preventDefault();
                dropEl.hidden = false;
            });
        });

        composerWrap.addEventListener('dragleave', function (event) {
            if (!event.relatedTarget || !composerWrap.contains(event.relatedTarget)) {
                dropEl.hidden = true;
            }
        });

        composerWrap.addEventListener('drop', function (event) {
            if (!event.dataTransfer || !event.dataTransfer.files.length) {
                return;
            }
            event.preventDefault();
            dropEl.hidden = true;
            uploadFiles(event.dataTransfer.files);
        });
    }

    resizeInput();
    updateSendState();

    if (!boot.messages || !boot.messages.length) {
        renderEmptyState();
    } else {
        boot.messages.forEach(function (message) {
            if (message.role === 'user') {
                var userMeta = message.meta || {};
                addUserMessage(message.content, userMeta.attachments || []);
            } else if (message.role === 'assistant') {
                renderStaticAssistant(message);
            }
        });
        scrollToBottom(true);
    }

    renderPending(boot.proposals || []);

    input.focus();
})();
