<?php
// =============================================
// CLI — sincronização do catálogo FIPE.
// Uso (dentro do container):
//   php bin/fipe.php sync
// =============================================

declare(strict_types=1);

use Kernel\Database\Connection;
use Modules\Mechanic\Services\FipeService;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
if (is_file($basePath . '/.env')) {
    Dotenv\Dotenv::createImmutable($basePath)->safeLoad();
}

$command = $argv[1] ?? 'sync';

try {
    $service = new FipeService(Connection::fromEnv());

    if ($command === 'sync') {
        $result = $service->sync(static function (string $msg): void {
            echo $msg . PHP_EOL;
        });
        echo PHP_EOL . "Sync FIPE concluído: {$result['brands']} marcas, {$result['models']} modelos." . PHP_EOL;
        if ($result['failures']) {
            echo 'Falhas: ' . implode(', ', $result['failures']) . PHP_EOL;
        }
    } else {
        echo 'Uso: php bin/fipe.php sync' . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
