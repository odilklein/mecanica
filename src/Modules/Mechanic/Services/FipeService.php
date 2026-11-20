<?php
// =============================================
// FIPE SERVICE — catálogo local + API Parallelum.
// Sync: marcas + modelos (versões). Anos/preços são
// buscados sob demanda e cacheados no banco.
// Fonte: https://fipe.parallelum.com.br/api/v1
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Services;

use PDO;

class FipeService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function baseUrl(): string
    {
        $url = (string) env('FIPE_BASE_URL', 'https://fipe.parallelum.com.br/api/v1');
        return rtrim($url, '/');
    }

    /**
     * Sincroniza marcas e modelos (versões). ~90 requisições.
     *
     * @param callable(string):void|null $log
     * @return array{brands:int, models:int, failures:array<string>}
     */
    public function sync(?callable $log = null): array
    {
        $log ??= static function (string $m): void {};

        $log('Buscando marcas da FIPE...');
        $brands = $this->httpJson($this->baseUrl() . '/carros/marcas');
        if (!$brands) {
            throw new \RuntimeException('Não foi possível obter as marcas da FIPE.');
        }

        $upsertBrand = $this->pdo->prepare(
            'INSERT INTO fipe_brands (code, name) VALUES (:code, :name)
             ON DUPLICATE KEY UPDATE name = VALUES(name), id = LAST_INSERT_ID(id)'
        );
        $upsertModel = $this->pdo->prepare(
            'INSERT INTO fipe_models (brand_id, code, name) VALUES (:brand_id, :code, :name)
             ON DUPLICATE KEY UPDATE name = VALUES(name)'
        );

        $failures = [];
        $brandCount = 0;

        foreach ($brands as $brand) {
            $code = trim((string) ($brand['codigo'] ?? ''));
            $name = trim((string) ($brand['nome'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }

            $upsertBrand->execute([':code' => $code, ':name' => $name]);
            $brandId = (int) $this->pdo->lastInsertId();
            $brandCount++;

            $data = $this->httpJson($this->baseUrl() . '/carros/marcas/' . rawurlencode($code) . '/modelos');
            if (!$data || empty($data['modelos'])) {
                $failures[] = $name;
                continue;
            }

            foreach ($data['modelos'] as $model) {
                $modelCode = trim((string) ($model['codigo'] ?? ''));
                $modelName = trim((string) ($model['nome'] ?? ''));
                if ($modelCode === '' || $modelName === '') {
                    continue;
                }
                $upsertModel->execute([
                    ':brand_id' => $brandId,
                    ':code' => $modelCode,
                    ':name' => mb_substr($modelName, 0, 150),
                ]);
            }

            $log("  [{$code}] {$name}: " . count($data['modelos']) . ' modelos');
        }

        return [
            'brands' => $brandCount,
            'models' => (int) $this->pdo->query('SELECT COUNT(*) FROM fipe_models')->fetchColumn(),
            'failures' => $failures,
        ];
    }

    /**
     * Modelos de uma marca (a partir do catálogo local).
     *
     * @return array<int, array{code:string, name:string}>
     */
    public function modelsForBrand(string $brandCode): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.code, m.name FROM fipe_models m
               JOIN fipe_brands b ON b.id = m.brand_id
              WHERE b.code = :code ORDER BY m.name'
        );
        $stmt->execute([':code' => $brandCode]);
        return $stmt->fetchAll();
    }

    /**
     * Anos de um modelo (busca na API e cacheia).
     *
     * @return array<int, array{code:string, name:string, year:?int}>
     */
    public function yearsForModel(int $modelId): array
    {
        $cached = $this->pdo->prepare('SELECT code, name, year FROM fipe_years WHERE model_id = :id ORDER BY name DESC');
        $cached->execute([':id' => $modelId]);
        $rows = $cached->fetchAll();
        if ($rows) {
            return $rows;
        }

        [$brandCode, $modelCode] = $this->codesForModel($modelId);
        if ($brandCode === null) {
            return [];
        }

        $data = $this->httpJson(
            $this->baseUrl() . '/carros/marcas/' . rawurlencode($brandCode)
            . '/modelos/' . rawurlencode($modelCode) . '/anos'
        );
        if (!$data) {
            return [];
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO fipe_years (model_id, code, name, year) VALUES (:model_id, :code, :name, :year)
             ON DUPLICATE KEY UPDATE name = VALUES(name), year = VALUES(year)'
        );

        foreach ($data as $year) {
            $code = trim((string) ($year['codigo'] ?? ''));
            $name = trim((string) ($year['nome'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }
            preg_match('/(\d{4})/', $name, $m);
            $yearNum = isset($m[1]) ? (int) $m[1] : null;
            $insert->execute([':model_id' => $modelId, ':code' => $code, ':name' => $name, ':year' => $yearNum]);
        }

        $cached->execute([':id' => $modelId]);
        return $cached->fetchAll();
    }

    /**
     * Preço de um modelo/ano (busca na API e cacheia).
     *
     * @return array{value:?float, year:?int, fuel:?string, fipe_code:?string, reference:?string}|null
     */
    public function priceFor(int $modelId, string $yearCode): ?array
    {
        $cached = $this->pdo->prepare('SELECT value, year, fuel, fipe_code, reference_month FROM fipe_prices WHERE model_id = :id AND year_code = :yc');
        $cached->execute([':id' => $modelId, ':yc' => $yearCode]);
        $row = $cached->fetch();
        if ($row) {
            return [
                'value' => $row['value'] !== null ? (float) $row['value'] : null,
                'year' => $row['year'] !== null ? (int) $row['year'] : null,
                'fuel' => $row['fuel'],
                'fipe_code' => $row['fipe_code'],
                'reference' => $row['reference_month'],
            ];
        }

        [$brandCode, $modelCode] = $this->codesForModel($modelId);
        if ($brandCode === null) {
            return null;
        }

        $data = $this->httpJson(
            $this->baseUrl() . '/carros/marcas/' . rawurlencode($brandCode)
            . '/modelos/' . rawurlencode($modelCode) . '/anos/' . rawurlencode($yearCode)
        );
        if (!$data) {
            return null;
        }

        $value = $this->money($data['Valor'] ?? null);
        $year = isset($data['AnoModelo']) ? (int) $data['AnoModelo'] : null;
        $fuel = $data['Combustivel'] ?? null;
        $fipeCode = $data['CodigoFipe'] ?? null;
        $reference = $data['MesReferencia'] ?? null;

        $this->pdo->prepare(
            'INSERT INTO fipe_prices (model_id, year_code, year, fuel, value, fipe_code, reference_month)
             VALUES (:model_id, :year_code, :year, :fuel, :value, :fipe_code, :reference)
             ON DUPLICATE KEY UPDATE year = VALUES(year), fuel = VALUES(fuel), value = VALUES(value),
                fipe_code = VALUES(fipe_code), reference_month = VALUES(reference_month)'
        )->execute([
            ':model_id' => $modelId,
            ':year_code' => $yearCode,
            ':year' => $year,
            ':fuel' => $fuel,
            ':value' => $value,
            ':fipe_code' => $fipeCode,
            ':reference' => $reference,
        ]);

        return [
            'value' => $value,
            'year' => $year,
            'fuel' => $fuel,
            'fipe_code' => $fipeCode,
            'reference' => $reference,
        ];
    }

    /** @return array{0: ?string, 1: ?string} [brandCode, modelCode] */
    public function codesForModel(int $modelId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.code AS brand_code, m.code AS model_code
               FROM fipe_models m JOIN fipe_brands b ON b.id = m.brand_id
              WHERE m.id = :id'
        );
        $stmt->execute([':id' => $modelId]);
        $row = $stmt->fetch();
        return $row ? [$row['brand_code'], $row['model_code']] : [null, null];
    }

    /** Resolve o id interno do modelo a partir dos códigos FIPE. */
    public function findModelId(string $brandCode, string $modelCode): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id FROM fipe_models m JOIN fipe_brands b ON b.id = m.brand_id
              WHERE b.code = :b AND m.code = :m'
        );
        $stmt->execute([':b' => $brandCode, ':m' => $modelCode]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** Opções de marca para selects (code => name). */
    public function brandOptions(): array
    {
        $options = [];
        foreach ($this->pdo->query('SELECT code, name FROM fipe_brands ORDER BY name')->fetchAll() as $row) {
            $options[$row['code']] = $row['name'];
        }
        return $options;
    }

    /**
     * Busca JSON com retry em 429 (rate limit).
     */
    private function httpJson(string $url): ?array
    {
        $attempts = 3;

        for ($i = 0; $i < $attempts; $i++) {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_USERAGENT => 'SistemaMecanica/2.0',
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                ]);
                $body = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($body === false || $status >= 400) {
                    if ($status === 429 && $i < $attempts - 1) {
                        usleep(($i + 1) * 800000);
                        continue;
                    }
                    return null;
                }
                $decoded = json_decode((string) $body, true);
                return is_array($decoded) ? $decoded : null;
            }

            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'header' => "Accept: application/json\r\nUser-Agent: SistemaMecanica/2.0\r\n",
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
            if ($body === false) {
                return null;
            }
            $decoded = json_decode((string) $body, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /** "R$ 12.345,67" -> 12345.67 */
    private function money(mixed $text): ?float
    {
        if ($text === null) {
            return null;
        }
        $clean = preg_replace('/[^\d,]/', '', (string) $text);
        $clean = str_replace(',', '.', (string) $clean);
        return $clean === '' ? null : (float) $clean;
    }
}
