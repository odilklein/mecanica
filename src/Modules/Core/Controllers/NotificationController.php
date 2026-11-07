<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

class NotificationController extends Controller
{
    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    public function index(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuth()) {
            return $response;
        }

        $userId = $this->auth->id();
        $perPage = 20;
        $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u');
        $count->execute([':u' => $userId]);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT n.*, u.name AS user_name, cb.name AS created_by_name
               FROM notifications n
               LEFT JOIN users u ON u.id = n.user_id
               LEFT JOIN users cb ON cb.id = n.created_by
              WHERE n.user_id = :u
              ORDER BY n.id DESC
              LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([':u' => $userId]);

        return $this->render('core::notifications/index', [
            'title' => 'Notificações',
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
        ]);
    }

    public function markRead(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guardAuth()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, '/notifications')) {
            return $response;
        }

        $this->pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = :id AND user_id = :u')
            ->execute([':id' => (int) $id, ':u' => $this->auth->id()]);

        if ($this->isAjax($request)) {
            return json_response(['ok' => true]);
        }

        return redirect('/notifications');
    }

    public function markAllRead(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuth()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, '/notifications')) {
            return $response;
        }

        $this->pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL')
            ->execute([':u' => $this->auth->id()]);

        if ($this->isAjax($request)) {
            return json_response(['ok' => true]);
        }

        $this->session->flash('success', 'Notificações marcadas como lidas.');
        return redirect('/notifications');
    }

    private function isAjax(ServerRequestInterface $request): bool
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    /** API para o sino do header (polling). */
    public function api(ServerRequestInterface $request)
    {
        if (!$this->auth->check()) {
            return json_response(['unread' => 0, 'items' => []]);
        }

        $userId = $this->auth->id();

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL');
        $count->execute([':u' => $userId]);
        $unread = (int) $count->fetchColumn();

        $items = $this->pdo->prepare(
            'SELECT n.id, n.user_id, n.created_by, n.type, n.message, n.link, n.read_at, n.created_at,
                    u.name AS user_name, cb.name AS created_by_name
               FROM notifications n
               LEFT JOIN users u ON u.id = n.user_id
               LEFT JOIN users cb ON cb.id = n.created_by
              WHERE n.user_id = :u
              ORDER BY n.id DESC
              LIMIT 8'
        );
        $items->execute([':u' => $userId]);

        return json_response(['unread' => $unread, 'items' => $items->fetchAll()]);
    }
}
