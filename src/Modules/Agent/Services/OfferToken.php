<?php
// =============================================
// OFFER TOKEN — assinatura das ofertas exibidas no chat.
//
// O botão "Usar esta peça" não pode confiar em dados do
// navegador (preço, link, sku). O servidor assina a oferta
// e valida na seleção.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

use RuntimeException;

final class OfferToken
{
    private const TTL = 1800;

    public static function sign(array $payload): string
    {
        $payload['exp'] = time() + self::TTL;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Falha ao preparar a oferta.');
        }

        $body = self::base64Url($json);
        $signature = hash_hmac('sha256', $body, self::secret(), true);

        return $body . '.' . self::base64Url($signature);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$body, $signature] = $parts;
        $expected = hash_hmac('sha256', $body, self::secret(), true);

        if (!hash_equals($expected, self::base64UrlDecode($signature))) {
            return null;
        }

        $json = self::base64UrlDecode($body);
        $data = json_decode($json, true);
        if (!is_array($data) || (int) ($data['exp'] ?? 0) < time()) {
            return null;
        }

        return $data;
    }

    private static function secret(): string
    {
        $key = trim((string) env('APP_KEY', ''));
        if ($key !== '' && strlen($key) >= 32) {
            return $key;
        }

        // Fail-closed: nunca derive o segredo de outra credencial (ex.: DB_PASS).
        // Gere com: php -r "echo bin2hex(random_bytes(32));"
        throw new \RuntimeException('APP_KEY ausente ou curta: defina uma chave de 32+ bytes no .env.');
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padded = str_pad(strtr($value, '-_', '+/'), (int) (ceil(strlen($value) / 4) * 4), '=');

        return (string) base64_decode($padded, true);
    }
}
