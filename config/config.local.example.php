<?php
// =============================================
// CONFIGURAÇÃO LOCAL (NÃO VAI PARA O GIT)
// Copie este arquivo para config/config.local.php (ou config.local.php na
// raiz) e preencha com as credenciais do ambiente.
//
// - Tem prioridade sobre o .env e é ignorado pelo Git.
// - Ideal para hospedagem compartilhada que não aceita .env.
// - Pode ser editado direto no servidor (FTP), é PHP puro.
// - As variáveis do processo (ex.: Docker) continuam tendo prioridade
//   sobre este arquivo.
// =============================================

return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'mecanica',

    // Cloudflare Turnstile (opcional). Hostnames separados por vírgula.
    'TURNSTILE_SITE_KEY' => '',
    'TURNSTILE_SECRET' => '',
    'TURNSTILE_HOSTNAMES' => 'localhost,127.0.0.1',

    // Opcional: URL base da API FIPE (padrão já aponta para o Parallelum).
    // 'FIPE_BASE_URL' => 'https://fipe.parallelum.com.br/api/v1',
];
