<?php
// =============================================
// IMPORTAÇÃO — catálogo Canal da Peça
//
// Uso (dentro do container):
//   php bin/import_parts.php all            (veículos + produtos + sync)
//   php bin/import_parts.php vehicles
//   php bin/import_parts.php products [paginas]
//   php bin/import_parts.php crawl [paginas]  (varredura profunda)
//   php bin/import_parts.php daemon [paginas] (ciclos contínuos)
//   php bin/import_parts.php reclassify     (corrige categorias antigas)
//   php bin/import_parts.php sync
//
// Respeita 1 requisição/segundo e usa cache em
// storage/cache/canaldapeca. Dados de catálogo,
// sem dados pessoais.
//
// Daemon: CDP_INTERVAL define o intervalo entre
// ciclos em segundos (padrão 21600 = 6 horas).
// =============================================

declare(strict_types=1);

use Kernel\Database\Connection;
use Modules\Mechanic\Services\CanalDaPecaClient;
use Modules\Mechanic\Services\PartsCatalog;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
if (is_file($basePath . '/.env')) {
    Dotenv\Dotenv::createImmutable($basePath)->safeLoad();
}

$command = $argv[1] ?? 'all';
$pdo = Connection::fromEnv();
$client = new CanalDaPecaClient();
$catalog = new PartsCatalog($pdo);

/** @var array<string, string[]> $popularModels */
$popularModels = [
    'VOLKSWAGEN' => ['GOL', 'POLO', 'T-CROSS', 'SAVEIRO', 'VOYAGE', 'UP'],
    'FIAT' => ['ARGO', 'MOBI', 'CRONOS', 'TORO', 'STRADA', 'UNO'],
    'CHEVROLET' => ['ONIX', 'TRACKER', 'S10', 'SPIN', 'MONTANA'],
    'FORD' => ['KA', 'ECOSPORT', 'RANGER'],
    'TOYOTA' => ['COROLLA', 'YARIS', 'ETIOS', 'HILUX'],
    'HONDA' => ['CIVIC', 'CITY', 'HR-V', 'FIT'],
    'HYUNDAI' => ['HB20', 'CRETA', 'TUCSON'],
    'RENAULT' => ['SANDERO', 'LOGAN', 'DUSTER', 'KWID'],
    'NISSAN' => ['KICKS', 'VERSA', 'MARCH'],
    'JEEP' => ['RENEGADE', 'COMPASS'],
];

$popularTerms = [
    'pneu aro 13', 'pneu aro 14', 'pneu aro 15', 'pneu aro 16', 'pneu aro 17', 'pneu aro 18',
    'pastilha de freio', 'disco de freio', 'filtro de óleo', 'filtro de ar',
    'filtro de combustível', 'filtro de cabine', 'amortecedor', 'bateria', 'correia dentada',
    'vela de ignição', 'óleo motor', 'palheta', 'lâmpada automotiva',
    'radiador', 'bomba d\'água', 'embreagem', 'kit embreagem',
    'rolamento de roda', 'bucha de suspensão', 'pivô de suspensão', 'terminal de direção',
    'sensor de oxigênio', 'sonda lambda', 'bobina de ignição', 'cabo de vela',
    'bomba de combustível', 'junta do motor', 'retentor', 'termostato',
    'farol', 'lanterna', 'retrovisor', 'compressor do ar condicionado',
    'escapamento', 'silencioso', 'alternador', 'motor de partida',
    'fluido de freio', 'aditivo radiador',
];

