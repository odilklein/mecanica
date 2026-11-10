<?php
// =============================================
// MIGRATION — tabela de tentativas de login.
// Usada pelo rate-limit de autenticação. No banco
// legado ela vinha do dump SQL; aqui fica garantida
// também para instalações novas via bin/migrate.php.
// =============================================

return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS login_tentativas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(120) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_ip (email, ip, criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS login_tentativas',
    ],
];
