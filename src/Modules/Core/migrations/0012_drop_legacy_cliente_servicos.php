<?php
// =============================================
// MIGRATION — remove a última tabela do app
// legado que não é usada pelo kernel modular.
// As tabelas do roteiro do TCC permanecem.
// =============================================

return [
    'up' => [
        'DROP TABLE IF EXISTS cliente_servicos_historico',
    ],
    'down' => [],
];
