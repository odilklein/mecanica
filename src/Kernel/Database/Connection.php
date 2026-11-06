<?php
// =============================================
// DATABASE CONNECTION — factory de PDO.
// Configuração via variáveis de ambiente (12-Factor).
// =============================================

declare(strict_types=1);

namespace Kernel\Database;

use PDO;

class Connection
{
    /**
     * Cria uma conexão PDO a partir de um array de configuração.
     *
     * @param array{host: string, port: string|int, database: string, username: string, password: string} $config
     */
    public static function make(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Cria uma conexão a partir das variáveis de ambiente (DB_HOST, ...).
     */
    public static function fromEnv(): PDO
    {
        return self::make([
            'host' => (string) env('DB_HOST', 'localhost'),
            'port' => (string) env('DB_PORT', '3306'),
            'database' => (string) env('DB_NAME', 'mecanica'),
            'username' => (string) env('DB_USER', 'root'),
            'password' => (string) env('DB_PASS', ''),
        ]);
    }
}
