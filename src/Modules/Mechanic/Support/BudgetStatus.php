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

    /**
     * Fluxo do orçamento. Aprovado/Reprovado/Cancelado podem ser reabertos
     * para correção; a edição só é permitida com status "Aberto".
     */
    public const TRANSITIONS = [
        'open' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['open', 'cancelled'],
        'rejected' => ['open', 'cancelled'],
        'cancelled' => ['open'],
    ];

    /** @return string[] */
    public static function next(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::next($from), true);
    }

    public static function isEditable(string $status): bool
    {
        return $status === 'open';
    }

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