function importVehicles(CanalDaPecaClient $client, PartsCatalog $catalog, array $popularModels): void
{
    echo "Importando árvore de veículos (carro)...\n";

    $makesResponse = $client->vehicles('carro');
    $makes = $makesResponse['items'] ?? [];
    $selected = [];

    foreach ($makes as $make) {
        $display = mb_strtoupper((string) ($make['displayValue'] ?? ''));
        foreach ($popularModels as $needle => $_) {
            if (str_contains($display, $needle)) {
                $selected[] = $make;
                break;
            }
        }
    }

    foreach ($selected as $make) {
        $makeSlug = (string) ($make['value'] ?? '');
        $makeName = (string) ($make['displayValue'] ?? $makeSlug);
        if ($makeSlug === '') {
            continue;
        }

        $makeId = $catalog->ensureMake('carro', $makeSlug, $makeName);
        echo "  {$makeName}\n";

        $modelsResponse = $client->vehicles('carro', $makeSlug);
        $models = $modelsResponse['items'] ?? [];
        $keywords = [];

        foreach ($popularModels as $needle => $terms) {
            if (str_contains(mb_strtoupper($makeName), $needle)) {
                $keywords = $terms;
                break;
            }
        }

        foreach ($models as $model) {
            $modelName = mb_strtoupper((string) ($model['displayValue'] ?? ''));
            $match = false;
            foreach ($keywords as $keyword) {
                if (str_contains($modelName, $keyword)) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                continue;
            }

            $modelSlug = (string) ($model['value'] ?? '');
            $modelDisplay = (string) ($model['displayValue'] ?? $modelSlug);
            if ($modelSlug === '') {
                continue;
            }

            $modelId = $catalog->ensureModel($makeId, $modelSlug, $modelDisplay);
            $yearsResponse = $client->vehicles('carro', $makeSlug, $modelSlug);
            $years = $yearsResponse['items'] ?? [];

            $count = 0;
            foreach ($years as $yearItem) {
                $year = (int) ($yearItem['value'] ?? 0);
                if ($year > 1900) {
                    $catalog->ensureYear($modelId, $year);
                    $count++;
                }
            }

            echo "    {$modelDisplay}: {$count} anos\n";
        }
    }
}

function importProducts(CanalDaPecaClient $client, PartsCatalog $catalog, array $terms, int $pages = 2, ?callable $afterEachTerm = null): void
{
    echo "Importando produtos populares ({$pages} página(s) por termo)...\n";

    foreach ($terms as $term) {
        $imported = 0;

        for ($page = 1; $page <= $pages; $page++) {
            try {
                $search = $client->search($term, [], $page);
            } catch (Throwable $e) {
                echo "  [erro] {$term} p{$page}: {$e->getMessage()}\n";
                continue;
            }

            $skus = $search['skus'] ?? [];
            if (!$skus) {
                break;
            }

            $ids = array_map(static fn ($sku) => (int) ($sku['id'] ?? 0), $skus);
            try {
                $prices = $client->prices($ids);
            } catch (Throwable $e) {
                $prices = [];
            }

            foreach ($skus as $sku) {
                $id = (int) ($sku['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $catalog->upsertProduct($sku, is_array($prices[$id] ?? null) ? $prices[$id] : [], $client);
                $imported++;
            }
        }

        echo "  {$term}: {$imported} produtos\n";

        if ($afterEachTerm !== null) {
            $afterEachTerm($term);
        }
    }
}



function seedCommonParts(PDO $pdo): void
{
    $common = [
        ['Pneu Aro 13', 'Pneus'],
        ['Pneu Aro 14', 'Pneus'],
        ['Pneu Aro 15', 'Pneus'],
        ['Pneu Aro 16', 'Pneus'],
        ['Pneu Aro 17', 'Pneus'],
        ['Pneu 175/70 R14', 'Pneus'],
        ['Pneu 175/65 R14', 'Pneus'],
        ['Pneu 185/60 R15', 'Pneus'],
        ['Pneu 185/65 R15', 'Pneus'],
        ['Pneu 195/55 R16', 'Pneus'],
        ['Pneu 205/55 R16', 'Pneus'],
    ];

    $exists = $pdo->prepare('SELECT id FROM parts WHERE name = ? AND brand IS NULL AND part_code IS NULL LIMIT 1');
    $insert = $pdo->prepare(
        "INSERT INTO parts (name, brand, part_code, category, price, source, is_active)
         VALUES (?, NULL, NULL, ?, NULL, 'manual', 1)"
    );

    foreach ($common as [$name, $category]) {
        $exists->execute([$name]);
        if (!$exists->fetchColumn()) {
            $insert->execute([$name, $category]);
        }
    }
}

function syncParts(PDO $pdo, PartsCatalog $catalog): array
{
    $result = $catalog->syncFromCatalog();
    $result['total'] = (int) $pdo->query('SELECT COUNT(*) FROM parts')->fetchColumn();
    return $result;
}

/** Garante instância única do importador (evita dois processos ao mesmo tempo). */
function acquireLock(): void
{
    $dir = dirname(__DIR__) . '/storage/cache/canaldapeca';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }

    $handle = @fopen($dir . '/import.lock', 'c');
    if ($handle === false) {
        return;
    }
    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fwrite(STDERR, "Já existe uma importação em andamento ({$dir}/import.lock).\n");
        exit(1);
    }

    $GLOBALS['__cdp_lock'] = $handle;
}

