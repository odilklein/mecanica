<?php
// =============================================
// MIGRATION — ficha técnica inicial (dados curados).
//
// Fonte: manual do proprietário Chevrolet Tracker 2025
// (chevrolet.com.br) e matéria da R7 sobre a linha Onix/Tracker.
// O manual admite 5W30 OU 0W20; a tampa do óleo define.
// =============================================

declare(strict_types=1);

return [
    'up' => [
        "INSERT INTO vehicle_specs
            (make, model, year_from, year_to, engine, oil_viscosity, oil_capacity_l, oil_spec,
             notes, source, confidence)
         SELECT 'chevrolet', 'tracker', 2020, 2026, '1.2', '5W30', 4.00,
                'Dexos 1 Gen3 / API SP',
                'O manual admite 5W30 ou 0W20; confira a tampa do óleo. Capacidade de 4,00 L com filtro (motor 1.2T).',
                'Manual do proprietário Chevrolet Tracker 2025 (chevrolet.com.br)',
                'high'
          FROM DUAL
         WHERE NOT EXISTS (
             SELECT 1 FROM vehicle_specs WHERE make = 'chevrolet' AND model = 'tracker' AND engine = '1.2'
         )",

        "INSERT INTO vehicle_specs
            (make, model, year_from, year_to, engine, oil_viscosity, oil_capacity_l, oil_spec,
             notes, source, confidence)
         SELECT 'chevrolet', 'onix', 2020, 2025, '1.0', '0W20', 3.75,
                'Dexos 1 Gen3 / API SP',
                'Capacidade de 3,75 L com filtro (motor 1.0 turbo).',
                'R7 — óleo recomendado para Onix, Onix Plus, Tracker e Montana (23/06/2025)',
                'high'
          FROM DUAL
         WHERE NOT EXISTS (
             SELECT 1 FROM vehicle_specs WHERE make = 'chevrolet' AND model = 'onix' AND engine = '1.0'
         )",
    ],
    'down' => [
        "DELETE FROM vehicle_specs WHERE make = 'chevrolet' AND model IN ('tracker', 'onix')",
    ],
];
