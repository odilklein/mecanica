<?php
// =============================================
// ORDER ACCESS — regras de visibilidade da OS.
//
// Admin, secretaria e mecânico-chefe veem tudo.
// Mecânico vê apenas OS em que é criador, responsável
// (lead) ou está designado em service_order_responsibles.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Support;

use Kernel\Auth\Auth;
use PDO;

final class OrderAccess
{
    /** Mecânico "puro" (sem papéis de gestão). */
    public static function isMechanicOnly(Auth $auth): bool
    {
        return $auth->hasRole(['mechanic'])
            && !$auth->hasRole(['admin', 'secretary', 'lead_mechanic']);
    }

    /** @param array<string, mixed> $order */
    public static function canAccess(PDO $pdo, Auth $auth, array $order): bool
    {
        if (!$auth->check()) {
            return false;
        }
        if (!self::isMechanicOnly($auth)) {
            return true;
        }

        $userId = (int) $auth->id();
        if ((int) ($order['created_by'] ?? 0) === $userId) {
            return true;
        }
        if ((int) ($order['lead_mechanic_id'] ?? 0) === $userId) {
            return true;
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM service_order_responsibles WHERE order_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([(int) ($order['id'] ?? 0), $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function canAccessOrderId(PDO $pdo, Auth $auth, int $orderId): bool
    {
        if (!self::isMechanicOnly($auth)) {
            return $auth->check();
        }

        $stmt = $pdo->prepare('SELECT id, created_by, lead_mechanic_id FROM service_orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        return $order ? self::canAccess($pdo, $auth, $order) : false;
    }

    /**
     * Condição SQL + parâmetros para listagens.
     *
     * @return array{0: string, 1: array<string, int>}
     */
    public static function scopeSql(Auth $auth, string $alias = 'so'): array
    {
        if (!self::isMechanicOnly($auth)) {
            return ['', []];
        }

        $userId = (int) $auth->id();
        return [
            "({$alias}.created_by = :scope_uid_created
              OR {$alias}.lead_mechanic_id = :scope_uid_lead
              OR EXISTS (
                    SELECT 1 FROM service_order_responsibles sr
                     WHERE sr.order_id = {$alias}.id AND sr.user_id = :scope_uid_resp
              ))",
            [
                ':scope_uid_created' => $userId,
                ':scope_uid_lead' => $userId,
                ':scope_uid_resp' => $userId,
            ],
        ];
    }
}
