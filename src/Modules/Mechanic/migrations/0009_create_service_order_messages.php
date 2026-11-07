<?php
// =============================================
// MIGRATION — chat/ticket por ordem de serviço.
// =============================================

return [
    'up' => [
        "CREATE TABLE service_order_messages (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_som_order (order_id, id),
            CONSTRAINT fk_som_order FOREIGN KEY (order_id) REFERENCES service_orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_som_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS service_order_messages',
    ],
];
