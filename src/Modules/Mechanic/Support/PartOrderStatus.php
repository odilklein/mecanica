<?php
// =============================================
// STATUS do pedido de peças — rótulos e cores.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Support;

class PartOrderStatus
{
    public const STATUSES = [
        'requested' => 'Solicitado',
        'validated' => 'Validado',
        'ordered' => 'Pedido',
        'on_the_way' => 'A caminho',
        'received' => 'Recebido',
        'cancelled' => 'Cancelado',
    ];

    /**
     * Transições permitidas do fluxo de pedido de peças.
     * Recebido e Cancelado são estados finais.
     */
    public const TRANSITIONS = [
        'requested' => ['validated', 'cancelled'],
        'validated' => ['ordered', 'cancelled'],
        'ordered' => ['on_the_way', 'cancelled'],
        'on_the_way' => ['received', 'cancelled'],
        'received' => [],
        'cancelled' => [],
    ];

    /** @return string[] */
    public static function next(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return $from === $to || in_array($to, self::next($from), true);
    }

    public static function isFinal(string $status): bool
    {
        return self::next($status) === [];
    }

    public static function label(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function badgeClass(string $status): string
    {
        $map = [
            'requested' => 'bg-label-secondary',
            'validated' => 'bg-label-info',
            'ordered' => 'bg-label-primary',
            'on_the_way' => 'bg-label-warning',
            'received' => 'bg-label-success',
            'cancelled' => 'bg-label-danger',
        ];
        return $map[$status] ?? 'bg-label-secondary';
    }
}
