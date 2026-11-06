<?php
// =============================================
// MIGRATION — Orçamentos + itens.
// Status: open, approved, rejected, cancelled.
// =============================================

return [
    'up' => [
        "CREATE TABLE budgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            number INT NOT NULL UNIQUE,
            client_id INT NULL,
            vehicle_id INT NULL,
            service_order_id INT NULL,
            budget_date DATE,
            valid_until DATE,
            status ENUM('open','approved','rejected','cancelled') NOT NULL DEFAULT 'open',
            parts_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            labor_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            discount DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            notes TEXT,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_budget_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
            CONSTRAINT fk_budget_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
            CONSTRAINT fk_budget_order FOREIGN KEY (service_order_id) REFERENCES service_orders(id) ON DELETE SET NULL,
            CONSTRAINT fk_budget_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE budget_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            budget_id INT NOT NULL,
            type ENUM('part','service') NOT NULL DEFAULT 'part',
            description VARCHAR(200) NOT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_item_budget FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS budget_items',
        'DROP TABLE IF EXISTS budgets',
    ],
];
