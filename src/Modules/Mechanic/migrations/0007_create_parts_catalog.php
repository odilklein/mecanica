<?php
// =============================================
// MIGRATION — catálogo de peças (Canal da Peça)
// + metadados de catálogo nos itens do pedido.
// =============================================

return [
    'up' => [
        "CREATE TABLE cdp_vehicle_makes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_type VARCHAR(30) NOT NULL,
            slug VARCHAR(80) NOT NULL,
            name VARCHAR(120) NOT NULL,
            UNIQUE KEY uq_cdp_make (vehicle_type, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE cdp_vehicle_models (
            id INT AUTO_INCREMENT PRIMARY KEY,
            make_id INT NOT NULL,
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(160) NOT NULL,
            UNIQUE KEY uq_cdp_model (make_id, slug),
            CONSTRAINT fk_cdp_model_make FOREIGN KEY (make_id) REFERENCES cdp_vehicle_makes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE cdp_vehicle_years (
            id INT AUTO_INCREMENT PRIMARY KEY,
            model_id INT NOT NULL,
            year SMALLINT NOT NULL,
            UNIQUE KEY uq_cdp_year (model_id, year),
            CONSTRAINT fk_cdp_year_model FOREIGN KEY (model_id) REFERENCES cdp_vehicle_models(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE cdp_products (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            sku_id BIGINT NOT NULL,
            brand VARCHAR(120) NULL,
            part_code VARCHAR(80) NULL,
            name VARCHAR(255) NOT NULL,
            name_simple VARCHAR(200) NULL,
            description TEXT NULL,
            category VARCHAR(200) NULL,
            applications TEXT NULL,
            oem_codes JSON NULL,
            images JSON NULL,
            product_url VARCHAR(255) NULL,
            universal TINYINT(1) NOT NULL DEFAULT 0,
            by_request TINYINT(1) NOT NULL DEFAULT 0,
            by_budget TINYINT(1) NOT NULL DEFAULT 0,
            raw_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cdp_product_sku (sku_id),
            INDEX idx_cdp_product_brand (brand),
            INDEX idx_cdp_product_code (part_code),
            INDEX idx_cdp_product_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE cdp_offers (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            sku_id BIGINT NOT NULL,
            seller_name VARCHAR(160) NULL,
            seller_type VARCHAR(40) NULL,
            city VARCHAR(120) NULL,
            state CHAR(2) NULL,
            price DECIMAL(10,2) NULL,
            stock INT NULL,
            lead_time INT NULL,
            packing_time INT NULL,
            partners_part_code VARCHAR(80) NULL,
            is_best TINYINT(1) NOT NULL DEFAULT 0,
            consulted_at DATETIME NOT NULL,
            INDEX idx_cdp_offer_sku (sku_id),
            INDEX idx_cdp_offer_price (price)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE part_order_items
            ADD COLUMN sku VARCHAR(40) NULL AFTER description,
            ADD COLUMN unit_price DECIMAL(10,2) NULL AFTER quantity,
            ADD COLUMN supplier VARCHAR(160) NULL AFTER unit_price,
            ADD COLUMN product_url VARCHAR(255) NULL AFTER supplier,
            ADD COLUMN catalog_notes VARCHAR(255) NULL AFTER product_url",
    ],
    'down' => [
        'ALTER TABLE part_order_items
            DROP COLUMN catalog_notes,
            DROP COLUMN product_url,
            DROP COLUMN supplier,
            DROP COLUMN unit_price,
            DROP COLUMN sku',
        'DROP TABLE IF EXISTS cdp_offers',
        'DROP TABLE IF EXISTS cdp_products',
        'DROP TABLE IF EXISTS cdp_vehicle_years',
        'DROP TABLE IF EXISTS cdp_vehicle_models',
        'DROP TABLE IF EXISTS cdp_vehicle_makes',
    ],
];
