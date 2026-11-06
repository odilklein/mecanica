<?php
// =============================================
// MIGRATION — configurações do sistema (chave/valor).
// =============================================

return [
    'up' => [
        "CREATE TABLE settings (
            setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
            ('theme_color', '#b8270f'),
            ('seo_title', 'Mecânica — Sistema de Gestão'),
            ('seo_description', 'Sistema de gestão para oficinas mecânicas.'),
            ('seo_keywords', 'oficina mecânica, sistema de gestão, ordem de serviço')",
    ],
    'down' => [
        'DROP TABLE IF EXISTS settings',
    ],
];
