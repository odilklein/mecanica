<?php
// =============================================
// MIGRATION — cria a tabela de clientes (schema em inglês).
// Mapeia a legada `clientes`:
//   nome -> name, telefone -> phone, endereco -> address,
//   cidade -> city, observacoes -> notes, ativo -> is_active,
//   data_cadastro -> created_at, data_atualizacao -> updated_at.
// =============================================

return [
    'up' => [
        "CREATE TABLE clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            cpf VARCHAR(14) UNIQUE,
            phone VARCHAR(20),
            email VARCHAR(120),
            address VARCHAR(255),
            city VARCHAR(80),
            notes TEXT,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS clients',
    ],
];
