<?php
// =============================================
// NOTIFY — cria notificações para usuários.
// =============================================

declare(strict_types=1);

namespace Modules\Core\Support;

use PDO;
use Throwable;

class Notify
{
    /**
     * @param int[] $userIds
     */
    public static function to(PDO $pdo, array $userIds, string $type, string $message, ?string $link = null): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, type, message, link) VALUES (:user_id, :type, :message, :link)'
            );
            foreach ($userIds as $userId) {
                $stmt->execute([
                    ':user_id' => $userId,
                    ':type' => mb_substr($type, 0, 50),
                    ':message' => mb_substr($message, 0, 255),
                    ':link' => $link,
                ]);
            }
        } catch (Throwable $e) {
            error_log('Falha ao notificar: ' . $e->getMessage());
        }
    }
}
