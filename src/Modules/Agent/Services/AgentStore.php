<?php
// =============================================
// AGENT STORE — persistência do chat do assistente.
//
// Sessões, mensagens e consumo de tokens. Toda a
// leitura é sempre escopada pelo usuário logado.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

use PDO;

class AgentStore
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Sessão ativa mais recente do usuário (cria uma se não existir).
     *
     * @return array<string, mixed>
     */
    public function activeSession(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM agent_sessions
              WHERE user_id = :u AND status = 'active'
              ORDER BY last_activity_at DESC, id DESC
              LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        $session = $stmt->fetch();

        return $session ?: $this->createSession($userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function createSession(int $userId, ?string $title = null): array
    {
        $this->pdo->prepare(
            'INSERT INTO agent_sessions (user_id, title) VALUES (:u, :t)'
        )->execute([
            ':u' => $userId,
            ':t' => $title !== null ? mb_substr($title, 0, 160) : null,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return $this->findSession($id, $userId) ?? [
            'id' => $id,
            'user_id' => $userId,
            'title' => $title,
            'status' => 'active',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSession(int $id, int $userId): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM agent_sessions WHERE id = :id AND user_id = :u LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':u' => $userId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sessions(int $userId, int $limit = 15): array
    {
        $limit = max(1, min(50, $limit));

        $stmt = $this->pdo->prepare(
            "SELECT s.*, (
                        SELECT COUNT(*) FROM agent_messages m WHERE m.session_id = s.id
                     ) AS message_count
               FROM agent_sessions s
              WHERE s.user_id = :u
              ORDER BY s.last_activity_at DESC, s.id DESC
              LIMIT {$limit}"
        );
        $stmt->execute([':u' => $userId]);

        return $stmt->fetchAll();
    }

    public function archive(int $id, int $userId): void
    {
        $this->pdo->prepare(
            "UPDATE agent_sessions SET status = 'archived'
              WHERE id = :id AND user_id = :u"
        )->execute([':id' => $id, ':u' => $userId]);
    }

    /**
     * Histórico em ordem cronológica.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messages(int $sessionId, int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));

        $stmt = $this->pdo->prepare(
            "SELECT * FROM (
                SELECT * FROM agent_messages
                 WHERE session_id = :s
                 ORDER BY id DESC
                 LIMIT {$limit}
             ) recent
             ORDER BY id ASC"
        );
        $stmt->execute([':s' => $sessionId]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['tool_calls'] = $row['tool_calls'] ? json_decode((string) $row['tool_calls'], true) : null;
            $row['meta'] = $row['meta'] ? json_decode((string) $row['meta'], true) : null;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed>|null $toolCalls
     * @param array<string, mixed>|null $meta
     */
    public function addMessage(
        int $sessionId,
        string $role,
        ?string $content,
        ?string $toolName = null,
        ?array $toolCalls = null,
        ?array $meta = null,
        int $tokensIn = 0,
        int $tokensOut = 0
    ): int {
        $this->pdo->prepare(
            'INSERT INTO agent_messages
                (session_id, role, content, tool_name, tool_calls, meta, tokens_in, tokens_out)
             VALUES (:s, :r, :c, :t, :tc, :m, :ti, :to)'
        )->execute([
            ':s' => $sessionId,
            ':r' => $role,
            ':c' => $content,
            ':t' => $toolName,
            ':tc' => $toolCalls !== null ? json_encode($toolCalls, JSON_UNESCAPED_UNICODE) : null,
            ':m' => $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ':ti' => $tokensIn,
            ':to' => $tokensOut,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualiza o título da sessão a partir da primeira mensagem.
     */
    public function touch(int $sessionId, ?string $title = null): void
    {
        if ($title !== null && trim($title) !== '') {
            $this->pdo->prepare(
                'UPDATE agent_sessions
                    SET title = COALESCE(title, :t), last_activity_at = NOW()
                  WHERE id = :id'
            )->execute([':t' => mb_substr(trim($title), 0, 160), ':id' => $sessionId]);
            return;
        }

        $this->pdo->prepare('UPDATE agent_sessions SET last_activity_at = NOW() WHERE id = :id')
            ->execute([':id' => $sessionId]);
    }

    public function bumpUsage(int $userId, int $tokensIn, int $tokensOut): void
    {
        $this->pdo->prepare(
            'INSERT INTO agent_usage (user_id, day, turns, tokens_in, tokens_out)
             VALUES (:u, CURDATE(), 1, :ti, :to)
             ON DUPLICATE KEY UPDATE
                turns = turns + 1,
                tokens_in = tokens_in + VALUES(tokens_in),
                tokens_out = tokens_out + VALUES(tokens_out)'
        )->execute([
            ':u' => $userId,
            ':ti' => max(0, $tokensIn),
            ':to' => max(0, $tokensOut),
        ]);
    }

    /**
     * @return array{turns: int, tokens_in: int, tokens_out: int}
     */
    public function usageToday(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT turns, tokens_in, tokens_out FROM agent_usage WHERE user_id = :u AND day = CURDATE()'
        );
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch() ?: ['turns' => 0, 'tokens_in' => 0, 'tokens_out' => 0];

        return [
            'turns' => (int) $row['turns'],
            'tokens_in' => (int) $row['tokens_in'],
            'tokens_out' => (int) $row['tokens_out'],
        ];
    }

    /**
     * Turnos do usuário na última hora (limite anti-abuso).
     */
    public function turnsLastHour(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
               FROM agent_messages m
               JOIN agent_sessions s ON s.id = m.session_id
              WHERE s.user_id = :u
                AND m.role = 'user'
                AND m.created_at >= (NOW() - INTERVAL 1 HOUR)"
        );
        $stmt->execute([':u' => $userId]);

        return (int) $stmt->fetchColumn();
    }
}
