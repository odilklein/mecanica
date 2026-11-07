<?php
// =============================================
// STATUS/PRIORIDADE da OS — rótulos (pt-BR) e cores.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Support;

class ServiceOrderStatus
{
    public const STATUSES = [
        'received' => 'Recebida',
        'in_analysis' => 'Em análise',
        'awaiting_approval' => 'Aguardando aprovação',
        'awaiting_part' => 'Aguardando peça',
        'in_progress' => 'Em execução',
        'ready_for_delivery' => 'Pronta para entrega',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelada',
    ];

    public const PRIORITIES = [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];

    public static function label(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function priorityLabel(string $priority): string
    {
        return self::PRIORITIES[$priority] ?? $priority;
    }

    /** Classes do badge Vuexy para cada status. */
    public static function badgeClass(string $status): string
    {
        $map = [
            'received' => 'bg-label-secondary',
            'in_analysis' => 'bg-label-info',
            'awaiting_approval' => 'bg-label-warning',
            'awaiting_part' => 'bg-label-warning',
            'in_progress' => 'bg-label-primary',
            'ready_for_delivery' => 'bg-label-success',
            'delivered' => 'bg-label-success',
            'cancelled' => 'bg-label-danger',
        ];
        return $map[$status] ?? 'bg-label-secondary';
    }

    public static function priorityBadge(string $priority): string
    {
        $map = [
            'low' => 'bg-label-secondary',
            'normal' => 'bg-label-info',
            'high' => 'bg-label-warning',
            'urgent' => 'bg-label-danger',
        ];
        return $map[$priority] ?? 'bg-label-secondary';
    }

    /** Próximos status válidos a partir do atual. */
    public static function next(string $status): array
    {
        $flow = [
            'received' => ['in_analysis', 'cancelled'],
            'in_analysis' => ['awaiting_approval', 'in_progress', 'cancelled'],
            'awaiting_approval' => ['awaiting_part', 'in_progress', 'cancelled'],
            'awaiting_part' => ['in_progress', 'cancelled'],
            'in_progress' => ['ready_for_delivery', 'cancelled'],
            'ready_for_delivery' => ['delivered', 'cancelled'],
            'delivered' => [],
            'cancelled' => [],
        ];
        return $flow[$status] ?? [];
    }
}
