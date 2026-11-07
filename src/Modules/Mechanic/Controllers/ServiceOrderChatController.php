<?php
// =============================================
// CHAT DA OS — API JSON de mensagens do ticket.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Modules\Mechanic\Support\ServiceOrderChat;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

class ServiceOrderChatController extends Controller
{
    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    public function index(ServerRequestInterface $request, $id)
    {
        $order = $this->findOrder((int) $id);
        if (!$order) {
            return json_response(['error' => 'not_found'], 404);
        }
        if (!$this->canChat($order)) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.order_id, m.user_id, m.message, m.created_at, u.name AS user_name, u.role AS user_role
               FROM service_order_messages m
               JOIN users u ON u.id = m.user_id
              WHERE m.order_id = :id
              ORDER BY m.id ASC'
        );
        $stmt->execute([':id' => (int) $id]);

        return json_response([
            'messages' => $stmt->fetchAll(),
            'current_user_id' => (int) $this->auth->id(),
            'can_send' => $this->canChat($order),
        ]);
    }

    public function store(ServerRequestInterface $request, $id)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $order = $this->findOrder((int) $id);
        if (!$order) {
            return json_response(['error' => 'not_found'], 404);
        }
        if (!$this->canChat($order)) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            return json_response(['error' => 'csrf'], 419);
        }

        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            return json_response(['error' => 'empty'], 422);
        }

        $message = mb_substr($message, 0, 2000);
        $this->pdo->prepare(
            'INSERT INTO service_order_messages (order_id, user_id, message) VALUES (?, ?, ?)'
        )->execute([(int) $id, (int) $this->auth->id(), $message]);

        $messageId = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.order_id, m.user_id, m.message, m.created_at, u.name AS user_name, u.role AS user_role
               FROM service_order_messages m
               JOIN users u ON u.id = m.user_id
              WHERE m.id = :id'
        );
        $stmt->execute([':id' => $messageId]);

        return json_response(['ok' => true, 'message' => $stmt->fetch() ?: null], 201);
    }

    private function findOrder(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_by, lead_mechanic_id FROM service_orders WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function canChat(array $order): bool
    {
        return ServiceOrderChat::canParticipate(
            $this->pdo,
            $order,
            (int) $this->auth->id(),
            $this->auth->hasRole(['admin'])
        );
    }
}
