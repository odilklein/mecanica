<?php
// =============================================
// MIGRATION — catálogo FIPE + vínculo no veículo.
// - fipe_brands / fipe_models: espelho do catálogo (sync).
// - fipe_years / fipe_prices: cache sob demanda (form).
// - vehicles: códigos/anop/referência/preço da FIPE.
// =============================================

return [
    'up' => [
        "CREATE TABLE fipe_brands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(10) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE fipe_models (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            code VARCHAR(20) NOT NULL,
            name VARCHAR(150) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fipe_model (brand_id, code),
            CONSTRAINT fk_fipe_models_brand FOREIGN KEY (brand_id) REFERENCES fipe_brands(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE fipe_years (
            id INT AUTO_INCREMENT PRIMARY KEY,
            model_id INT NOT NULL,
            code VARCHAR(20) NOT NULL,
            name VARCHAR(60) NOT NULL,
            year INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fipe_year (model_id, code),
            CONSTRAINT fk_fipe_years_model FOREIGN KEY (model_id) REFERENCES fipe_models(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE fipe_prices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            model_id INT NOT NULL,
            year_code VARCHAR(20) NOT NULL,
            year INT NULL,
            fuel VARCHAR(30) NULL,
            value DECIMAL(10,2) NULL,
            fipe_code VARCHAR(20) NULL,
            reference_month VARCHAR(20) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fipe_price (model_id, year_code),
            CONSTRAINT fk_fipe_prices_model FOREIGN KEY (model_id) REFERENCES fipe_models(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE vehicles
            ADD COLUMN fipe_brand_code VARCHAR(10) NULL AFTER plate,
            ADD COLUMN fipe_model_code VARCHAR(20) NULL AFTER fipe_brand_code,
            ADD COLUMN fipe_year_code VARCHAR(20) NULL AFTER fipe_model_code,
            ADD COLUMN fipe_year INT NULL AFTER fipe_year_code,
            ADD COLUMN fipe_value DECIMAL(10,2) NULL AFTER fipe_year,
            ADD COLUMN fipe_reference VARCHAR(20) NULL AFTER fipe_value",
    ],
    'down' => [
        'ALTER TABLE vehicles'
            . ' DROP COLUMN fipe_brand_code, DROP COLUMN fipe_model_code,'
            . ' DROP COLUMN fipe_year_code, DROP COLUMN fipe_year,'
            . ' DROP COLUMN fipe_value, DROP COLUMN fipe_reference',
        'DROP TABLE IF EXISTS fipe_prices',
        'DROP TABLE IF EXISTS fipe_years',
        'DROP TABLE IF EXISTS fipe_models',
        'DROP TABLE IF EXISTS fipe_brands',
    ],
];
