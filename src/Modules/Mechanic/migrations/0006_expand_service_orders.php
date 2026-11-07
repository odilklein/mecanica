<?php
// =============================================
// MIGRATION — expansão do módulo Mechanic para
// paridade com a versão antiga:
// - OS: observações internas, responsáveis, eventos, entrega
// - tarefas: autor
// - pedidos de peças: itens
// =============================================

return [
    'up' => [
        "ALTER TABLE service_orders ADD COLUMN internal_notes TEXT NULL AFTER diagnosis",

        "ALTER TABLE service_order_tasks ADD COLUMN created_by INT NULL AFTER status",
        "ALTER TABLE service_order_tasks ADD CONSTRAINT fk_sot_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL",

        "CREATE TABLE service_order_responsibles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            UNIQUE KEY uq_sor (order_id, user_id),
            CONSTRAINT fk_sor_order FOREIGN KEY (order_id) REFERENCES service_orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_sor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE service_order_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            type VARCHAR(40) NOT NULL,
            description VARCHAR(255) NOT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_soe_order FOREIGN KEY (order_id) REFERENCES service_orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_soe_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE service_order_deliveries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            modality ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
            responsible VARCHAR(120),
            destination VARCHAR(255),
            exit_km INT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'registered',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sod_order FOREIGN KEY (order_id) REFERENCES service_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE part_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_order_id INT NOT NULL,
            description VARCHAR(200) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            CONSTRAINT fk_poi_order FOREIGN KEY (part_order_id) REFERENCES part_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS part_order_items',
        'DROP TABLE IF EXISTS service_order_deliveries',
        'DROP TABLE IF EXISTS service_order_events',
        'DROP TABLE IF EXISTS service_order_responsibles',
        'ALTER TABLE service_order_tasks DROP FOREIGN KEY fk_sot_user, DROP COLUMN created_by',
        'ALTER TABLE service_orders DROP COLUMN internal_notes',
    ],
];
