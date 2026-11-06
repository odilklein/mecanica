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
            "SELECT * FROM notifications WHERE user_id = :u ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"
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

        $this->session->flash('success', 'Notificações marcadas como lidas.');
        return redirect('/notifications');
    }
}
