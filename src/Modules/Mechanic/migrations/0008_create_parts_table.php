<?php
// =============================================
// MIGRATION — catálogo simples de peças
// (nome, marca, código, categoria e preço de
// referência; sem estoque/fornecedor no MVP).
// =============================================

return [
    'up' => [
        "CREATE TABLE parts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            brand VARCHAR(120) NULL,
            part_code VARCHAR(80) NULL,
            category VARCHAR(120) NULL,
            price DECIMAL(10,2) NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'manual',
            cdp_sku BIGINT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_parts_cdp_sku (cdp_sku),
            INDEX idx_parts_name (name),
            INDEX idx_parts_brand (brand),
            INDEX idx_parts_code (part_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS parts',
    ],
];
