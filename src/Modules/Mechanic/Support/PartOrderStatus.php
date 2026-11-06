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
