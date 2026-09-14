<?php
// =============================================
// CLOUDFLARE TURNSTILE - VERIFICAÇÃO (server-side)
// Fluxo: navegador -> backend (aqui) -> siteverify da Cloudflare.
// Nunca chamar siteverify pelo navegador.
// =============================================

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/turnstile.php';

if (!function_exists('turnstile_secret')) {
    function turnstile_secret(): string
    {
        return trim((string) config_valor('TURNSTILE_SECRET'));
    }
}

if (!function_exists('turnstile_habilitado')) {
    // Ativo somente com a site key E o secret configurados. Sem os dois o
    // widget não renderiza e o login seria bloqueado indevidamente.
    function turnstile_habilitado(): bool
    {
        return turnstile_site_key() !== '' && turnstile_secret() !== '';
    }
}

if (!function_exists('turnstile_hostnames')) {
    // Hostnames de frontend permitidos (separados por vírgula em TURNSTILE_HOSTNAMES).
    function turnstile_hostnames(): array
    {
        $bruto = (string) config_valor('TURNSTILE_HOSTNAMES');
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
     * Retorna ['ok' => bool, 'ignorado' => bool, 'motivo' => string,
     *          'codigo' => string, 'detalhe' => string].
     * - ok=true/ignorado=true: verificação desativada (config incompleta).
     * - codigo 'token_ausente': o desafio não foi concluído no formulário.
     * - 'detalhe' traz error-codes/hostname da Cloudflare (para o error.log),
     *   nunca contém o secret.
     */
    function turnstile_verificar(?string $token, string $acao_esperada = ''): array
    {
        $faltando = [];
        if (turnstile_site_key() === '') {
            $faltando[] = 'TURNSTILE_SITE_KEY';
        }
        if (turnstile_secret() === '') {
            $faltando[] = 'TURNSTILE_SECRET';
        }
        if ($faltando) {
            return [
                'ok' => true,
                'ignorado' => true,
                'codigo' => 'desativado',
                'motivo' => 'Turnstile desativado: configure ' . implode(' e ', $faltando) . ' no .env/config.local.php.',
            ];
        }

        $token = (string) $token;
        if ($token === '' || strlen($token) > 2048) {
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'token_ausente',
                'motivo' => 'Token do Turnstile ausente ou inválido (desafio não concluído ou widget não renderizou).',
            ];
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
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'comunicacao',
                'motivo' => 'Falha ao consultar o Turnstile (rede/siteverify).',
            ];
        }

        $detalhe = 'error-codes=' . json_encode($resultado['error-codes'] ?? [])
            . ' action=' . ($resultado['action'] ?? '(vazio)')
            . ' hostname=' . ($resultado['hostname'] ?? '(vazio)');

        if (empty($resultado['success'])) {
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'nao_aprovado',
                'motivo' => 'Verificação anti-bot não aprovada pela Cloudflare.',
                'detalhe' => $detalhe,
            ];
        }
        if ($acao_esperada !== '' && (($resultado['action'] ?? '') !== $acao_esperada)) {
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'acao',
                'motivo' => 'Ação inesperada no token (esperada: ' . $acao_esperada . ').',
                'detalhe' => $detalhe,
            ];
        }

        $hostnames = turnstile_hostnames();
        if ($hostnames && !in_array((string) ($resultado['hostname'] ?? ''), $hostnames, true)) {
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'hostname',
                'motivo' => 'Hostname não permitido: ' . ($resultado['hostname'] ?? '(vazio)') . ' não está em TURNSTILE_HOSTNAMES.',
                'detalhe' => 'permitidos=' . implode(',', $hostnames),
            ];
        }

        return ['ok' => true, 'ignorado' => false, 'codigo' => 'ok', 'motivo' => ''];
    }
}
