<?php
// =============================================
// MIGRATION — tabela de veículos (módulo Mechanic).
// Mapeia a legada `veiculos` (campos FIPE virão em
// migration própria, junto do catálogo).
// =============================================

return [
    'up' => [
        "CREATE TABLE vehicles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NULL,
            plate VARCHAR(10) UNIQUE,
            brand VARCHAR(80),
            model VARCHAR(120),
            year SMALLINT,
            color VARCHAR(30),
            current_km INT NOT NULL DEFAULT 0,
            chassis VARCHAR(30),
            notes TEXT,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_vehicles_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS vehicles',
    ],
];
