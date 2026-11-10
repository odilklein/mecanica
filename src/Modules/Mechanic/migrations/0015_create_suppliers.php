<?php
// =============================================
// MIGRATION — fornecedores de peças.
// Centraliza os nomes usados em peças e pedidos
// para evitar digitação repetida.
// =============================================

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            document VARCHAR(30) NULL,
            phone VARCHAR(30) NULL,
            email VARCHAR(120) NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_suppliers_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Aproveita fornecedores já digitados em peças/pedidos.
        "INSERT IGNORE INTO suppliers (name)
            SELECT DISTINCT supplier FROM part_orders WHERE supplier IS NOT NULL AND supplier <> ''
            UNION
            SELECT DISTINCT supplier FROM parts WHERE supplier IS NOT NULL AND supplier <> ''",
    ],
    'down' => [
        'DROP TABLE IF EXISTS suppliers',
    ],
];
