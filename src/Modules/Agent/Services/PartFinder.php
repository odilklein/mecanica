<?php
// =============================================
// PART FINDER — resolve a peça a partir do veículo.
//
// Cruza:
//  1. ficha técnica curada (óleo, códigos de filtro);
//  2. catálogo local (`parts`, com applications);
//  3. Canal da Peça (busca + preço + estoque + link).
//
// Devolve ofertas pontuadas, com link para conferência e um
// token assinado para o mecânico escolher a correta.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

use Modules\Mechanic\Services\CanalDaPecaClient;
use PDO;
use Throwable;

class PartFinder
{
    private PDO $pdo;
    private VehicleSpecService $specs;
    private CanalDaPecaClient $cdp;

    /**
     * Regras de categoria. A ordem importa: as mais específicas vêm antes.
     *
     * @var array<string, array{label: string, term: string, any: string[], avoid: string[], match: string[]}>
     */
    private const CATEGORIES = [
        'filtro_cabine' => [
            'label' => 'Filtro de cabine',
            'term' => 'filtro de cabine',
            'any' => ['filtro de cabine', 'filtro cabine', 'filtro ar condicionado', 'filtro do ar condicionado'],
            'avoid' => [],
            'match' => ['filtro', 'cabine'],
        ],
        'filtro_oleo' => [
            'label' => 'Filtro de óleo',
            'term' => 'filtro de oleo',
            'any' => ['filtro de oleo', 'filtro oleo', 'filtro do oleo'],
            'avoid' => [],
            'match' => ['filtro', 'oleo'],
        ],
        'filtro_ar' => [
            'label' => 'Filtro de ar',
            'term' => 'filtro de ar',
            'any' => ['filtro de ar', 'filtro ar', 'filtro do ar'],
            'avoid' => ['condicionado'],
            'match' => ['filtro', 'ar'],
        ],
        'filtro_combustivel' => [
            'label' => 'Filtro de combustível',
            'term' => 'filtro de combustivel',
            'any' => ['filtro de combustivel', 'filtro combustivel', 'filtro de diesel', 'filtro diesel'],
            'avoid' => [],
            'match' => ['filtro', 'combustivel'],
        ],
        'oleo_cambio' => [
            'label' => 'Óleo de câmbio',
            'term' => 'oleo de cambio',
            'any' => ['oleo de cambio', 'oleo cambio', 'oleo de transmissao', 'oleo transmissao'],
            'avoid' => [],
            'match' => ['oleo'],
        ],
        'oleo_motor' => [
            'label' => 'Óleo do motor',
            'term' => 'oleo motor',
            'any' => ['oleo'],
            'avoid' => [
                'cambio', 'transmiss', 'freio', 'hidraul', 'direcao', 'arrefec', 'diferencial',
                'filtro', 'tampa', 'bujao', 'carter', 'medidor', 'sensor', 'bomba', 'radiador',
            ],
            'match' => ['oleo'],
        ],
        'freio' => [
            'label' => 'Freios',
            'term' => 'pastilha de freio',
            'any' => ['pastilha', 'disco de freio', 'lona', 'freio', 'pinca'],
            'avoid' => [],
            'match' => ['freio'],
        ],
        'suspensao' => [
            'label' => 'Suspensão',
            'term' => 'amortecedor',
            'any' => ['amortecedor', 'mola', 'bandeja', 'pivo', 'bieleta', 'batente', 'coxim'],
            'avoid' => [],
            'match' => ['amortecedor'],
        ],
        'correia' => [
            'label' => 'Correias e polias',
            'term' => 'correia',
            'any' => ['correia', 'tensionador', 'polia'],
            'avoid' => [],
            'match' => ['correia'],
        ],
        'vela' => [
            'label' => 'Ignição',
            'term' => 'vela de ignicao',
            'any' => ['vela', 'ignicao', 'bobina'],
            'avoid' => [],
            'match' => ['vela'],
        ],
        'bateria' => [
            'label' => 'Bateria',
            'term' => 'bateria',
            'any' => ['bateria'],
            'avoid' => [],
            'match' => ['bateria'],
        ],
        'arrefecimento' => [
            'label' => 'Arrefecimento',
            'term' => 'radiador',
            'any' => ['radiador', 'termostato', 'bomba de agua', 'ventoinha', 'aditivo'],
            'avoid' => [],
            'match' => ['radiador'],
        ],
    ];

