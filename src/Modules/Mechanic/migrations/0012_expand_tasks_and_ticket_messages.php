<?php
// =============================================
// MIGRATION — tarefas com modelo expandido e
// mensagens de sistema no ticket da OS.
//
// service_order_tasks: responsável, prazo,
// observações e conclusão.
// ticket_messages: kind = message | system
// (usado pelo resumo automático da OS).
// =============================================

return [
    'up' => [
        "ALTER TABLE service_order_tasks
            ADD COLUMN assigned_to INT NULL AFTER status,
            ADD COLUMN due_date DATE NULL AFTER assigned_to,
            ADD COLUMN notes TEXT NULL AFTER due_date,
            ADD COLUMN completed_at DATETIME NULL AFTER notes,
            ADD COLUMN completed_by INT NULL AFTER completed_at,
            ADD CONSTRAINT fk_sot_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            ADD CONSTRAINT fk_sot_completed FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL",

        "ALTER TABLE ticket_messages
            ADD COLUMN kind ENUM('message','system') NOT NULL DEFAULT 'message' AFTER message",
    ],
    'down' => [
        'ALTER TABLE ticket_messages DROP COLUMN kind',
        'ALTER TABLE service_order_tasks
            DROP FOREIGN KEY fk_sot_completed,
            DROP FOREIGN KEY fk_sot_assigned,
            DROP COLUMN completed_by,
            DROP COLUMN completed_at,
            DROP COLUMN notes,
            DROP COLUMN due_date,
            DROP COLUMN assigned_to',
    ],
];
