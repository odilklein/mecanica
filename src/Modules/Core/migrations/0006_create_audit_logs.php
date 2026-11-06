<?php
// =============================================
// MIGRATION — trilha de auditoria.
// =============================================

return [
    'up' => [
        "CREATE TABLE audit_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            entity VARCHAR(60) NOT NULL,
            entity_id BIGINT NULL,
            action VARCHAR(40) NOT NULL,
            data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS audit_logs',
    ],
];
