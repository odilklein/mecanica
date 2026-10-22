<?php
// =============================================
// CLIENTES - SCHEMA E HELPERS
// Garante de forma idempotente as colunas novas
// usadas pela interface moderna de CRUD.
// =============================================

if (!function_exists('clientes_coluna_existe')) {
    function clientes_coluna_existe(PDO $conn, string $tabela, string $coluna): bool
    {
        $st = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $st->execute([':t' => $tabela, ':c' => $coluna]);
        return (int) $st->fetchColumn() > 0;
    }
}

if (!function_exists('clientes_garantir_schema')) {
    // Normaliza o cadastro do cliente: status (ativo) e endereço completo
    // (CEP, número, complemento, bairro e UF).
    // Idempotente: pode rodar a cada request sem efeitos colaterais.
    function clientes_garantir_schema(PDO $conn): void
    {
        $colunas = [
            'ativo' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER observacoes',
            'cep' => 'VARCHAR(9) NULL AFTER endereco',
            'numero' => 'VARCHAR(20) NULL AFTER cep',
            'complemento' => 'VARCHAR(120) NULL AFTER numero',
            'bairro' => 'VARCHAR(120) NULL AFTER complemento',
            'uf' => 'VARCHAR(2) NULL AFTER cidade',
        ];

        foreach ($colunas as $coluna => $definicao) {
            if (!clientes_coluna_existe($conn, 'clientes', $coluna)) {
                $conn->exec("ALTER TABLE clientes ADD COLUMN {$coluna} {$definicao}");
            }
        }
    }
}
