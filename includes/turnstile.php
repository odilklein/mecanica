<?php
// =============================================
// CLOUDFLARE TURNSTILE - VERIFICAÇÃO (server-side)
// Fluxo: navegador -> backend (aqui) -> siteverify da Cloudflare.
// Nunca chamar siteverify pelo navegador.
// =============================================

require_once __DIR__ . '/../config/env.php';

if (!function_exists('turnstile_secret')) {
    function turnstile_secret(): string
    {
        return trim((string) getenv('TURNSTILE_SECRET'));
    }
}

if (!function_exists('turnstile_habilitado')) {
    // Só há verificação quando o secret está configurado no .env.
    function turnstile_habilitado(): bool
    {
        return turnstile_secret() !== '';
    }
}

if (!function_exists('turnstile_hostnames')) {
    // Hostnames de frontend permitidos (separados por vírgula em TURNSTILE_HOSTNAMES).
    function turnstile_hostnames(): array
    {
        $bruto = (string) getenv('TURNSTILE_HOSTNAMES');
        return array_values(array_filter(array_map('trim', explode(',', $bruto)), 'strlen'));
    }
}

if (!function_exists('turnstile_siteverify')) {
    // POST form-urlencoded para o siteverify. Retorna o JSON decodificado ou null em falha.
    function turnstile_siteverify(array $campos): ?array
    {
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $corpo = http_build_query($campos);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $corpo,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $resposta = curl_exec($ch);
            $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resposta === false) {
                return null;
            }
            $dados = json_decode($resposta, true);
            if (!is_array($dados)) {
                return null;
            }
            // A Cloudflare devolve 400 com JSON útil (ex.: invalid-input-secret).
            // O bloqueio em si é decidido por "success" em turnstile_verificar().
            if ($codigo >= 500) {
                return null;
            }
            return $dados;
        }

        // Fallback sem cURL.
        $contexto = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $corpo,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $resposta = @file_get_contents($url, false, $contexto);
        if ($resposta === false) {
            return null;
        }
        $dados = json_decode($resposta, true);
        return is_array($dados) ? $dados : null;
    }
}

if (!function_exists('turnstile_verificar')) {
    /**
     * Valida o token do widget.
     * Retorna ['ok' => bool, 'motivo' => string, 'ignorado' => bool].
     * Se o secret não estiver configurado, retorna ok=true e ignorado=true
     * (o sistema continua funcionando até você configurar o TURNSTILE_SECRET).
     */
    function turnstile_verificar(?string $token, string $acao_esperada = ''): array
    {
        if (!turnstile_habilitado()) {
            return ['ok' => true, 'motivo' => 'Turnstile desabilitado: TURNSTILE_SECRET não configurado.', 'ignorado' => true];
        }

        $token = (string) $token;
        if ($token === '' || strlen($token) > 2048) {
            return ['ok' => false, 'motivo' => 'Token do Turnstile ausente ou inválido.', 'ignorado' => false];
        }

        $campos = [
            'secret' => turnstile_secret(),
            'response' => $token,
        ];
        $ip_remoto = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip_remoto !== '') {
            $campos['remoteip'] = $ip_remoto;
        }

        $resultado = turnstile_siteverify($campos);
        if ($resultado === null) {
            return ['ok' => false, 'motivo' => 'Falha ao consultar o Turnstile.', 'ignorado' => false];
        }
        if (empty($resultado['success'])) {
            return ['ok' => false, 'motivo' => 'Verificação anti-bot não aprovada.', 'ignorado' => false];
        }
        if ($acao_esperada !== '' && (($resultado['action'] ?? '') !== $acao_esperada)) {
            return ['ok' => false, 'motivo' => 'Ação inesperada no token.', 'ignorado' => false];
        }

        $hostnames = turnstile_hostnames();
        if ($hostnames && !in_array((string) ($resultado['hostname'] ?? ''), $hostnames, true)) {
            return ['ok' => false, 'motivo' => 'Hostname não permitido.', 'ignorado' => false];
        }

        return ['ok' => true, 'motivo' => '', 'ignorado' => false];
    }
}