function catalogSummary(PDO $pdo): void
{
    $products = (int) $pdo->query('SELECT COUNT(*) FROM cdp_products')->fetchColumn();
    $offers = (int) $pdo->query('SELECT COUNT(*) FROM cdp_offers')->fetchColumn();
    $parts = (int) $pdo->query('SELECT COUNT(*) FROM parts')->fetchColumn();
    $classified = (int) $pdo->query('SELECT COUNT(*) FROM parts WHERE category_id IS NOT NULL')->fetchColumn();
    $makes = (int) $pdo->query('SELECT COUNT(*) FROM cdp_vehicle_makes')->fetchColumn();
    $models = (int) $pdo->query('SELECT COUNT(*) FROM cdp_vehicle_models')->fetchColumn();
    $years = (int) $pdo->query('SELECT COUNT(*) FROM cdp_vehicle_years')->fetchColumn();

    echo "\nResumo do catálogo:\n";
    echo "  Produtos (bruto): {$products}\n";
    echo "  Ofertas:          {$offers}\n";
    echo "  Peças publicadas: {$parts} ({$classified} classificadas)\n";
    echo "  Veículos:         {$makes} marcas | {$models} modelos | {$years} anos\n";
}

function runDaemon(
    CanalDaPecaClient $client,
    PartsCatalog $catalog,
    PDO $pdo,
    array $terms,
    int $pages,
    int $interval
): void {
    do {
        echo '[' . date('c') . "] Início do ciclo de importação\n";
        seedCommonParts($pdo);

        importProducts($client, $catalog, $terms, $pages, static function () use ($pdo, $catalog): void {
            $result = $catalog->syncFromCatalog();
            printf("    sync: +%d novas, %d atualizadas, %d classificadas\n",
                $result['inserted'], $result['updated'], $result['classified']);
        });

        $result = $catalog->syncFromCatalog();
        $parts = (int) $pdo->query('SELECT COUNT(*) FROM parts')->fetchColumn();

        echo '[' . date('c') . "] Fim do ciclo: +{$result['inserted']} nova(s), "
            . "{$result['updated']} atualizada(s), total {$parts} peça(s).\n";
        echo "Aguardando {$interval} segundo(s) para o próximo ciclo.\n";
        flush();
        sleep($interval);
    } while (true);
}

try {
    $pages = isset($argv[2]) ? max(1, (int) $argv[2]) : null;

    if ($command === 'all' || $command === 'vehicles' || $command === 'products'
        || $command === 'crawl' || $command === 'daemon'
    ) {
        acquireLock();
    }

    if ($command === 'all' || $command === 'vehicles') {
        importVehicles($client, $catalog, $popularModels);
    }

    if ($command === 'all' || $command === 'products') {
        importProducts($client, $catalog, $popularTerms, $pages ?? 3);
    }

    if ($command === 'crawl') {
        seedCommonParts($pdo);
        importProducts($client, $catalog, $popularTerms, $pages ?? 8, static function (string $term) use ($catalog): void {
            $result = $catalog->syncFromCatalog();
            printf("    sync (%s): +%d novas, %d classificadas\n",
                $term, $result['inserted'], $result['classified']);
        });
    }

    if ($command === 'daemon') {
        $interval = max(300, (int) (getenv('CDP_INTERVAL') ?: 21600));
        runDaemon($client, $catalog, $pdo, $popularTerms, $pages ?? 8, $interval);
        exit(0);
    }

    if ($command === 'reclassify') {
        $changed = $catalog->reclassifyAll();
        echo "Peças reclassificadas: {$changed}\n";
    }

    if ($command === 'sync' || $command === 'parts') {
        seedCommonParts($pdo);
        $result = syncParts($pdo, $catalog);
        echo "Sincronização concluída: +{$result['inserted']} nova(s), "
            . "{$result['updated']} atualizada(s), {$result['classified']} classificada(s).\n";
    }

    if (in_array($command, ['all', 'products', 'crawl', 'sync', 'parts', 'reclassify'], true)) {
        seedCommonParts($pdo);
        $result = $catalog->syncFromCatalog();
        echo "Peças na base: {$result['inserted']} nova(s), {$result['updated']} atualizada(s).\n";
    }

    catalogSummary($pdo);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro na importação: ' . $e->getMessage() . "\n");
    exit(1);
}
