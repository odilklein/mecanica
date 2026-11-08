<?php
// =============================================
// API INTERNA — busca de peças no catálogo
// Canal da Peça + fallback no catálogo local.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Modules\Mechanic\Services\CanalDaPecaClient;
use Modules\Mechanic\Services\PartsCatalog;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

class PartsController extends Controller
{
    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    public function search(ServerRequestInterface $request)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $query = $request->getQueryParams();
        $term = clean($query['q'] ?? '');
        $page = max(1, (int) ($query['page'] ?? 1));
        $brand = clean($query['brand'] ?? '');

        $filters = array_filter([
            'vehicle' => clean($query['vehicle'] ?? ''),
            'make' => clean($query['make'] ?? ''),
            'model' => clean($query['model'] ?? ''),
            'year' => clean($query['year'] ?? ''),
            'brand' => $brand,
        ], static fn ($value): bool => $value !== '');

        $client = new CanalDaPecaClient();
        $catalog = new PartsCatalog($this->pdo);

        try {
            $data = $client->search($term, $filters, $page);
            $skus = $data['skus'] ?? [];

            $ids = array_values(array_filter(array_map(
                static fn ($sku) => (int) ($sku['id'] ?? 0),
                is_array($skus) ? $skus : []
            )));

            $prices = $ids ? $client->prices($ids) : [];

            $results = [];
            foreach ($skus as $sku) {
                $id = (int) ($sku['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $priceData = is_array($prices[$id] ?? null) ? $prices[$id] : [];
                $catalog->upsertProduct($sku, $priceData, $client);
                $results[] = $this->normalizeExternal($sku, $priceData, $client);
            }

            return json_response([
                'source' => 'external',
                'term' => $term,
                'page' => $page,
                'total' => (int) ($data['total'] ?? 0),
                'per_page' => 6,
                'filters' => $this->normalizeFilters($data['filters'] ?? []),
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            error_log('Parts search fallback: ' . $e->getMessage());

            $local = $catalog->searchLocal($term, $filters, $page, 12);
            $results = array_map([$this, 'normalizeLocal'], $local['items']);

            return json_response([
                'source' => 'local',
                'term' => $term,
                'page' => $page,
                'total' => $local['total'],
                'per_page' => 12,
                'filters' => ['brands' => [], 'categories' => []],
                'results' => $results,
                'warning' => 'Provedor externo indisponível; exibindo catálogo local.',
            ]);
        }
    }

    /** Autocomplete local de peças (sugestão; texto livre continua permitido). */
    public function autocomplete(ServerRequestInterface $request)
    {
        if (!$this->auth->check()) {
            return json_response(['results' => []], 401);
        }

        $query = $request->getQueryParams();
        $term = clean($query['q'] ?? '');
        if (mb_strlen($term) < 2) {
            return json_response(['results' => []]);
        }

        $limit = min(20, max(5, (int) ($query['limit'] ?? 10)));
        $stmt = $this->pdo->prepare(
            "SELECT id, name, brand, part_code, category, price, cdp_sku, product_url, image
               FROM parts
              WHERE is_active = 1
                AND (name LIKE :q1 OR brand LIKE :q2 OR part_code LIKE :q3 OR category LIKE :q4)
              ORDER BY name
              LIMIT {$limit}"
        );
        $like = '%' . $term . '%';
        $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like]);

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $price = $row['price'] !== null ? (float) $row['price'] : null;
            $meta = trim(
                ($row['brand'] ?? '') . (($row['part_code'] ?? '') ? ' · ' . $row['part_code'] : '')
            );
            $label = (string) $row['name'];
            if ($meta !== '') {
                $label .= ' — ' . $meta;
            }
            if ($price !== null) {
                $label .= ' · R$ ' . number_format($price, 2, ',', '.');
            }

            $results[] = [
                'id' => (int) $row['id'],
                'text' => $label,
                'name' => (string) $row['name'],
                'brand' => (string) ($row['brand'] ?? ''),
                'part_code' => (string) ($row['part_code'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'price' => $price,
                'price_label' => $price !== null ? 'R$ ' . number_format($price, 2, ',', '.') : 'Sob consulta',
                'sku' => $row['cdp_sku'] !== null ? (string) $row['cdp_sku'] : null,
                'product_url' => (string) ($row['product_url'] ?? ''),
                'image' => (string) ($row['image'] ?? ''),
            ];
        }

        return json_response(['results' => $results]);
    }

    public function vehicles(ServerRequestInterface $request)
    {        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $query = $request->getQueryParams();
        $type = clean($query['type'] ?? 'carro') ?: 'carro';
        $make = clean($query['make'] ?? '');
        $model = clean($query['model'] ?? '');

        $catalog = new PartsCatalog($this->pdo);
        $client = new CanalDaPecaClient();

        if ($make === '') {
            $items = $catalog->makes($type);
            if (!$items) {
                try {
                    $data = $client->vehicles($type);
                    $items = array_map(static fn ($item) => [
                        'value' => (string) ($item['value'] ?? ''),
                        'label' => (string) ($item['displayValue'] ?? ''),
                    ], $data['items'] ?? []);
                } catch (\Throwable $e) {
                    $items = [];
                }
            }
            return json_response(['type' => $type, 'makes' => $items]);
        }

        if ($model === '') {
            $items = $catalog->models($type, $make);
            if (!$items) {
                try {
                    $data = $client->vehicles($type, $make);
                    $items = array_map(static fn ($item) => [
                        'value' => (string) ($item['value'] ?? ''),
                        'label' => (string) ($item['displayValue'] ?? ''),
                    ], $data['items'] ?? []);
                } catch (\Throwable $e) {
                    $items = [];
                }
            }
            return json_response(['type' => $type, 'make' => $make, 'models' => $items]);
        }

        $years = $catalog->years($type, $make, $model);
        if (!$years) {
            try {
                $data = $client->vehicles($type, $make, $model);
                $years = array_map(static fn ($item) => (int) ($item['value'] ?? 0), $data['items'] ?? []);
                $years = array_values(array_filter($years));
                rsort($years);
            } catch (\Throwable $e) {
                $years = [];
            }
        }

        return json_response(['type' => $type, 'make' => $make, 'model' => $model, 'years' => $years]);
    }

    private function normalizeExternal(array $sku, array $priceData, CanalDaPecaClient $client): array
    {
        $images = [];
        $imageSet = $sku['imageSet'] ?? null;
        if (is_array($imageSet)) {
            $base = rtrim((string) ($imageSet['baseUrl'] ?? ''), '/');
            foreach (($imageSet['paths'] ?? []) as $path) {
                $images[] = $base . '/' . ltrim((string) $path, '/');
            }
        }

        $best = $this->bestOffer($priceData);
        $offerCount = count($priceData['filtered_prices'] ?? []);

        return [
            'sku' => (int) ($sku['id'] ?? 0),
            'name' => (string) ($sku['name'] ?? ''),
            'name_simple' => (string) ($sku['nameSimple'] ?? ''),
            'brand' => (string) ($sku['brand'] ?? ''),
            'part_code' => (string) ($sku['partCode'] ?? ''),
            'category' => trim(($sku['category1'] ?? '') . ' > ' . ($sku['category2'] ?? ''), ' >'),
            'applications' => (string) ($sku['applications'] ?? ($sku['gridDescription'] ?? '')),
            'oem_codes' => $sku['assemblerCodes'] ?? null,
            'image' => $images[0] ?? null,
            'url' => $client->productUrl((int) ($sku['id'] ?? 0), (string) ($sku['name'] ?? 'Produto')),
            'universal' => !empty($sku['universal']),
            'by_request' => !empty($sku['price']['byRequest']),
            'by_budget' => !empty($sku['price']['byBudget']),
            'price' => $best ? [
                'from' => (float) $best['price'],
                'seller' => (string) ($best['sellerName'] ?? ''),
                'city' => (string) ($best['city'] ?? ''),
                'state' => (string) ($best['state'] ?? ''),
                'stock' => (int) ($best['stockTotal'] ?? $best['quantity'] ?? 0),
                'lead_time' => (int) ($best['leadTime'] ?? 0),
                'offers' => $offerCount,
                'consulted_at' => date('c'),
            ] : null,
        ];
    }

    private function normalizeLocal(array $row): array
    {
        $images = json_decode((string) ($row['images'] ?? '[]'), true) ?: [];
        $offers = $row['offers'] ?? [];
        $best = $offers[0] ?? null;

        return [
            'sku' => (int) $row['sku_id'],
            'name' => (string) $row['name'],
            'name_simple' => (string) ($row['name_simple'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'part_code' => (string) ($row['part_code'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'applications' => (string) ($row['applications'] ?? ''),
            'oem_codes' => json_decode((string) ($row['oem_codes'] ?? 'null'), true),
            'image' => $images[0] ?? null,
            'url' => (string) ($row['product_url'] ?? ''),
            'universal' => (bool) ($row['universal'] ?? false),
            'by_request' => (bool) ($row['by_request'] ?? false),
            'by_budget' => (bool) ($row['by_budget'] ?? false),
            'price' => $best ? [
                'from' => (float) $best['price'],
                'seller' => (string) ($best['seller_name'] ?? ''),
                'city' => (string) ($best['city'] ?? ''),
                'state' => (string) ($best['state'] ?? ''),
                'stock' => (int) ($best['stock'] ?? 0),
                'lead_time' => (int) ($best['lead_time'] ?? 0),
                'offers' => count($offers),
                'consulted_at' => (string) ($best['consulted_at'] ?? ''),
            ] : null,
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $brands = [];
        foreach (($filters['brands'] ?? []) as $brand) {
            $brands[] = [
                'value' => (string) ($brand['value'] ?? $brand['term'] ?? ''),
                'label' => (string) ($brand['displayValue'] ?? $brand['term'] ?? ''),
            ];
        }

        $categories = [];
        $categoryValues = $filters['categories']['values'] ?? [];
        foreach ($categoryValues as $category) {
            $categories[] = [
                'value' => (string) ($category['value'] ?? ''),
                'label' => (string) ($category['displayValue'] ?? ''),
            ];
        }

        return ['brands' => $brands, 'categories' => $categories];
    }

    private function bestOffer(array $priceData): ?array
    {
        $offers = $priceData['filtered_prices'] ?? [];
        if (is_array($offers) && $offers) {
            $valid = array_values(array_filter($offers, static function ($offer): bool {
                return is_array($offer) && (float) ($offer['price'] ?? 0) > 0;
            }));
            usort($valid, static fn ($a, $b) => (float) $a['price'] <=> (float) $b['price']);
            if ($valid) {
                return $valid[0];
            }
        }

        return is_array($priceData['best'] ?? null) ? $priceData['best'] : null;
    }
}
