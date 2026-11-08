<?php
// =============================================
// MIGRATION — normaliza o rótulo de categoria
// das peças importadas.
//
// O importador antigo gravava o breadcrumb inteiro
// ("Autopeças > Motor > Radiador & Arrefecimento").
// Aqui mantemos apenas o segmento específico.
// A classificação funcional (category_id) é feita
// pelo comando `php bin/import_parts.php reclassify`.
// =============================================

return [
    'up' => [
        "UPDATE parts
            SET category = TRIM(SUBSTRING_INDEX(category, ' > ', -1))
          WHERE category LIKE '% > %'",

        "UPDATE parts
            SET category = NULL
          WHERE category IN ('Busca por Código da Peça', 'Busca por Codigo da Peca')",
    ],
    'down' => [
        // Sem reversão: o breadcrumb original não é mais necessário.
    ],
];
