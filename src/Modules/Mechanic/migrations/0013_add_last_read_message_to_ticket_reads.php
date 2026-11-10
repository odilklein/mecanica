<?php
// =============================================
// MIGRATION — leitura de tickets por ID de mensagem.
//
// Antes o "lido" usava apenas o timestamp (segundos), então uma
// mensagem enviada no mesmo segundo em que o ticket foi aberto
// podia ser considerada lida. O ponteiro por ID elimina o problema.
// =============================================

return [
    'up' => [
        "ALTER TABLE ticket_reads
            ADD COLUMN last_read_message_id BIGINT NULL AFTER last_read_at",

        // Backfill: considera lidas apenas as mensagens criadas até o
        // último read_at registrado.
        "UPDATE ticket_reads r
            SET r.last_read_message_id = COALESCE((
                SELECT MAX(m.id)
                  FROM ticket_messages m
                 WHERE m.ticket_id = r.ticket_id
                   AND m.created_at <= r.last_read_at
            ), 0)",
    ],
    'down' => [
        'ALTER TABLE ticket_reads DROP COLUMN last_read_message_id',
    ],
];
