<?php
// =============================================
// MIGRATION — número do endereço do cliente.
// (O CEP preenche a rua; o número é digitado pelo usuário.)
// =============================================

return [
    'up' => [
        'ALTER TABLE clients ADD COLUMN address_number VARCHAR(20) NULL AFTER address',
    ],
    'down' => [
        'ALTER TABLE clients DROP COLUMN address_number',
    ],
];
