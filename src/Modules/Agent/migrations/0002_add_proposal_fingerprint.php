<?php
// =============================================
// MIGRATION — impressão digital da proposta.
//
// A chave de idempotência unicamente única não serve
// para deduplicar propostas já decididas: abrir a
// mesma OS duas vezes em dias diferentes é legítimo.
// A fingerprint identifica a intenção atual, e a
// dedupe só vale enquanto a proposta está pendente.
// =============================================

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE agent_proposals ADD COLUMN fingerprint CHAR(64) NULL AFTER tool_name',

        'ALTER TABLE agent_proposals ADD INDEX idx_agent_proposals_fingerprint (user_id, fingerprint, status)',
    ],
    'down' => [
        'ALTER TABLE agent_proposals DROP INDEX idx_agent_proposals_fingerprint',
        'ALTER TABLE agent_proposals DROP COLUMN fingerprint',
    ],
];
