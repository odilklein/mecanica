<?php
// =============================================
// CATÁLOGO LOCAL DE PEÇAS
// Persiste produtos, ofertas e a árvore de veículos
// do Canal da Peça e fornece consultas de fallback.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Services;

use PDO;

class PartsCatalog
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Grava/atualiza um produto e substitui suas ofertas atuais. */
    public function upsertProduct(array $sku, array $priceData, CanalDaPecaClient $client): void
    {
        $skuId = (int) ($sku['id'] ?? 0);
        if ($skuId <= 0) {
            return;
        }

        $images = [];
        $imageSet = $sku['imageSet'] ?? null;
        if (is_array($imageSet)) {
            $base = rtrim((string) ($imageSet['baseUrl'] ?? ''), '/');
            foreach (($imageSet['paths'] ?? []) as $path) {
                $images[] = $base . '/' . ltrim((string) $path, '/');
            }
        }

        $categoryParts = [];
        foreach (['category1', 'category2', 'category3', 'category4'] as $key) {
            if (!empty($sku[$key])) {
                $categoryParts[] = (string) $sku[$key];
            }
        }

        $name = (string) ($sku['name'] ?? $sku['nameSimple'] ?? 'Produto');
        $priceFlags = is_array($sku['price'] ?? null) ? $sku['price'] : [];

        $stmt = $this->pdo->prepare(
            'INSERT INTO cdp_products
                (sku_id, brand, part_code, name, name_simple, description, category, applications,
                 oem_codes, images, product_url, universal, by_request, by_budget, raw_json)
             VALUES
                (:sku_id, :brand, :part_code, :name, :name_simple, :description, :category, :applications,
                 :oem_codes, :images, :product_url, :universal, :by_request, :by_budget, :raw_json)
             ON DUPLICATE KEY UPDATE
                brand = VALUES(brand), part_code = VALUES(part_code), name = VALUES(name),
                name_simple = VALUES(name_simple), description = VALUES(description),
                category = VALUES(category), applications = VALUES(applications),
                oem_codes = VALUES(oem_codes), images = VALUES(images), product_url = VALUES(product_url),
                universal = VALUES(universal), by_request = VALUES(by_request),
                by_budget = VALUES(by_budget), raw_json = VALUES(raw_json), updated_at = NOW()'
        );

        $stmt->execute([
            ':sku_id' => $skuId,
            ':brand' => $this->limit((string) ($sku['brand'] ?? ''), 120),
            ':part_code' => $this->limit((string) ($sku['partCode'] ?? ''), 80),
            ':name' => $this->limit($name, 255),
            ':name_simple' => $this->limit((string) ($sku['nameSimple'] ?? ''), 200),
            ':description' => (string) ($sku['description'] ?? ''),
            ':category' => $this->limit(implode(' > ', $categoryParts), 200),
            ':applications' => $this->limit((string) ($sku['applications'] ?? ($sku['gridDescription'] ?? '')), 2000),
            ':oem_codes' => json_encode($sku['assemblerCodes'] ?? null, JSON_UNESCAPED_UNICODE),
            ':images' => json_encode($images, JSON_UNESCAPED_UNICODE),
            ':product_url' => $client->productUrl($skuId, $name),
            ':universal' => !empty($sku['universal']) ? 1 : 0,
            ':by_request' => !empty($priceFlags['byRequest']) ? 1 : 0,
            ':by_budget' => !empty($priceFlags['byBudget']) ? 1 : 0,
            ':raw_json' => json_encode($sku, JSON_UNESCAPED_UNICODE),
        ]);

        $offers = $priceData['filtered_prices'] ?? [];
        if ((!is_array($offers) || !$offers) && !empty($priceData['best'])) {
            $offers = [$priceData['best']];
        }

        $this->saveOffers($skuId, is_array($offers) ? $offers : []);
    }

    private function saveOffers(int $skuId, array $offers): void
    {
        $this->pdo->prepare('DELETE FROM cdp_offers WHERE sku_id = ?')->execute([$skuId]);

        $usable = array_values(array_filter($offers, static function ($offer): bool {
            return is_array($offer) && isset($offer['price']) && (float) $offer['price'] > 0;
        }));
        usort($usable, static fn ($a, $b) => (float) $a['price'] <=> (float) $b['price']);

        $best = $usable[0] ?? null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO cdp_offers
                (sku_id, seller_name, seller_type, city, state, price, stock, lead_time,
                 packing_time, partners_part_code, is_best, consulted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        foreach ($usable as $offer) {
            $stmt->execute([
                $skuId,
                $this->limit((string) ($offer['sellerName'] ?? ''), 160),
                $this->limit((string) ($offer['sellerType'] ?? ''), 40),
                $this->limit((string) ($offer['city'] ?? ''), 120),
                strtoupper(substr((string) ($offer['state'] ?? ''), 0, 2)),
                (float) $offer['price'],
                (int) ($offer['stockTotal'] ?? $offer['quantity'] ?? 0),
                (int) ($offer['leadTime'] ?? 0),
                (int) ($offer['packingTime'] ?? 0),
                $this->limit((string) ($offer['partnersPartCode'] ?? ''), 80),
                $best === $offer ? 1 : 0,
            ]);
        }
    }

    /**
     * Busca local (fallback quando o provedor externo falha).
     * @return array{items: array, total: int}
     */
    public function searchLocal(string $term, array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $page = max(1, $page);
        $where = ['1=1'];
        $params = [];

        if ($term !== '') {
            $where[] = '(name LIKE :term OR name_simple LIKE :term OR part_code LIKE :term OR applications LIKE :term)';
            $params[':term'] = '%' . $term . '%';
        }
        if (!empty($filters['brand'])) {
            $where[] = 'brand LIKE :brand';
            $params[':brand'] = '%' . $filters['brand'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM cdp_products WHERE ' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cdp_products WHERE ' . $whereSql
            . ' ORDER BY name LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return ['items' => $this->withOffers($rows), 'total' => $total];
    }

    /** @param array<int, array> $products */
    public function withOffers(array $products): array
    {
        if (!$products) {
            return [];
        }

        $skus = array_map(static fn ($row) => (int) $row['sku_id'], $products);
        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cdp_offers WHERE sku_id IN (' . $placeholders . ') ORDER BY price ASC'
        );
        $stmt->execute($skus);

        $offersBySku = [];
        foreach ($stmt->fetchAll() as $offer) {
            $offersBySku[(int) $offer['sku_id']][] = $offer;
        }

        $result = [];
        foreach ($products as $row) {
            $row['offers'] = $offersBySku[(int) $row['sku_id']] ?? [];
            $result[] = $row;
        }
        return $result;
    }

    /** @return array<int, array{value:string, label:string}> */
    public function makes(string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT slug AS value, name AS label FROM cdp_vehicle_makes WHERE vehicle_type = ? ORDER BY name');
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array{value:string, label:string}> */
    public function models(string $type, string $makeSlug): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.slug AS value, m.name AS label
               FROM cdp_vehicle_models m
               JOIN cdp_vehicle_makes k ON k.id = m.make_id
              WHERE k.vehicle_type = ? AND k.slug = ?
              ORDER BY m.name'
        );
        $stmt->execute([$type, $makeSlug]);
        return $stmt->fetchAll();
    }

    /** @return array<int, int> */
    public function years(string $type, string $makeSlug, string $modelSlug): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT y.year
               FROM cdp_vehicle_years y
               JOIN cdp_vehicle_models m ON m.id = y.model_id
               JOIN cdp_vehicle_makes k ON k.id = m.make_id
              WHERE k.vehicle_type = ? AND k.slug = ? AND m.slug = ?
              ORDER BY y.year DESC'
        );
        $stmt->execute([$type, $makeSlug, $modelSlug]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function ensureMake(string $type, string $slug, string $name): int
    {
        $this->pdo->prepare(
            'INSERT INTO cdp_vehicle_makes (vehicle_type, slug, name) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), id = LAST_INSERT_ID(id)'
        )->execute([$type, $this->limit($slug, 80), $this->limit($name, 120)]);

        return (int) $this->pdo->lastInsertId();
    }

    public function ensureModel(int $makeId, string $slug, string $name): int
    {
        $this->pdo->prepare(
            'INSERT INTO cdp_vehicle_models (make_id, slug, name) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), id = LAST_INSERT_ID(id)'
        )->execute([$makeId, $this->limit($slug, 120), $this->limit($name, 160)]);

        return (int) $this->pdo->lastInsertId();
    }

    public function ensureYear(int $modelId, int $year): void
    {
        $this->pdo->prepare(
            'INSERT IGNORE INTO cdp_vehicle_years (model_id, year) VALUES (?, ?)'
        )->execute([$modelId, $year]);
    }

    private function limit(string $value, int $length): string
    {
        return mb_substr(trim($value), 0, $length);
    }
}
