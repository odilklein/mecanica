<?php
// =============================================
// MIGRATION — usuários padrão de desenvolvimento.
// Senha de todos: admin123 (trocar em produção).
// =============================================

return [
    'up' => [
        "INSERT INTO users (name, email, password, role, is_active) VALUES
         ('Administrador', 'admin@mecanica.com', '\$2y\$12\$sHoNQoIMhbrFjz8KgXTS2e0cA6jfLaijNEpjLNRRm.8Tor3p4XVfW', 'admin', 1),
         ('Secretária', 'secretaria@mecanica.com', '\$2y\$12\$sHoNQoIMhbrFjz8KgXTS2e0cA6jfLaijNEpjLNRRm.8Tor3p4XVfW', 'secretary', 1),
         ('Mecânico', 'mecanico@mecanica.com', '\$2y\$12\$sHoNQoIMhbrFjz8KgXTS2e0cA6jfLaijNEpjLNRRm.8Tor3p4XVfW', 'mechanic', 1)",
    ],
    'down' => [
        "DELETE FROM users WHERE email IN ('admin@mecanica.com', 'secretaria@mecanica.com', 'mecanico@mecanica.com')",
    ],
];
