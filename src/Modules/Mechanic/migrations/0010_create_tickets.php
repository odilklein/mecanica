<?php
// =============================================
// MIGRATION — sistema de tickets com chat.
// Substitui o chat antigo embutido na OS.
// =============================================

return [
    'up' => [
        'DROP TABLE IF EXISTS service_order_messages',

        "CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(200) NOT NULL,
            service_order_id INT NULL,
            created_by INT NOT NULL,
            assigned_to INT NULL,
            status ENUM('open','closed') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tickets_order (service_order_id),
            INDEX idx_tickets_created (created_by),
            INDEX idx_tickets_assigned (assigned_to),
            CONSTRAINT fk_tickets_order FOREIGN KEY (service_order_id) REFERENCES service_orders(id) ON DELETE SET NULL,
            CONSTRAINT fk_tickets_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_tickets_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS ticket_messages (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tm_ticket (ticket_id, id),
            CONSTRAINT fk_tm_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            CONSTRAINT fk_tm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS ticket_reads (
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            last_read_at DATETIME NULL,
            PRIMARY KEY (ticket_id, user_id),
            CONSTRAINT fk_tr_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            CONSTRAINT fk_tr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "INSERT INTO tickets (subject, service_order_id, created_by, assigned_to, status, created_at, updated_at)
         SELECT CONCAT('OS #', LPAD(so.number, 4, '0'), ' - ', COALESCE(c.name, 'Cliente')),
                so.id, COALESCE(so.created_by, 1), so.lead_mechanic_id, 'open', so.created_at, so.created_at
           FROM service_orders so
           LEFT JOIN clients c ON c.id = so.client_id
           LEFT JOIN tickets t ON t.service_order_id = so.id
          WHERE t.id IS NULL",
    ],
    'down' => [
        'DROP TABLE IF EXISTS ticket_reads',
        'DROP TABLE IF EXISTS ticket_messages',
        'DROP TABLE IF EXISTS tickets',
    ],
];
