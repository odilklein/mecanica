<?php
// =============================================
// ASSISTENTE — chat com streaming, reasoning,
// anexos, timeline de ferramentas e propostas.
//
// O estado inicial vai em JSON; o JS (assistant.js)
// é o único responsável por renderizar mensagens.
// =============================================

$initialMessages = [];
foreach ($messages as $message) {
    $meta = $message['meta'] ?? [];
    $initialMessages[] = [
        'id' => (int) $message['id'],
        'role' => (string) $message['role'],
        'content' => (string) ($message['content'] ?? ''),
        'meta' => is_array($meta) ? $meta : [],
        'created_at' => (string) ($message['created_at'] ?? ''),
    ];
}

$bootstrap = [
    'sessionId' => (int) $session['id'],
    'csrf' => (string) $csrf,
    'endpoint' => '/assistant/stream',
    'newSessionUrl' => '/assistant/sessions',
    'proposalsUrl' => '/assistant/proposals',
    'uploadUrl' => '/assistant/attachments',
    'enabled' => (bool) $configured,
    'mock' => (bool) $mock,
    'model' => (string) $model,
    'models' => $models,
    'canReasoning' => (bool) $canReasoning,
    'attachmentLimits' => $attachmentLimits,
    'role' => (string) ($user['role'] ?? ''),
    'userName' => (string) ($user['name'] ?? ''),
    'limits' => $limits,
    'messages' => $initialMessages,
    'proposals' => $proposals,
    'proposalIndex' => $proposalIndex,
    'tools' => $tools,
];

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<div class="assistant-page" id="assistantApp">
    <div class="assistant-shell">

        <aside class="assistant-rail">
            <div class="assistant-rail-head">
                <span class="assistant-orb" aria-hidden="true">
                    <i class="icon-base ti tabler-sparkles"></i>
                </span>
                <div>
                    <strong>Assistente</strong>
                    <span><?= e($mock ? 'modo demonstração' : $model) ?></span>
                </div>
            </div>

            <button type="button" class="assistant-new" id="assistantNew">
                <i class="icon-base ti tabler-plus"></i>
                <span class="assistant-new-label">Nova conversa</span>
                <kbd>⇧K</kbd>
            </button>

            <div class="assistant-rail-section">
                <div class="assistant-rail-title">
                    Conversas
                    <span class="assistant-rail-hint"><?= count($sessions) ?></span>
                </div>
                <div class="assistant-sessions">
                    <?php foreach ($sessions as $item): ?>
                        <?php $isCurrent = (int) $item['id'] === (int) $session['id']; ?>
                        <a class="assistant-session <?= $isCurrent ? 'is-current' : '' ?>"
                           href="/assistant?s=<?= (int) $item['id'] ?>">
                            <i class="icon-base ti tabler-message-2"></i>
                            <span class="assistant-session-text">
                                <?= e($item['title'] ?: 'Conversa #' . (int) $item['id']) ?>
                            </span>
                            <?php if ((int) ($item['message_count'] ?? 0) > 0): ?>
                                <span class="assistant-session-count"><?= (int) $item['message_count'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="assistant-rail-section assistant-rail-pending">
                <div class="assistant-rail-title">
                    Pendências
                    <span class="assistant-rail-hint" id="assistantPendingCount"><?= count($proposals) ?></span>
                </div>
                <div id="assistantPending" class="assistant-pending">
                    <div class="assistant-pending-empty">Nada aguardando aprovação.</div>
                </div>
            </div>

            <div class="assistant-rail-foot">
                <div class="assistant-meter">
                    <span>Uso hoje</span>
                    <strong id="assistantUsage">
                        <?= (int) $limits['turns_today'] ?>/<?= (int) $limits['day'] ?>
                    </strong>
                </div>
                <div class="assistant-meter-bar">
                    <span id="assistantUsageBar" style="width: <?= min(100, (int) round(((int) $limits['turns_today'] / max(1, (int) $limits['day'])) * 100)) ?>%"></span>
                </div>
                <p class="assistant-rail-note">
                    Toda alteração é uma proposta. Nada é gravado sem a sua aprovação.
                </p>
            </div>
        </aside>

        <section class="assistant-main">
            <header class="assistant-head">
                <div class="assistant-head-title">
                    <span class="assistant-status-dot" id="assistantStatusDot"></span>
                    <div>
                        <h1>Assistente</h1>
                        <p id="assistantStatusText">
                            <?= $configured
                                ? 'Pergunte sobre clientes, veículos, OS, peças e relatórios.'
                                : 'Configure GROQ_API_KEY no .env para ativar as respostas do modelo.' ?>
                        </p>
                    </div>
                </div>
                <div class="assistant-head-actions">
                    <?php if ($mock): ?>
                        <span class="assistant-chip assistant-chip-warn">demonstração</span>
                    <?php else: ?>
                        <span class="assistant-chip" id="assistantModelChip"><?= e($model) ?></span>
                    <?php endif; ?>
                    <button type="button" class="assistant-icon-btn d-lg-none" id="assistantNewMobile" title="Nova conversa">
                        <i class="icon-base ti tabler-plus"></i>
                    </button>
                    <button type="button" class="assistant-icon-btn" id="assistantCopyAll" title="Copiar conversa">
                        <i class="icon-base ti tabler-copy"></i>
                    </button>
                </div>
            </header>

            <?php if (!$configured): ?>
                <div class="assistant-banner">
                    <i class="icon-base ti tabler-key"></i>
                    <div>
                        <strong>Assistente não configurado</strong>
                        <span>Defina <code>GROQ_API_KEY</code> no <code>.env</code>. Para revisar a interface
                        sem chave, use <code>AGENT_MOCK=true</code>.</span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="assistant-log" id="assistantLog" role="log" aria-live="polite"></div>

            <div class="assistant-composer-wrap">
                <div class="assistant-slash" id="assistantSlash" hidden></div>

                <form class="assistant-composer" id="assistantForm"
                      data-audio-url="/api/transcribe"
                      data-audio-csrf="<?= e($csrf) ?>">
                    <div class="assistant-attachment-tray" id="assistantAttachments" hidden></div>

                    <div class="assistant-recorder" id="assistantRecorder" hidden>
                        <span class="assistant-rec-dot" aria-hidden="true"></span>
                        <canvas id="assistantWave" class="assistant-wave" height="40"></canvas>
                        <span class="assistant-rec-time" id="assistantRecTime">0:00</span>
                    </div>

                    <textarea id="assistantInput"
                              rows="1"
                              maxlength="4000"
                              placeholder="Pergunte, anexe ou descreva uma ação…"
                              autocomplete="off"
                              spellcheck="false"></textarea>

                    <div class="assistant-composer-bar">
                        <div class="assistant-toolbar">
                            <button type="button" class="assistant-tool-btn" id="assistantAttach" title="Anexar arquivo (PDF, imagem, áudio, texto)">
                                <i class="icon-base ti tabler-paperclip"></i>
                            </button>

                            <div class="assistant-model" id="assistantModel">
                                <button type="button" class="assistant-model-btn" id="assistantModelBtn" aria-haspopup="listbox" aria-expanded="false">
                                    <span id="assistantModelLabel">Modelo</span>
                                    <i class="icon-base ti tabler-chevron-down"></i>
                                </button>
                                <div class="assistant-model-menu" id="assistantModelMenu" role="listbox" hidden></div>
                            </div>

                            <div class="assistant-effort" id="assistantEffort" role="group" aria-label="Esforço de raciocínio" hidden>
                                <button type="button" data-effort="low" title="Resposta rápida">Rápido</button>
                                <button type="button" data-effort="medium" title="Equilíbrio entre tempo e profundidade">Equilibrado</button>
                                <button type="button" data-effort="high" title="Analisa com mais cuidado">Profundo</button>
                            </div>
                        </div>

                        <div class="assistant-actions">
                            <button type="button" class="assistant-rec-btn is-cancel" id="assistantRecCancel" hidden title="Cancelar gravação" aria-label="Cancelar gravação">
                                <i class="icon-base ti tabler-x"></i>
                            </button>
                            <button type="button" class="assistant-rec-btn is-stop" id="assistantRecStop" hidden title="Parar e revisar" aria-label="Parar e revisar">
                                <i class="icon-base ti tabler-check"></i>
                            </button>

                            <button type="button" class="assistant-stop" id="assistantStop" hidden>
                                <i class="icon-base ti tabler-player-stop"></i> Parar
                            </button>

                            <button type="button" class="assistant-mic-btn <?= $audioEnabled ? '' : 'd-none' ?>"
                                    id="assistantAudio" title="Gravar áudio" aria-label="Gravar áudio">
                                <i class="icon-base ti tabler-microphone"></i>
                            </button>

                            <button type="submit" class="assistant-send" id="assistantSend" aria-label="Enviar">
                                <span>Enviar</span>
                                <i class="icon-base ti tabler-arrow-up"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="assistant-dropzone" id="assistantDrop" hidden>
                    <i class="icon-base ti tabler-cloud-upload"></i>
                    <strong>Solte os arquivos aqui</strong>
                    <span>PDF, imagem, áudio, CSV, JSON ou texto · até 10 MB</span>
                </div>
            </div>
        </section>
    </div>
</div>

<input type="file" id="assistantFile" hidden multiple
       accept=".pdf,.txt,.md,.csv,.json,.jpg,.jpeg,.png,.webp,.webm,.ogg,.mp3,.wav,.m4a,application/pdf,image/*,audio/*,text/*">

<script type="application/json" id="assistantBootstrap"><?= json_encode($bootstrap, $jsonFlags) ?></script>
