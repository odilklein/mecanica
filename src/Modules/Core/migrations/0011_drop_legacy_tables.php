<?php
// =============================================
// MIGRATION — remove tabelas do app legado que
// não são usadas pelo kernel modular.
//
// Mantidas de propósito (demonstração do TCC):
//   ordens_servico, clientes, veiculos, usuarios,
//   os_responsaveis, os_tarefas, os_eventos,
//   pedidos_pecas, vw_ordens_servico_detalhada,
//   trg_os_status_historico.
//
// Backup das tabelas removidas: /tmp/opencode/legacy-unused-tables-backup.sql
// (gerado antes da limpeza; não faz parte do repositório).
// =============================================

return [
    'up' => [
        'SET FOREIGN_KEY_CHECKS = 0',
        'DROP TABLE IF EXISTS auditoria',
        'DROP TABLE IF EXISTS configuracoes',
        'DROP TABLE IF EXISTS fipe_anos',
        'DROP TABLE IF EXISTS fipe_marcas_alias',
        'DROP TABLE IF EXISTS fipe_modelos',
        'DROP TABLE IF EXISTS fipe_modelos_base',
        'DROP TABLE IF EXISTS fipe_precos',
        'DROP TABLE IF EXISTS fipe_marcas',
        'DROP TABLE IF EXISTS notificacoes',
        'DROP TABLE IF EXISTS orcamento_itens',
        'DROP TABLE IF EXISTS orcamentos',
        'DROP TABLE IF EXISTS pedido_pecas_itens',
        'DROP TABLE IF EXISTS os_entregas',
        'SET FOREIGN_KEY_CHECKS = 1',
    ],
    'down' => [],
];
