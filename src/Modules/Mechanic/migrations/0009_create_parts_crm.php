<?php
// =============================================
// MIGRATION — CRM de peças
// Categorias, dados comerciais/estoque, imagem,
// aplicação veicular e histórico de movimentações.
// Enriquece também as peças já importadas do
// catálogo Canal da Peça.
// =============================================

return [
    'up' => [
        "CREATE TABLE part_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            description TEXT NULL,
            icon VARCHAR(50) NOT NULL DEFAULT 'package',
            color VARCHAR(20) NOT NULL DEFAULT 'primary',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_part_categories_slug (slug),
            INDEX idx_part_categories_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "INSERT INTO part_categories (name, slug, description, icon, color) VALUES
            ('Motor', 'motor', 'Componentes internos e periféricos do motor', 'engine', 'primary'),
            ('Freios', 'freios', 'Pastilhas, discos, lonas, fluidos e componentes de freio', 'circle-dot', 'danger'),
            ('Suspensão', 'suspensao', 'Amortecedores, molas, bandejas, pivôs e rolamentos', 'arrows-vertical', 'warning'),
            ('Filtros', 'filtros', 'Filtros de óleo, ar, combustível e cabine', 'filter', 'info'),
            ('Elétrica', 'eletrica', 'Baterias, alternadores, sensores e componentes elétricos', 'bolt', 'success'),
            ('Arrefecimento', 'arrefecimento', 'Radiadores, bombas d\\'água, termostatos e ventoinhas', 'temperature', 'info'),
            ('Transmissão', 'transmissao', 'Embreagem, câmbio, diferenciais e transmissão', 'settings', 'dark'),
            ('Pneus', 'pneus', 'Pneus, câmaras de ar e válvulas', 'circle', 'secondary'),
            ('Carroceria', 'carroceria', 'Parachoques, palhetas, retrovisores e vidros', 'car', 'primary'),
            ('Iluminação', 'iluminacao', 'Faróis, lanternas, lâmpadas e sinalizadores', 'bulb', 'warning'),
            ('Acessórios', 'acessorios', 'Acessórios, tuning e itens de conveniência', 'star', 'success'),
            ('Lubrificantes', 'lubrificantes', 'Óleos, fluidos e aditivos', 'droplet', 'info'),
            ('Ferramentas', 'ferramentas', 'Ferramentas e equipamentos de oficina', 'tools', 'secondary'),
            ('Diversos', 'diversos', 'Itens ainda não classificados', 'packages', 'secondary')",

        "ALTER TABLE parts
            ADD COLUMN category_id INT NULL AFTER category,
            ADD COLUMN sku VARCHAR(80) NULL AFTER part_code,
            ADD COLUMN barcode VARCHAR(80) NULL AFTER sku,
            ADD COLUMN description TEXT NULL AFTER barcode,
            ADD COLUMN image VARCHAR(500) NULL AFTER description,
            ADD COLUMN images JSON NULL AFTER image,
            ADD COLUMN cost_price DECIMAL(10,2) NULL AFTER price,
            ADD COLUMN stock INT NOT NULL DEFAULT 0 AFTER cost_price,
            ADD COLUMN min_stock INT NOT NULL DEFAULT 0 AFTER stock,
            ADD COLUMN location VARCHAR(80) NULL AFTER min_stock,
            ADD COLUMN supplier VARCHAR(160) NULL AFTER location,
            ADD COLUMN supplier_url VARCHAR(500) NULL AFTER supplier,
            ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'published' AFTER is_active,
            ADD COLUMN tags VARCHAR(255) NULL AFTER status,
            ADD COLUMN applications TEXT NULL AFTER tags,
            ADD COLUMN oem_codes JSON NULL AFTER applications,
            ADD COLUMN product_url VARCHAR(500) NULL AFTER oem_codes,
            ADD COLUMN universal TINYINT(1) NOT NULL DEFAULT 0 AFTER product_url,
            ADD COLUMN by_request TINYINT(1) NOT NULL DEFAULT 0 AFTER universal,
            ADD COLUMN by_budget TINYINT(1) NOT NULL DEFAULT 0 AFTER by_request,
            ADD COLUMN weight DECIMAL(10,3) NULL AFTER by_budget,
            ADD COLUMN warranty_months INT NULL AFTER weight,
            ADD COLUMN notes TEXT NULL AFTER warranty_months,
            ADD COLUMN created_by INT NULL AFTER notes,
            ADD COLUMN last_synced_at DATETIME NULL AFTER created_by,
            ADD INDEX idx_parts_category_id (category_id),
            ADD INDEX idx_parts_status (status),
            ADD CONSTRAINT fk_parts_category FOREIGN KEY (category_id) REFERENCES part_categories(id) ON DELETE SET NULL",

        "CREATE TABLE part_stock_movements (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            part_id INT NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'adjustment',
            quantity INT NOT NULL,
            balance_after INT NOT NULL DEFAULT 0,
            unit_cost DECIMAL(10,2) NULL,
            reason VARCHAR(200) NULL,
            reference VARCHAR(120) NULL,
            user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_part_movements_part (part_id),
            INDEX idx_part_movements_date (created_at),
            CONSTRAINT fk_part_movements_part FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Classificação funcional das peças já importadas (do mais
        // específico para o mais genérico; o último UPDATE é o fallback).
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'motor' LIMIT 1) WHERE category LIKE '%motor%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'freios' LIMIT 1) WHERE category LIKE '%freio%' OR category LIKE '%pastilha%' OR category LIKE '%disco%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'filtros' LIMIT 1) WHERE category LIKE '%filtro%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'suspensao' LIMIT 1) WHERE category LIKE '%suspens%' OR category LIKE '%amortecedor%' OR category LIKE '%bandeja%' OR category LIKE '%pivô%' OR category LIKE '%pivo%' OR category LIKE '%rolamento%' OR category LIKE '%cubo%' OR category LIKE '%mola%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'transmissao' LIMIT 1) WHERE category LIKE '%embreagem%' OR category LIKE '%câmbio%' OR category LIKE '%cambio%' OR category LIKE '%transmiss%' OR category LIKE '%diferencial%' OR category LIKE '%correia%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'arrefecimento' LIMIT 1) WHERE category LIKE '%radiador%' OR category LIKE '%arrefec%' OR category LIKE '%termostato%' OR category LIKE '%ventoinha%' OR category LIKE '%água%' OR category LIKE '%agua%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'eletrica' LIMIT 1) WHERE category LIKE '%elétric%' OR category LIKE '%eletric%' OR category LIKE '%bateria%' OR category LIKE '%alternador%' OR category LIKE '%partida%' OR category LIKE '%sensor%' OR category LIKE '%igni%' OR category LIKE '%vela%' OR category LIKE '%bobina%' OR category LIKE '%lâmpada%' OR category LIKE '%lampada%' OR category LIKE '%farol%' OR category LIKE '%lanterna%' OR category LIKE '%fusível%' OR category LIKE '%fusivel%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'pneus' LIMIT 1) WHERE category LIKE '%pneu%' OR category LIKE '%aro %'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'lubrificantes' LIMIT 1) WHERE category LIKE '%óleo%' OR category LIKE '%oleo%' OR category LIKE '%fluído%' OR category LIKE '%fluido%' OR category LIKE '%lubrificante%' OR category LIKE '%aditivo%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'carroceria' LIMIT 1) WHERE category LIKE '%carroceria%' OR category LIKE '%palheta%' OR category LIKE '%retrovisor%' OR category LIKE '%para-brisa%' OR category LIKE '%parachoque%' OR category LIKE '%vidro%' OR category LIKE '%porta%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'iluminacao' LIMIT 1) WHERE category LIKE '%ilumina%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'acessorios' LIMIT 1) WHERE category LIKE '%acessóri%' OR category LIKE '%acessori%' OR category LIKE '%tuning%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'ferramentas' LIMIT 1) WHERE category LIKE '%ferramenta%' OR category LIKE '%máquina%' OR category LIKE '%maquina%'",
        "UPDATE parts SET category_id = (SELECT id FROM part_categories WHERE slug = 'diversos' LIMIT 1) WHERE category_id IS NULL",

        // Status coerente com o flag legado.
        "UPDATE parts SET status = IF(is_active = 1, 'published', 'inactive')",

        // Enriquece as peças importadas com os dados completos do catálogo.
        "UPDATE parts p
           JOIN cdp_products cp ON cp.sku_id = p.cdp_sku
            SET p.sku = COALESCE(NULLIF(p.sku, ''), CAST(cp.sku_id AS CHAR)),
                p.description = COALESCE(NULLIF(p.description, ''), cp.description),
                p.image = COALESCE(NULLIF(p.image, ''), JSON_UNQUOTE(JSON_EXTRACT(cp.images, '$[0]'))),
                p.applications = COALESCE(NULLIF(p.applications, ''), cp.applications),
                p.oem_codes = COALESCE(p.oem_codes, cp.oem_codes),
                p.product_url = COALESCE(NULLIF(p.product_url, ''), cp.product_url),
                p.universal = cp.universal,
                p.by_request = cp.by_request,
                p.by_budget = cp.by_budget,
                p.last_synced_at = NOW()",
    ],
    'down' => [
        'DROP TABLE IF EXISTS part_stock_movements',
        'ALTER TABLE parts DROP FOREIGN KEY fk_parts_category',
        'ALTER TABLE parts DROP INDEX idx_parts_category_id',
        'ALTER TABLE parts DROP INDEX idx_parts_status',
        'ALTER TABLE parts
            DROP COLUMN category_id,
            DROP COLUMN sku,
            DROP COLUMN barcode,
            DROP COLUMN description,
            DROP COLUMN image,
            DROP COLUMN images,
            DROP COLUMN cost_price,
            DROP COLUMN stock,
            DROP COLUMN min_stock,
            DROP COLUMN location,
            DROP COLUMN supplier,
            DROP COLUMN supplier_url,
            DROP COLUMN status,
            DROP COLUMN tags,
            DROP COLUMN applications,
            DROP COLUMN oem_codes,
            DROP COLUMN product_url,
            DROP COLUMN universal,
            DROP COLUMN by_request,
            DROP COLUMN by_budget,
            DROP COLUMN weight,
            DROP COLUMN warranty_months,
            DROP COLUMN notes,
            DROP COLUMN created_by,
            DROP COLUMN last_synced_at',
        'DROP TABLE IF EXISTS part_categories',
    ],
];
