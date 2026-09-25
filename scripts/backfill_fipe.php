<?php
declare(strict_types=1);

/**
 * =============================================================
 * BACKFILL FIPE - Associa veículos legados (texto livre) ao
 * catálogo FIPE local, preenchendo marca_id e modelo_base_id.
 *
 * Uso:
 *   php scripts/backfill_fipe.php            # associa marcas/modelos
 *   php scripts/backfill_fipe.php --com-valor # também busca Valor FIPE
 * =============================================================
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via linha de comando.\n");
    exit(1);
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/fipe.php';

$opcoes = getopt('', ['com-valor']);
$comValor = array_key_exists('com-valor', $opcoes);

fipe_garantir_schema($conn);

$veiculos = $conn->query("SELECT id, marca, modelo, ano FROM veiculos WHERE marca_id IS NULL AND marca IS NOT NULL AND marca <> ''")->fetchAll();

$stMarca = $conn->prepare("SELECT id FROM fipe_marcas WHERE LOWER(nome_exibicao) = LOWER(:m) OR LOWER(nome) = LOWER(:m) LIMIT 1");
$stBase = $conn->prepare("SELECT id FROM fipe_modelos_base WHERE marca_id = :marca AND LOWER(nome_base) = LOWER(:base) LIMIT 1");
$stUpd = $conn->prepare("UPDATE veiculos SET marca_id = :marca, modelo_base_id = :base WHERE id = :id");
$stUpdMarca = $conn->prepare("UPDATE veiculos SET marca_id = :marca WHERE id = :id");

$associados = 0;
$soMarca = 0;
$falhas = [];

$conn->beginTransaction();
try {
    foreach ($veiculos as $v) {
        $stMarca->execute([':m' => $v['marca']]);
        $marcaId = (int) ($stMarca->fetchColumn() ?: 0);
        if (!$marcaId) {
            $falhas[] = $v['marca'] . ' ' . $v['modelo'];
            continue;
        }

        $stBase->execute([':marca' => $marcaId, ':base' => $v['modelo']]);
        $baseId = (int) ($stBase->fetchColumn() ?: 0);

        if ($baseId) {
            $stUpd->execute([':marca' => $marcaId, ':base' => $baseId, ':id' => $v['id']]);
            $associados++;
        } else {
            $stUpdMarca->execute([':marca' => $marcaId, ':id' => $v['id']]);
            $soMarca++;
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollBack();
    fwrite(STDERR, "Erro no backfill: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Backfill concluído.\n";
printf("%-30s %6d\n", 'Veículos associados (marca+modelo)', $associados);
printf("%-30s %6d\n", 'Associados só por marca', $soMarca);
printf("%-30s %6d\n", 'Sem correspondência', count($falhas));

if ($comValor) {
    echo "\nBuscando Valor FIPE para os veículos associados...\n";

    $stVersoes = $conn->prepare("SELECT id, nome_versao FROM fipe_modelos WHERE base_id = :b ORDER BY id");
    $stValorVeiculo = $conn->prepare("UPDATE veiculos SET modelo_id = :m, fipe_versao = :fv, valor_fipe = :v, fipe_mes_referencia = :mes WHERE id = :id");
    $stValorSimples = $conn->prepare("UPDATE veiculos SET valor_fipe = :v, fipe_mes_referencia = :mes WHERE id = :id");

    // 1) Veículos que já têm versão específica (modelo_id)
    $pendentes = $conn->query("SELECT id, modelo_id, ano FROM veiculos WHERE modelo_id IS NOT NULL AND ano > 0 AND valor_fipe IS NULL")->fetchAll();
    $comValorOk = 0;
    $semValor = 0;
    foreach ($pendentes as $p) {
        $fipe = fipe_valor_por_modelo_ano($conn, (int) $p['modelo_id'], (int) $p['ano']);
        if ($fipe) {
            $stValorSimples->execute([':v' => $fipe['valor'], ':mes' => mb_substr($fipe['mes_referencia'], 0, 20), ':id' => $p['id']]);
            $comValorOk++;
        } else {
            $semValor++;
        }
    }

    // 2) Veículos só com modelo-base: agrupa por (base, ano) e escolhe uma versão representativa
    $grupos = $conn->query("SELECT modelo_base_id, ano, COUNT(*) AS qtd
        FROM veiculos
        WHERE modelo_base_id IS NOT NULL AND modelo_id IS NULL AND ano > 0 AND valor_fipe IS NULL
        GROUP BY modelo_base_id, ano")->fetchAll();

    $stAtualizaGrupo = $conn->prepare("UPDATE veiculos
        SET modelo_id = :m, fipe_versao = :fv, valor_fipe = :v, fipe_mes_referencia = :mes
        WHERE modelo_base_id = :b AND ano = :ano AND modelo_id IS NULL");

    $tentados = [];
    $gruposOk = 0;
    $gruposSem = 0;
    $gruposProcessados = 0;
    foreach ($grupos as $g) {
        $gruposProcessados++;
        if ($gruposProcessados % 25 === 0) {
            echo "  processados {$gruposProcessados}/" . count($grupos) . " grupos...\n";
        }
        $baseId = (int) $g['modelo_base_id'];
        $ano = (int) $g['ano'];

        $stVersoes->execute([':b' => $baseId]);
        $versoes = $stVersoes->fetchAll();

        $achou = false;
        $tentativas = 0;
        foreach ($versoes as $v) {
            $modeloId = (int) $v['id'];
            if (isset($tentados[$modeloId . '-' . $ano])) {
                continue;
            }
            $tentados[$modeloId . '-' . $ano] = true;
            $tentativas++;
            if ($tentativas > 6) {
                break;
            }

            $fipe = fipe_valor_por_modelo_ano($conn, $modeloId, $ano);
            usleep(60000); // cortesia: evita estourar rate limit da API
            if ($fipe) {
                $stAtualizaGrupo->execute([
                    ':m' => $modeloId,
                    ':fv' => mb_substr((string) $v['nome_versao'], 0, 150),
                    ':v' => $fipe['valor'],
                    ':mes' => mb_substr($fipe['mes_referencia'], 0, 20),
                    ':b' => $baseId,
                    ':ano' => $ano,
                ]);
                $gruposOk += (int) $g['qtd'];
                $achou = true;
                break;
            }
        }
        if (!$achou) {
            $gruposSem += (int) $g['qtd'];
        }
    }

    printf("%-30s %6d\n", 'Com valor FIPE (versão já definida)', $comValorOk);
    printf("%-30s %6d\n", 'Com valor FIPE (via modelo-base)', $gruposOk);
    printf("%-30s %6d\n", 'Sem valor encontrado', $semValor + $gruposSem);
}
