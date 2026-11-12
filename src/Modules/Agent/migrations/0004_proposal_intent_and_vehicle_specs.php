<?php
// =============================================
// MIGRATION — intenção da proposta + ficha técnica.
//
// - `intent` identifica o "assunto" da proposta (ex.: partorder:os:45).
//   Uma nova proposta com a mesma intenção substitui a pendente anterior
//   em vez de empilhar duplicatas.
// - `vehicle_specs` guarda a ficha técnica curada (óleo, filtros, pneus),
//   com fonte e confiança.
// =============================================

declare(strict_types=1);

return [
    'up' => [
        "ALTER TABLE agent_proposals
            ADD COLUMN intent VARCHAR(120) NULL AFTER tool_name,
            ADD COLUMN superseded_by BIGINT NULL AFTER approved_by",

        "ALTER TABLE agent_proposals
            MODIFY COLUMN status ENUM('pending','approved','rejected','expired','applied','failed','superseded')
            NOT NULL DEFAULT 'pending'",

        'ALTER TABLE agent_proposals ADD INDEX idx_agent_proposals_intent (user_id, intent, status)',

        "CREATE TABLE IF NOT EXISTS vehicle_specs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            make VARCHAR(80) NOT NULL,
            model VARCHAR(120) NOT NULL,
            year_from SMALLINT NULL,
            year_to SMALLINT NULL,
            engine VARCHAR(60) NULL,
            oil_viscosity VARCHAR(20) NULL,
            oil_capacity_l DECIMAL(4,2) NULL,
            oil_spec VARCHAR(120) NULL,
            oil_filter_code VARCHAR(60) NULL,
            air_filter_code VARCHAR(60) NULL,
            cabin_filter_code VARCHAR(60) NULL,
            fuel_filter_code VARCHAR(60) NULL,
            tire_size VARCHAR(40) NULL,
            notes TEXT NULL,
            source VARCHAR(255) NULL,
            confidence ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
            updated_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_vehicle_specs_model (make, model),
            CONSTRAINT fk_vehicle_specs_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS vehicle_specs',
        'ALTER TABLE agent_proposals DROP INDEX idx_agent_proposals_intent',
        "ALTER TABLE agent_proposals
            MODIFY COLUMN status ENUM('pending','approved','rejected','expired','applied','failed')
            NOT NULL DEFAULT 'pending'",
        'ALTER TABLE agent_proposals DROP COLUMN superseded_by',
        'ALTER TABLE agent_proposals DROP COLUMN intent',
    ],
];
