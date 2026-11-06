<?php
// =============================================
// MIGRATION — cria a tabela de usuários (schema em inglês).
// Mapeia a tabela legada `usuarios`:
//   nome -> name, senha -> password, tipo -> role,
//   telefone -> phone, salario -> salary,
//   data_admissao -> hire_date, cargo -> job_title,
//   ativo -> is_active, data_cadastro -> created_at.
// =============================================

return [
    'up' => [
        "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(120) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','secretary','lead_mechanic','mechanic') NOT NULL DEFAULT 'mechanic',
            cpf VARCHAR(14) UNIQUE,
            phone VARCHAR(20),
            salary DECIMAL(10,2),
            hire_date DATE,
            job_title VARCHAR(100),
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        'DROP TABLE IF EXISTS users',
    ],
];
