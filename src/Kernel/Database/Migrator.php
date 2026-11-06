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
            foreach ($migration['up'] as $sql) {
                $this->pdo->exec($sql);
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
        sort($files);

        $map = [];
        foreach ($files as $file) {
            $key = $this->keyFor($file);
            $map[$key] = $file;
        }
        return $map;
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
     * @return array{up: string[], down?: string[]}
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
