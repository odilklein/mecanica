<?php
// =============================================
// MIGRATOR — executa as migrations de cada módulo
// em ordem, registrando as aplicadas na tabela
// `migrations` (para não rodar duas vezes).
// =============================================

declare(strict_types=1);

namespace Kernel\Database;

use PDO;

class Migrator
{
    private PDO $pdo;
    private string $basePath;

    public function __construct(PDO $pdo, string $basePath)
    {
        $this->pdo = $pdo;
        $this->basePath = $basePath;
    }

    /**
     * Aplica as migrations pendentes. Retorna a lista das executadas.
     *
     * @return string[]
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->applied();
        $ran = [];

        foreach ($this->pending($applied) as $key => $file) {
            $migration = $this->load($file);

            // MySQL executa DDL com commit implícito, então não usamos
            // transação aqui (CREATE TABLE/DROP não são transacionais).
            // Uma migration pode conter SQL (string) ou um callable que recebe
            // o PDO — necessário para passos que dependem de PHP (ex.: gerar
            // hash de senha aleatório no seed de usuários).
            foreach ($migration['up'] as $step) {
                if (is_callable($step)) {
                    $step($this->pdo);
                    continue;
                }
                $this->pdo->exec((string) $step);
            }
            $this->record($key);
            $ran[] = $key;
        }

        return $ran;
    }

    /**
     * Lista o status de cada migration (aplicada/pendente).
     *
     * @return array<int, array{key: string, applied: bool}>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->applied();

        $rows = [];
        foreach ($this->migrationFiles() as $key => $file) {
            $rows[] = ['key' => $key, 'applied' => in_array($key, $applied, true)];
        }
        return $rows;
    }

    /** @return array<string, string> aplicadas (key => key) */
    private function applied(): array
    {
        $stmt = $this->pdo->query('SELECT migration FROM migrations');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return array<string, string> pendentes (key => file) */
    private function pending(array $applied): array
    {
        $pending = [];
        foreach ($this->migrationFiles() as $key => $file) {
            if (!in_array($key, $applied, true)) {
                $pending[$key] = $file;
            }
        }
        return $pending;
    }

    /**
     * @return array<string, string> key => caminho do arquivo (ordenado por nome)
     */
    private function migrationFiles(): array
    {
        $files = glob($this->basePath . '/src/Modules/*/migrations/*.php') ?: [];

        // Ordena por módulo respeitando o module.json (Core antes de Mechanic,
        // Mechanic antes de Agent) e, dentro do módulo, pelo nome do arquivo.
        // Sem isso a ordem alfabética das pastas roda Agent primeiro e uma FK
        // para `users`/`service_orders` quebra em banco vazio.
        $rank = array_flip($this->moduleOrder());

        usort($files, static function (string $a, string $b) use ($rank): int {
            $moduleOf = static function (string $file): string {
                return preg_match('~/src/Modules/([^/]+)/migrations/~', $file, $m) === 1 ? $m[1] : '';
            };

            return [$rank[$moduleOf($a)] ?? PHP_INT_MAX, basename($a)]
                <=> [$rank[$moduleOf($b)] ?? PHP_INT_MAX, basename($b)];
        });

        $map = [];
        foreach ($files as $file) {
            $key = $this->keyFor($file);
            $map[$key] = $file;
        }
        return $map;
    }

    /**
     * Ordem de execução dos módulos, derivada do module.json
     * (dependências primeiro; pastas sem manifesto ficam de fora).
     *
     * @return string[] nomes das pastas dos módulos, na ordem de execução
     */
    private function moduleOrder(): array
    {
        $rawDeps = [];
        $folderByName = [];

        foreach (glob($this->basePath . '/src/Modules/*/module.json') ?: [] as $manifestFile) {
            $folder = basename(dirname($manifestFile));
            $manifest = json_decode((string) file_get_contents($manifestFile), true);
            if (!is_array($manifest)) {
                continue;
            }

            $name = (string) ($manifest['name'] ?? strtolower($folder));
            $folderByName[$name] = $folder;
            $rawDeps[$folder] = array_map('strval', (array) ($manifest['dependencies'] ?? []));
        }

        // Dependências vêm com o nome do manifesto ("core"); traduz para a pasta.
        $modules = [];
        foreach ($rawDeps as $folder => $deps) {
            $modules[$folder] = array_map(
                static fn (string $dep): string => $folderByName[$dep] ?? $dep,
                $deps
            );
        }

        $order = [];
        $visiting = [];
        $resolve = function (string $folder) use (&$resolve, &$order, &$visiting, $modules): void {
            if (isset($order[$folder]) || isset($visiting[$folder])) {
                return;
            }

            $visiting[$folder] = true;
            foreach ($modules[$folder] ?? [] as $dep) {
                if (isset($modules[$dep])) {
                    $resolve($dep);
                }
            }
            unset($visiting[$folder]);
            $order[$folder] = true;
        };

        $folders = array_keys($modules);
        sort($folders);
        foreach ($folders as $folder) {
            $resolve($folder);
        }

        return array_keys($order);
    }

    /**
     * Gera a chave estável da migration a partir do caminho:
     * "src/Modules/Core/migrations/0001_...php" -> "Core/0001_..."
     */
    private function keyFor(string $file): string
    {
        $relative = substr($file, strlen($this->basePath) + 1);
        return (string) preg_replace('~^src/Modules/([^/]+)/migrations/(.+)\.php$~', '$1/$2', $relative);
    }

    /**
     * Carrega o array retornado pelo arquivo de migration.
     *
     * @return array{up: array<int, string|callable>, down?: array<int, string|callable>}
     */
    private function load(string $file): array
    {
        $migration = require $file;
        if (!is_array($migration) || !isset($migration['up']) || !is_array($migration['up'])) {
            throw new \RuntimeException('Migration inválida: ' . $file);
        }
        return $migration;
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL DEFAULT 1,
                ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function record(string $key): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
        $stmt->execute([':migration' => $key]);
    }
}
