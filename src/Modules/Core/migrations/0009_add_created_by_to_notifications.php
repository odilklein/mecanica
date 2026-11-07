<?php
// =============================================
// MIGRATION — autor da notificação.
// Permite exibir quem criou/disparou a notificação
// (ex.: mecânico que alterou o status da OS).
// =============================================

return [
    'up' => [
        "ALTER TABLE notifications ADD COLUMN created_by INT NULL AFTER user_id",
        "ALTER TABLE notifications ADD CONSTRAINT fk_notifications_creator
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL",
    ],
    'down' => [
        "ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_creator",
        "ALTER TABLE notifications DROP COLUMN created_by",
    ],
];
