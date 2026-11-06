<?php
// =============================================
// STATUS do orçamento — rótulos e cores.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Support;

class BudgetStatus
{
    public const STATUSES = [
        'open' => 'Aberto',
        'approved' => 'Aprovado',
        'rejected' => 'Reprovado',
        'cancelled' => 'Cancelado',
    ];

    public static function label(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function badgeClass(string $status): string
    {
        $map = [
            'open' => 'bg-label-info',
            'approved' => 'bg-label-success',
            'rejected' => 'bg-label-danger',
            'cancelled' => 'bg-label-secondary',
        ];
        return $map[$status] ?? 'bg-label-secondary';
    }
}
