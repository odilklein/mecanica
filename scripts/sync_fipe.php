<?php
declare(strict_types=1);

/**
 * =============================================================
 * SYNC FIPE - Espelha marcas, modelos-base e versões no banco.
 * Fonte: API pública Parallelum (base comunitária da Tabela FIPE).
 *
 * Uso (dentro do container):
 *   php scripts/sync_fipe.php
 *   php scripts/sync_fipe.php --limpar
 *
 * Observações:
 * - Idempotente (ON DUPLICATE KEY UPDATE): pode rodar quantas vezes quiser.
 * - Faz ~90 requisições (1 p/ marcas + 1 por marca p/ modelos).
 * =============================================================
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via linha de comando.\n");
    exit(1);
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/fipe.php';

$opcoes = getopt('', ['limpar', 'tipo::']);
$limpar = array_key_exists('limpar', $opcoes);
$tipo = $opcoes['tipo'] ?? 'carros';
if ($tipo !== 'carros') {
    fwrite(STDERR, "Somente --tipo=carros é suportado no momento.\n");
    exit(1);
}

$resumo = fipe_sincronizar($conn, $limpar, function (string $msg) {
    echo $msg . "\n";
});

echo "\nSync concluído.\n";
echo str_repeat('-', 40) . "\n";
printf("%-22s %6d\n", 'Marcas', $resumo['marcas']);
printf("%-22s %6d\n", 'Modelos-base', $resumo['bases']);
printf("%-22s %6d\n", 'Versões/modelos', $resumo['modelos']);
if ($resumo['falhas']) {
    echo "\nMarcas sem modelos retornados (rever depois): " . implode(', ', $resumo['falhas']) . "\n";
}
