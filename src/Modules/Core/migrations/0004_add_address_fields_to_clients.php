<?php
// =============================================
// MIGRATION — endereço dos clientes (CEP/bairro/UF).
// =============================================

return [
    'up' => [
        "ALTER TABLE clients
            ADD COLUMN zip VARCHAR(10) NULL AFTER email,
            ADD COLUMN neighborhood VARCHAR(80) NULL AFTER address,
            ADD COLUMN state VARCHAR(2) NULL AFTER city",
    ],
    'down' => [
        'ALTER TABLE clients DROP COLUMN zip, DROP COLUMN neighborhood, DROP COLUMN state',
    ],
];
