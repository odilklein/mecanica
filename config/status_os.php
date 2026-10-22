<?php
// =============================================
// STATUS DA ORDEM DE SERVIÇO — FONTE ÚNICA
// -------------------------------------------------
// Centraliza rótulos e transições para evitar que PHP,
// JavaScript, SQL e documentação divirjam. Alterou o
// fluxo? Mude apenas aqui (e a migração/ENUM do banco).
// =============================================

if (!defined('OS_STATUS_DEFINIDO')) {
    define('OS_STATUS_DEFINIDO', true);

    /**
     * Rótulos na ordem exibida em filtros e selects.
     *
     * @return array<string, string>
     */
    function os_status_labels(): array
    {
        return [
            'recebida' => 'Recebida',
            'em_analise' => 'Em análise',
            'aguardando_aprovacao' => 'Aguardando aprovação',
            'aguardando_peca' => 'Aguardando peça',
            'em_execucao' => 'Em execução',
            'pronta_para_entrega' => 'Pronta para entrega',
            'entregue' => 'Entregue',
            'cancelada' => 'Cancelada',
        ];
    }

    /**
     * Transições permitidas por status atual.
     *
     * @return array<string, array<int, string>>
     */
    function os_status_transicoes(): array
    {
        return [
            'recebida' => ['em_analise', 'aguardando_peca', 'cancelada'],
            'em_analise' => ['aguardando_aprovacao', 'aguardando_peca', 'em_execucao', 'cancelada'],
            'aguardando_aprovacao' => ['em_execucao', 'aguardando_peca', 'cancelada'],
            'aguardando_peca' => ['em_execucao', 'cancelada'],
            'em_execucao' => ['pronta_para_entrega', 'aguardando_peca', 'cancelada'],
            'pronta_para_entrega' => ['entregue', 'cancelada'],
            'entregue' => [],
            'cancelada' => [],
        ];
    }

    /**
     * Status que encerram o ciclo de vida da OS.
     *
     * @return array<int, string>
     */
    function os_status_terminais(): array
    {
        return ['entregue', 'cancelada'];
    }

    function os_status_label(string $status): string
    {
        return os_status_labels()[$status] ?? $status;
    }

    /**
     * Próximos status válidos a partir do status informado.
     *
     * @return array<int, string>
     */
    function os_status_proximos(string $status): array
    {
        return os_status_transicoes()[$status] ?? [];
    }

    function os_status_valido(string $status): bool
    {
        return array_key_exists($status, os_status_labels());
    }

    /**
     * Opções para select/filtro: ['valor' => 'Rótulo'].
     *
     * @return array<string, string>
     */
    function os_status_opcoes(bool $incluir_terminais = true): array
    {
        $labels = os_status_labels();
        if (!$incluir_terminais) {
            foreach (os_status_terminais() as $terminal) {
                unset($labels[$terminal]);
            }
        }
        return $labels;
    }

    /**
     * Payload para o front-end (window.OS_STATUS).
     *
     * @return array<string, mixed>
     */
    function os_status_payload(): array
    {
        return [
            'labels' => os_status_labels(),
            'transicoes' => os_status_transicoes(),
            'terminais' => os_status_terminais(),
        ];
    }

    /**
     * Mapeia status legados (12) para o fluxo condensado (8).
     *
     * @return array<string, string>
     */
    function os_status_migracao(): array
    {
        return [
            'recebida' => 'recebida',
            'triagem' => 'recebida',
            'aguardando_diagnostico' => 'em_analise',
            'aguardando_orcamento' => 'em_analise',
            'aguardando_aprovacao' => 'aguardando_aprovacao',
            'aguardando_peca' => 'aguardando_peca',
            'pronta_para_execucao' => 'em_execucao',
            'em_execucao' => 'em_execucao',
            'aguardando_retorno' => 'em_execucao',
            'pronta_para_entrega' => 'pronta_para_entrega',
            'entregue' => 'entregue',
            'cancelada' => 'cancelada',
        ];
    }
}