    public function __construct(PDO $pdo, VehicleSpecService $specs)
    {
        $this->pdo = $pdo;
        $this->specs = $specs;
        $this->cdp = new CanalDaPecaClient();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function vehicleFromOrder(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.* FROM service_orders so JOIN vehicles v ON v.id = so.vehicle_id WHERE so.id = :id'
        );
        $stmt->execute([':id' => $orderId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateVehicle($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function vehicleFromPlate(string $plate): ?array
    {
        $plate = strtoupper(trim($plate));
        if ($plate === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM vehicles WHERE plate = :p LIMIT 1');
        $stmt->execute([':p' => $plate]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateVehicle($row) : null;
    }

    /**
     * @param array<string, mixed> $vehicle
     * @return array<string, mixed>
     */
    public function find(
        array $vehicle,
        string $description,
        ?string $category = null,
        ?int $orderId = null,
        int $quantity = 1
    ): array {
        $categoryKey = $this->category($description, $category);
        $rule = self::CATEGORIES[$categoryKey] ?? null;
        $spec = $this->specs->forVehicle($vehicle);
        $specInfo = $this->specs->describe($spec);

        $offers = [];
        $notes = [];

        foreach ($this->localOffers($vehicle, $categoryKey, $description) as $offer) {
            $offers[] = $offer;
        }

        try {
            foreach ($this->cdpOffers($vehicle, $categoryKey, $description) as $offer) {
                $offers[] = $offer;
            }
        } catch (Throwable $e) {
            error_log('[agent:partfinder] ' . $e->getMessage());
            $notes[] = 'O Canal da Peça não respondeu agora; mostrando o catálogo local.';
        }

        $scored = [];
        foreach ($offers as $offer) {
            $score = $this->score($offer, $vehicle, $categoryKey, $spec);
            if ($score === null) {
                continue;
            }
            $offer['score'] = $score;
            $offer['fitment'] = $score >= 70 ? 'compatível' : ($score >= 40 ? 'provável' : 'verificar');
            $scored[] = $offer;
        }

        usort($scored, static function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return ($a['price'] ?? PHP_INT_MAX) <=> ($b['price'] ?? PHP_INT_MAX);
            }

            return $b['score'] <=> $a['score'];
        });

        $scored = $this->dedupe($scored);
        $scored = array_slice($scored, 0, 6);

        foreach ($scored as &$offer) {
            $offer['token'] = $orderId !== null && $orderId > 0
                ? OfferToken::sign([
                    'os' => $orderId,
                    'q' => max(1, $quantity),
                    'item' => [
                        'description' => $offer['title'],
                        'sku' => $offer['code'] ?? null,
                        'unit_price' => $offer['price'],
                        'supplier' => $offer['source'],
                        'product_url' => $offer['url'] ?? null,
                    ],
                ])
                : null;
            unset($offer['score']);
        }
        unset($offer);

        if (!$scored) {
            $notes[] = 'Não encontrei oferta com evidência suficiente de compatibilidade. '
                . 'Tente descrever a peça com mais detalhe (posição, lado, motor).';
        }

        if ($rule !== null && $categoryKey === 'oleo_motor' && empty($specInfo['known'])) {
            $notes[] = 'A ficha técnica deste veículo não está cadastrada. Confirme a viscosidade no manual '
                . 'antes de aprovar o pedido.';

            $viscosities = [];
            foreach ($scored as $candidate) {
                if (preg_match('/\b(0W\d{2}|5W\d{2}|10W\d{2}|15W\d{2}|20W\d{2})\b/i', $candidate['title'], $match)) {
                    $viscosities[strtoupper($match[1])] = true;
                }
            }
            if ($viscosities) {
                $notes[] = 'Viscosidades encontradas nas ofertas: ' . implode(', ', array_keys($viscosities))
                    . '. O manual define qual é a correta.';
            }
        }

        return [
            'vehicle' => $vehicle,
            'spec' => $specInfo,
            'category' => [
                'key' => $categoryKey,
                'label' => $rule['label'] ?? 'Peça',
            ],
            'offers' => $scored,
            'links' => $this->searchLinks($vehicle, $description, $categoryKey),
            'notes' => $notes,
            'order_id' => $orderId,
        ];
    }

    /**
     * @param array<string, mixed> $vehicle
     * @return array<int, array<string, mixed>>
     */
    private function localOffers(array $vehicle, string $category, string $description): array
    {
        $rule = self::CATEGORIES[$category] ?? null;
        $term = $rule['term'] ?? trim($description);
        $like = '%' . $this->normalize($term) . '%';

        $stmt = $this->pdo->prepare(
            'SELECT id, name, brand, part_code, cdp_sku, price, stock, product_url, image, applications, category
               FROM parts
              WHERE is_active = 1
                AND (
                    LOWER(name) LIKE :t1
                    OR LOWER(COALESCE(applications, "")) LIKE :t2
                    OR LOWER(COALESCE(category, "")) LIKE :t3
                )
              ORDER BY (stock > 0) DESC, price ASC
              LIMIT 40'
        );
        $stmt->execute([':t1' => $like, ':t2' => $like, ':t3' => $like]);

        $offers = [];
        foreach ($stmt->fetchAll() as $row) {
            $offers[] = [
                'source' => 'Catálogo local',
                'title' => (string) $row['name'],
                'brand' => (string) ($row['brand'] ?? ''),
                'code' => (string) ($row['part_code'] ?? $row['cdp_sku'] ?? ''),
                'price' => $row['price'] !== null ? (float) $row['price'] : null,
                'stock' => (int) ($row['stock'] ?? 0),
                'url' => (string) ($row['product_url'] ?? '') ?: '/parts/' . (int) $row['id'],
                'image' => (string) ($row['image'] ?? ''),
                'applications' => (string) ($row['applications'] ?? ''),
                'category_text' => (string) ($row['category'] ?? ''),
                'match_text' => (string) $row['name'] . ' ' . (string) ($row['applications'] ?? '')
                    . ' ' . (string) ($row['category'] ?? ''),
            ];
        }

        return $offers;
    }

    /**
     * @param array<string, mixed> $vehicle
     * @return array<int, array<string, mixed>>
     */
    private function cdpOffers(array $vehicle, string $category, string $description): array
    {
        $rule = self::CATEGORIES[$category] ?? null;
        $term = $rule['term'] ?? trim($description);

        $filters = array_filter([
            'vehicle' => 'carro',
            'make' => $vehicle['make_slug'],
            'model' => $vehicle['model_slug'],
            'year' => $vehicle['year'] ? (string) $vehicle['year'] : '',
        ], static fn (string $value): bool => $value !== '');

        $data = $this->cdp->search($term, $filters);
        $skus = is_array($data['skus'] ?? null) ? $data['skus'] : [];
        if (!$skus) {
            return [];
        }

        $ids = array_values(array_filter(array_map(
            static fn (array $sku): int => (int) ($sku['id'] ?? 0),
            $skus
        )));
        $prices = $ids ? $this->cdp->prices($ids) : [];

        $offers = [];
        foreach ($skus as $sku) {
            $id = (int) ($sku['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $priceData = is_array($prices[$id] ?? null) ? $prices[$id] : [];
            $best = $this->bestPrice($priceData);

            $fullName = (string) ($sku['name'] ?? '');
            $displayName = trim((string) ($sku['nameSimple'] ?? ''));
            if ($displayName === '') {
                $displayName = $fullName;
            }
            $categoryText = implode(' > ', array_filter([
                (string) ($sku['category1'] ?? ''),
                (string) ($sku['category2'] ?? ''),
                (string) ($sku['category3'] ?? ''),
            ]));

            $image = '';
            if (!empty($sku['imageSet']['baseUrl'])) {
                $paths = (array) ($sku['imageSet']['paths'] ?? []);
                $image = (string) $sku['imageSet']['baseUrl'] . (string) ($paths[0] ?? '');
            }

            $offers[] = [
                'source' => 'Canal da Peça',
                'title' => $displayName,
                'match_text' => $fullName . ' ' . (string) ($sku['applications'] ?? '') . ' ' . $categoryText,
                'brand' => (string) ($sku['brand'] ?? ''),
                'code' => (string) ($sku['partCode'] ?? ''),
                'price' => $best['price'],
                'stock' => $best['stock'],
                'url' => $this->cdp->productUrl($id, $fullName !== '' ? $fullName : $displayName),
                'image' => $image,
                'applications' => (string) ($sku['applications'] ?? ''),
                'category_text' => $categoryText,
                'sku' => $id,
            ];
        }

        return $offers;
    }

    /**
     * @param array<string, mixed> $priceData
     * @return array{price: float|null, stock: int|null}
     */
    private function bestPrice(array $priceData): array
    {
        $rows = $priceData['filtered_prices'] ?? [];
        if (!is_array($rows) || !$rows) {
            return ['price' => null, 'stock' => null];
        }

        $best = null;
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['price'])) {
                continue;
            }
            if ($best === null || (float) $row['price'] < (float) $best['price']) {
                $best = $row;
            }
        }

        if ($best === null) {
            return ['price' => null, 'stock' => null];
        }

        return [
            'price' => (float) $best['price'],
            'stock' => isset($best['realQuantity']) ? (int) $best['realQuantity'] : null,
        ];
    }

    /**
     * Score de compatibilidade. null = descartado.
     *
     * @param array<string, mixed> $offer
     * @param array<string, mixed> $vehicle
     * @param array<string, mixed>|null $spec
     */
    private function score(array $offer, array $vehicle, string $category, ?array $spec): ?int
    {
        $text = (string) ($offer['match_text'] ?? '')
            . ' ' . ($offer['title'] ?? '')
            . ' ' . ($offer['applications'] ?? '')
            . ' ' . ($offer['category_text'] ?? '');
        $haystack = $this->normalize($text);

        $rule = self::CATEGORIES[$category] ?? null;
        if ($rule !== null) {
            foreach ($rule['avoid'] as $avoid) {
                if (str_contains($haystack, $avoid)) {
                    return null;
                }
            }
            foreach ($rule['match'] as $term) {
                if (!str_contains($haystack, $term)) {
                    return null;
                }
            }
        }

        $model = (string) ($vehicle['model_slug'] ?? '');
        $year = (int) ($vehicle['year'] ?? 0);
        $engine = (string) ($vehicle['engine'] ?? '');

        $modelInText = $model !== '' && str_contains($haystack, $model);

        $range = $this->yearRange((string) ($offer['applications'] ?? ''));
        if ($range === null) {
            $range = $this->yearRange((string) ($offer['title'] ?? ''));
        }
        $yearFits = $range !== null && $year > 0
            && $year >= $range[0] - 1
            && $year <= $range[1] + 1;

        // Óleo e bateria são universais: o ano não invalida, só desqualifica.
        $universal = in_array($category, ['oleo_motor', 'oleo_cambio', 'bateria', 'outros'], true);

        if ($range !== null && !$yearFits) {
            if (!$universal) {
                return null;
            }
        }

        // Peça específica precisa de evidência do veículo E do ano.
        if (!$universal) {
            if (!$modelInText) {
                return null;
            }
            if ($range !== null && !$yearFits) {
                return null;
            }
        }

        $score = 20;

        if ($modelInText) {
            $score += 30;
        }
        if ($yearFits) {
            $score += 25;
        }
        if ($range !== null && !$yearFits) {
            $score -= 35;
        }

        if ($engine !== '' && str_contains($haystack, str_replace('.', ' ', $engine))) {
            $score += 10;
        }

        if ($category === 'oleo_motor' && $spec && !empty($spec['oil_viscosity'])) {
            $viscosity = $this->normalize((string) $spec['oil_viscosity']);
            if ($viscosity !== '' && str_contains($haystack, $viscosity)) {
                $score += 30;
            } elseif ($viscosity !== '') {
                $score -= 20;
            }
        }

        if (!empty($offer['brand'])) {
            $score += 5;
        }
        if (($offer['stock'] ?? 0) > 0) {
            $score += 5;
        }
        if ($offer['price'] === null) {
            $score -= 10;
        }

        return max(0, min(100, $score));
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function yearRange(string $text): ?array
    {
        if (!preg_match_all('/(19|20)\d{2}/', $text, $matches) || !$matches[0]) {
            return null;
        }

        $years = array_map('intval', $matches[0]);

        return [min($years), max($years)];
    }

    /**
     * @param array<int, array<string, mixed>> $offers
     * @return array<int, array<string, mixed>>
     */
    private function dedupe(array $offers): array
    {
        $seen = [];
        $result = [];

        foreach ($offers as $offer) {
            $key = strtolower(trim(($offer['brand'] ?? '') . '|' . ($offer['title'] ?? '')));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $offer;
        }

        return $result;
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function searchLinks(array $vehicle, string $description, string $category): array
    {
        $rule = self::CATEGORIES[$category] ?? null;
        $term = $rule['term'] ?? $description;
        $query = trim(
            $term . ' ' . ($vehicle['make_slug'] ?? $vehicle['brand'])
            . ' ' . ($vehicle['model_slug'] ?? $vehicle['model'])
            . ' ' . ($vehicle['year'] ?? '')
        );

        return [
            [
                'label' => 'Mercado Livre',
                'url' => 'https://lista.mercadolivre.com.br/' . rawurlencode(str_replace(' ', '-', trim($query))),
            ],
            [
                'label' => 'Google Shopping',
                'url' => 'https://www.google.com/search?tbm=shop&q=' . rawurlencode($query),
            ],
            [
                'label' => 'Canal da Peça',
                'url' => 'https://www.canaldapeca.com.br/busca?q=' . rawurlencode($term),
            ],
        ];
    }

    private function category(string $description, ?string $requested): string
    {
        if ($requested !== null && isset(self::CATEGORIES[$requested])) {
            return $requested;
        }

        $text = ' ' . $this->normalize($description) . ' ';

        foreach (self::CATEGORIES as $key => $rule) {
            foreach ($rule['any'] as $term) {
                if (!str_contains($text, $term)) {
                    continue;
                }

                $blocked = false;
                foreach ($rule['avoid'] as $avoid) {
                    if (str_contains($text, $avoid)) {
                        $blocked = true;
                        break;
                    }
                }

                if (!$blocked) {
                    return $key;
                }
            }
        }

        return 'outros';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateVehicle(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'plate' => (string) ($row['plate'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'model' => (string) ($row['model'] ?? ''),
            'year' => $row['year'] !== null ? (int) $row['year'] : 0,
            'make_slug' => VehicleSpecService::makeSlug((string) ($row['brand'] ?? '')),
            'model_slug' => VehicleSpecService::modelSlug((string) ($row['model'] ?? '')),
            'engine' => VehicleSpecService::engine((string) ($row['model'] ?? '')),
        ];
    }

    private function normalize(string $value): string
    {
        return VehicleSpecService::normalize($value);
    }
}
