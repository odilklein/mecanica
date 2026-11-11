<?php
declare(strict_types=1);

namespace Modules\Core\Support;

/**
 * Cloudflare Turnstile — verificação server-side.
 *
 * A site key é pública; o secret vem de TURNSTILE_SECRET. Sem as duas chaves
 * configuradas, a verificação é ignorada para não bloquear o login.
 */
class Turnstile
{
    public static function siteKey(): string
    {
        return trim((string) env('TURNSTILE_SITE_KEY'));
    }

    public static function secret(): string
    {
        return trim((string) env('TURNSTILE_SECRET'));
    }

    public static function configured(): bool
    {
        return self::siteKey() !== '';
    }

    public static function enabled(): bool
    {
        return self::siteKey() !== '' && self::secret() !== '';
    }

    /** @return string[] */
    public static function hostnames(): array
    {
        $raw = (string) env('TURNSTILE_HOSTNAMES');
        return array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
    }

    /** POST form-urlencoded para o siteverify. Retorna o JSON decodificado ou null em falha. */
    public static function siteverify(array $fields): ?array
    {
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $body = http_build_query($fields);

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $response = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);

            if ($response === false || $status >= 500) {
                return null;
            }
            $data = json_decode($response, true);
            return is_array($data) ? $data : null;
        }

        // Fallback sem cURL.
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }
        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @return array{ok: bool, ignorado: bool, motivo: string, codigo: string, detalhe?: string}
     */
    public static function verify(?string $token, string $expectedAction = ''): array
    {
        $missing = [];
        if (self::siteKey() === '') {
            $missing[] = 'TURNSTILE_SITE_KEY';
        }
        if (self::secret() === '') {
            $missing[] = 'TURNSTILE_SECRET';
        }
        if ($missing) {
            return [
                'ok' => true,
                'ignorado' => true,
                'codigo' => 'desativado',
                'motivo' => 'Turnstile desativado: configure ' . implode(' e ', $missing) . ' no .env.',
            ];
        }

        $token = (string) $token;
        if ($token === '' || strlen($token) > 2048) {
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'token_ausente',
                'motivo' => 'Token do Turnstile ausente ou inválido.',
            ];
        }

        $fields = [
            'secret' => self::secret(),
            'response' => $token,
        ];
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($remoteIp !== '') {
            $fields['remoteip'] = $remoteIp;
        }

        $result = self::siteverify($fields);
        if ($result === null) {
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'comunicacao',
                'motivo' => 'Falha ao consultar o Turnstile (rede/siteverify).',
            ];
        }

        $detail = 'error-codes=' . json_encode($result['error-codes'] ?? [])
            . ' action=' . ($result['action'] ?? '(vazio)')
            . ' hostname=' . ($result['hostname'] ?? '(vazio)');

        if (empty($result['success'])) {
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'nao_aprovado',
                'motivo' => 'Verificação anti-bot não aprovada pela Cloudflare.',
                'detalhe' => $detail,
            ];
        }
        if ($expectedAction !== '' && (($result['action'] ?? '') !== $expectedAction)) {
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'acao',
                'motivo' => 'Ação inesperada no token (esperada: ' . $expectedAction . ').',
                'detalhe' => $detail,
            ];
        }

        $hostnames = self::hostnames();
        if ($hostnames && !in_array((string) ($result['hostname'] ?? ''), $hostnames, true)) {
            return [
                'ok' => false,
                'ignorado' => false,
                'codigo' => 'hostname',
                'motivo' => 'Hostname não permitido: ' . ($result['hostname'] ?? '(vazio)') . '.',
                'detalhe' => 'permitidos=' . implode(',', $hostnames),
            ];
        }

        return ['ok' => true, 'ignorado' => false, 'codigo' => 'ok', 'motivo' => ''];
    }
}
