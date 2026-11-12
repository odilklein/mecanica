<?php
// =============================================
// MIGRATION — anexos do assistente.
//
// Arquivos ficam fora do webroot (storage/agent) e são
// servidos por um controller com checagem de dono.
// =============================================

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS agent_attachments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id BIGINT NULL,
            message_id BIGINT NULL,
            filename VARCHAR(255) NOT NULL,
            mime VARCHAR(120) NOT NULL,
            kind ENUM('image','pdf','text','audio') NOT NULL,
            bytes INT NOT NULL DEFAULT 0,
            sha256 CHAR(64) NOT NULL,
            storage_path VARCHAR(255) NOT NULL,
            extracted_text MEDIUMTEXT NULL,
            pages INT NULL,
            duration_seconds INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_agent_att_user (user_id, id),
            INDEX idx_agent_att_session (session_id, id),
            CONSTRAINT fk_agent_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_agent_att_session FOREIGN KEY (session_id) REFERENCES agent_sessions(id) ON DELETE CASCADE,
            CONSTRAINT fk_agent_att_message FOREIGN KEY (message_id) REFERENCES agent_messages(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS agent_attachments',
    ],
];
