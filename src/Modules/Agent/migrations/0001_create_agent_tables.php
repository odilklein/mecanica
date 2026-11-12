<?php
// =============================================
// MIGRATION — Assistente de IA (chat + propostas).
//
// O agente NUNCA escreve no domínio. Ele cria
// propostas em agent_proposals; a gravação real
// acontece quando um usuário aprova na interface.
// =============================================

declare(strict_types=1);

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS agent_sessions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(160) NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_agent_sessions_user (user_id, last_activity_at),
            CONSTRAINT fk_agent_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS agent_messages (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            session_id BIGINT NOT NULL,
            role ENUM('user','assistant','tool','system') NOT NULL,
            content MEDIUMTEXT NULL,
            tool_name VARCHAR(80) NULL,
            tool_calls JSON NULL,
            meta JSON NULL,
            tokens_in INT NOT NULL DEFAULT 0,
            tokens_out INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_agent_messages_session (session_id, id),
            CONSTRAINT fk_agent_message_session FOREIGN KEY (session_id) REFERENCES agent_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS agent_proposals (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            session_id BIGINT NULL,
            user_id INT NOT NULL,
            tool_name VARCHAR(80) NOT NULL,
            payload JSON NOT NULL,
            preview JSON NOT NULL,
            status ENUM('pending','approved','rejected','expired','applied','failed') NOT NULL DEFAULT 'pending',
            requires_role VARCHAR(40) NULL,
            expires_at DATETIME NULL,
            approved_by INT NULL,
            decided_at DATETIME NULL,
            applied_at DATETIME NULL,
            result JSON NULL,
            error VARCHAR(255) NULL,
            idempotency_key VARCHAR(64) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_agent_proposal_idem (idempotency_key),
            INDEX idx_agent_proposals_user (user_id, status),
            INDEX idx_agent_proposals_status (status, expires_at),
            CONSTRAINT fk_agent_proposal_session FOREIGN KEY (session_id) REFERENCES agent_sessions(id) ON DELETE SET NULL,
            CONSTRAINT fk_agent_proposal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_agent_proposal_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS agent_usage (
            user_id INT NOT NULL,
            day DATE NOT NULL,
            turns INT NOT NULL DEFAULT 0,
            tokens_in BIGINT NOT NULL DEFAULT 0,
            tokens_out BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id, day),
            CONSTRAINT fk_agent_usage_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS agent_usage',
        'DROP TABLE IF EXISTS agent_proposals',
        'DROP TABLE IF EXISTS agent_messages',
        'DROP TABLE IF EXISTS agent_sessions',
    ],
];
