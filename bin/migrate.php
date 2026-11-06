<?php
// =============================================
// CLI — runner de migrations.
// Uso (dentro do container):
//   php bin/migrate.php migrate
//   php bin/migrate.php status
// =============================================

declare(strict_types=1);

use Kernel\Database\Connection;
use Kernel\Database\Migrator;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
if (is_file($basePath . '/.env')) {
    Dotenv\Dotenv::createImmutable($basePath)->safeLoad();
}

$command = $argv[1] ?? 'migrate';

try {
    $pdo = Connection::fromEnv();
    $migrator = new Migrator($pdo, $basePath);

    switch ($command) {
        case 'migrate':
            $ran = $migrator->migrate();
            if ($ran) {
                echo "Migrations aplicadas:\n  - " . implode("\n  - ", $ran) . "\n";
            } else {
                echo "Nada a migrar (tudo em dia).\n";
            }
            break;

        case 'status':
            foreach ($migrator->status() as $row) {
                printf("[%s] %s\n", $row['applied'] ? 'x' : ' ', $row['key']);
            }
            break;

        default:
            fwrite(STDOUT, "Uso: php bin/migrate.php [migrate|status]\n");
            exit(2);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
