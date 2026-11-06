<?php
// =============================================
// MIGRATION — Ordens de Serviço + tarefas.
// Status (8): received, in_analysis, awaiting_approval,
// awaiting_part, in_progress, ready_for_delivery,
// delivered, cancelled.
// =============================================

return [
    'up' => [
        "CREATE TABLE service_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            number INT NOT NULL UNIQUE,
            client_id INT NULL,
            vehicle_id INT NULL,
            status ENUM('received','in_analysis','awaiting_approval','awaiting_part','in_progress','ready_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'received',
            priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
            initial_complaint TEXT,
            diagnosis TEXT,
            entry_date DATE,
            completion_date DATE,
            lead_mechanic_id INT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_so_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
            CONSTRAINT fk_so_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
            CONSTRAINT fk_so_lead FOREIGN KEY (lead_mechanic_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_so_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE service_order_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            description VARCHAR(255) NOT NULL,
            status ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sot_order FOREIGN KEY (order_id) REFERENCES service_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS service_order_tasks',
        'DROP TABLE IF EXISTS service_orders',
    ],
];
