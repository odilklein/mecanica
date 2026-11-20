<?php
// =============================================
// CLIENTE CANAL DA PEÇA
// Consome os endpoints JSON internos com cache em
// arquivo e rate limit de 1 req/s. Uso interno,
// conforme RELATORIO_CANAL_DA_PECA.md.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Services;

use RuntimeException;

class CanalDaPecaClient
{
    private const BASE = 'https://www.canaldapeca.com.br';
    private const USER_AGENT = 'AutoMecanica/1.0 (uso interno; contato da oficina)';

    private string $cacheDir;
    private static float $lastRequestAt = 0.0;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?: (dirname(__DIR__, 4) . '/storage/cache/canaldapeca');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
        if (!is_dir($this->cacheDir) || !is_writable($this->cacheDir)) {
            $this->cacheDir = sys_get_temp_dir() . '/canaldapeca';
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    /** Busca estruturada (6 itens/página). */
    public function search(string $term, array $filters = [], int $page = 1): array
    {
        $query = array_filter(array_merge([
            'q' => $term,
            'page' => max(1, $page),
        ], $filters), static fn ($value): bool => $value !== null && $value !== '');

        return $this->getJson('/searchProductsSuggestions?' . http_build_query($query), 600);
    }

    /** Preços/estoque em lote (até 50 SKUs por chamada). */
    public function prices(array $skus, array $location = []): array
    {
        $skus = array_values(array_unique(array_filter(array_map('intval', $skus))));
        $result = [];

        foreach (array_chunk($skus, 50) as $chunk) {
            $query = array_merge([
                'skusIdList' => implode(',', $chunk),
                'origin' => 'search',
            ], $location);

            $data = $this->getJson('/sku/price?' . http_build_query($query), 300);
            if (is_array($data)) {
                $result += $data;
            }
        }

        return $result;
    }

    /** Cascata veículo → marca → modelo → ano. */
    public function vehicles(string $type = '', string $make = '', string $model = ''): array
    {
        $path = '/veiculos';
        foreach ([$type, $make, $model] as $part) {
            if ($part !== '') {
                $path .= '/' . rawurlencode($part);
            }
        }

        return $this->getJson($path, 86400);
    }

    /** URL pública aproximada do produto (o site redireciona para o slug canônico). */
    public function productUrl(int $sku, string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $ascii), '-'));

        return self::BASE . '/p/' . $sku . '/' . ($slug !== '' ? $slug : 'produto');
    }

    private function getJson(string $path, int $ttl): array
    {
        $cacheFile = rtrim($this->cacheDir, '/') . '/' . sha1($path) . '.json';

        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $this->throttle();

        $attempts = 2;
        $lastError = '';

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $ch = curl_init(self::BASE . $path);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body !== false && $status < 400) {
                $decoded = json_decode((string) $body, true);
                if (is_array($decoded)) {
                    file_put_contents($cacheFile, json_encode($decoded, JSON_UNESCAPED_UNICODE));
                    return $decoded;
                }
                $lastError = 'resposta inválida';
            } else {
                $lastError = 'HTTP ' . $status . ' ' . $error;
            }

            if ($attempt < $attempts - 1) {
                usleep(1000000);
                $this->throttle();
            }
        }

        throw new RuntimeException('Canal da Peça: ' . $lastError);
    }

    private function throttle(): void
    {
        $now = microtime(true);
        $elapsed = $now - self::$lastRequestAt;
        if ($elapsed < 1.0) {
            usleep((int) ((1.0 - $elapsed) * 1000000));
        }
        self::$lastRequestAt = microtime(true);
    }
}
