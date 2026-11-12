<?php
// =============================================
// VEHICLE SPECS — ficha técnica curada do veículo.
//
// Não existe API pública confiável de ficha técnica (óleo,
// filtros, pneus). A tabela é curada pelo admin e sempre
// carrega fonte + confiança; o assistente nunca apresenta
// um dado sem dizer de onde veio.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

use PDO;

class VehicleSpecService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Normaliza a marca do sistema para o vocabulário de busca.
     * "GM - Chevrolet" => "chevrolet"; "VW - VolksWagen" => "volkswagen".
     */
    public static function makeSlug(string $make): string
    {
        $value = self::normalize($make);
        $value = preg_replace('/^(gm|vw|fiat|ford|hyundai|caoa|toyota|honda)\s+/', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * "Tracker Premier 1.2" => "tracker"; mantém o modelo completo em `model_full`.
     */
    public static function modelSlug(string $model): string
    {
        $value = self::normalize($model);
        $parts = preg_split('/\s+/', $value) ?: [];

        return $parts[0] ?? $value;
    }

    public static function engine(string $model): ?string
    {
        if (preg_match('/(\d[.,]\d)/', $model, $match)) {
            return str_replace(',', '.', $match[1]);
        }
        if (preg_match('/(\d{1,2}\.\d)\s*(turbo|tsi|tgdi)/i', $model, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $vehicle Linha de `vehicles`.
     * @return array<string, mixed>|null
     */
    public function forVehicle(array $vehicle): ?array
    {
        $make = self::normalize(self::makeSlug((string) ($vehicle['brand'] ?? '')));
        $model = self::normalize((string) ($vehicle['model'] ?? ''));
        $modelSlug = self::modelSlug($model);
        $engine = self::engine($model);
        $year = (int) ($vehicle['year'] ?? 0);

        if ($make === '' || $modelSlug === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM vehicle_specs
              WHERE make = :make
                AND (model = :model OR model = :model_slug OR :model_full LIKE CONCAT(model, "%"))
              ORDER BY (engine IS NOT NULL) DESC, id DESC'
        );
        $stmt->execute([
            ':make' => $make,
            ':model' => $model,
            ':model_slug' => $modelSlug,
            ':model_full' => $model,
        ]);

        $best = null;
        foreach ($stmt->fetchAll() as $row) {
            $from = $row['year_from'] !== null ? (int) $row['year_from'] : null;
            $to = $row['year_to'] !== null ? (int) $row['year_to'] : null;

            if ($year > 0) {
                if ($from !== null && $year < $from) {
                    continue;
                }
                if ($to !== null && $year > $to) {
                    continue;
                }
            }

            $rowEngine = trim((string) ($row['engine'] ?? ''));
            if ($rowEngine !== '' && $engine !== null && $rowEngine !== $engine) {
                continue;
            }

            $best = $row;
            break;
        }

        return $best ? $this->hydrate($best) : null;
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    public function describe(?array $spec): array
    {
        if (!$spec) {
            return [
                'known' => false,
                'label' => 'Ficha técnica não cadastrada',
                'source' => null,
                'confidence' => 'none',
                'codes' => [],
            ];
        }

        $bits = [];
        if (!empty($spec['oil_viscosity'])) {
            $bits[] = (string) $spec['oil_viscosity'];
        }
        if (!empty($spec['oil_capacity_l'])) {
            $bits[] = rtrim(rtrim(number_format((float) $spec['oil_capacity_l'], 2, ',', '.'), '0'), ',') . ' L';
        }
        if (!empty($spec['oil_spec'])) {
            $bits[] = (string) $spec['oil_spec'];
        }

        $codes = array_filter([
            'Filtro de óleo' => $spec['oil_filter_code'] ?? null,
            'Filtro de ar' => $spec['air_filter_code'] ?? null,
            'Filtro de cabine' => $spec['cabin_filter_code'] ?? null,
            'Filtro de combustível' => $spec['fuel_filter_code'] ?? null,
            'Pneus' => $spec['tire_size'] ?? null,
        ]);

        return [
            'known' => true,
            'label' => $bits ? implode(' · ', $bits) : 'Ficha cadastrada',
            'source' => $spec['source'] ?? null,
            'confidence' => (string) ($spec['confidence'] ?? 'medium'),
            'codes' => $codes,
            'notes' => $spec['notes'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsert(array $data, int $userId): array
    {
        $make = self::normalize(self::makeSlug((string) ($data['make'] ?? '')));
        $model = self::normalize((string) ($data['model'] ?? ''));
        if ($make === '' || $model === '') {
            throw new \RuntimeException('Informe marca e modelo.');
        }

        $yearFrom = isset($data['year_from']) && $data['year_from'] !== '' ? (int) $data['year_from'] : null;
        $yearTo = isset($data['year_to']) && $data['year_to'] !== '' ? (int) $data['year_to'] : null;
        $engine = trim((string) ($data['engine'] ?? '')) ?: null;

        $existing = $this->pdo->prepare(
            'SELECT id FROM vehicle_specs
              WHERE make = :m AND model = :mo
                AND COALESCE(year_from, 0) = COALESCE(:yf, 0)
                AND COALESCE(year_to, 0) = COALESCE(:yt, 0)
                AND COALESCE(engine, "") = COALESCE(:en, "")
              LIMIT 1'
        );
        $existing->execute([
            ':m' => $make,
            ':mo' => $model,
            ':yf' => $yearFrom,
            ':yt' => $yearTo,
            ':en' => $engine,
        ]);
        $id = (int) ($existing->fetchColumn() ?: 0);

        $values = [
            ':make' => $make,
            ':model' => $model,
            ':year_from' => $yearFrom,
            ':year_to' => $yearTo,
            ':engine' => $engine,
            ':oil_viscosity' => trim((string) ($data['oil_viscosity'] ?? '')) ?: null,
            ':oil_capacity_l' => isset($data['oil_capacity_l']) && $data['oil_capacity_l'] !== ''
                ? (float) str_replace(',', '.', (string) $data['oil_capacity_l'])
                : null,
            ':oil_spec' => trim((string) ($data['oil_spec'] ?? '')) ?: null,
            ':oil_filter_code' => trim((string) ($data['oil_filter_code'] ?? '')) ?: null,
            ':air_filter_code' => trim((string) ($data['air_filter_code'] ?? '')) ?: null,
            ':cabin_filter_code' => trim((string) ($data['cabin_filter_code'] ?? '')) ?: null,
            ':fuel_filter_code' => trim((string) ($data['fuel_filter_code'] ?? '')) ?: null,
            ':tire_size' => trim((string) ($data['tire_size'] ?? '')) ?: null,
            ':notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ':source' => trim((string) ($data['source'] ?? '')) ?: null,
            ':confidence' => in_array(($data['confidence'] ?? ''), ['high', 'medium', 'low'], true)
                ? (string) $data['confidence']
                : 'medium',
            ':updated_by' => $userId,
        ];

        if ($id > 0) {
            $sets = [];
            foreach (array_keys($values) as $key) {
                $sets[] = substr($key, 1) . ' = ' . $key;
            }
            $this->pdo->prepare('UPDATE vehicle_specs SET ' . implode(', ', $sets) . ' WHERE id = :id')
                ->execute($values + [':id' => $id]);
        } else {
            $columns = array_map(static fn (string $key): string => substr($key, 1), array_keys($values));
            $this->pdo->prepare(
                'INSERT INTO vehicle_specs (' . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', array_keys($values)) . ')'
            )->execute($values);
            $id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare('SELECT * FROM vehicle_specs WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch() ?: [];
        if ($row) {
            record_audit($this->pdo, $userId, 'vehicle_specs', $id, 'upsert', [
                'make' => $make,
                'model' => $model,
                'engine' => $engine,
            ]);
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['year_from'] = $row['year_from'] !== null ? (int) $row['year_from'] : null;
        $row['year_to'] = $row['year_to'] !== null ? (int) $row['year_to'] : null;

        return $row;
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = (string) preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim($value);
    }
}
