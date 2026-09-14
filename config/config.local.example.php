<?php
// =============================================
// CONFIGURAÇÃO LOCAL (NÃO VAI PARA O GIT)
// Copie este arquivo para config.local.php e preencha com as credenciais
// do ambiente. config.local.php está no .gitignore.
//
// Tem prioridade sobre o .env. Se não existir, o sistema usa o .env.
// Pode ser editado direto no servidor (FTP), é PHP puro.
// =============================================

return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'mecanica',

    // Opcional: Turnstile (pode deixar de fora e usar o .env)
    // 'TURNSTILE_SITE_KEY' => '',
    // 'TURNSTILE_SECRET' => '',
    // 'TURNSTILE_HOSTNAMES' => 'localhost,127.0.0.1',
];
