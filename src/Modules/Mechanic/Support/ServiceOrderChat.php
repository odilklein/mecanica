<?php
// =============================================
// CHAT DA OS — regras de participação.
// Quem pode conversar: admin, criador da OS,
// mecânico responsável principal e designados.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Support;

use PDO;

class ServiceOrderChat
{
    public static function canParticipate(PDO $pdo, array $order, int $userId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ((int) ($order['created_by'] ?? 0) === $userId) {
            return true;
        }
        if ((int) ($order['lead_mechanic_id'] ?? 0) === $userId) {
            return true;
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM service_order_responsibles WHERE order_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([(int) $order['id'], $userId]);
        return (bool) $stmt->fetchColumn();
    }
}
