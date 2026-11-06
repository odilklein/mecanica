<?php
// =============================================
// MIGRATION — Pedidos de peças (vinculados à OS).
// Status: requested, validated, ordered, on_the_way,
// received, cancelled.
// =============================================

return [
    'up' => [
        "CREATE TABLE part_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_order_id INT NOT NULL,
            supplier VARCHAR(120),
            status ENUM('requested','validated','ordered','on_the_way','received','cancelled') NOT NULL DEFAULT 'requested',
            expected_date DATE NULL,
            notes TEXT,
            created_by INT NULL,
            received_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            received_at DATETIME NULL,
            CONSTRAINT fk_po_order FOREIGN KEY (service_order_id) REFERENCES service_orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_po_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_po_received FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS part_orders',
    ],
];
