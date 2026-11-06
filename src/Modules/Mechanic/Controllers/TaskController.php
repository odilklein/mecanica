<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

class TaskController extends Controller
{
    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    /** Adiciona uma tarefa à OS. */
    public function store(ServerRequestInterface $request, $orderId)
    {
        if (!$this->auth->check()) {
            return redirect('/login');
        }
        if ($response = $this->guardCsrf($request, '/service-orders/' . (int) $orderId)) {
            return $response;
        }

        $description = clean($request->getParsedBody()['description'] ?? '');
        if ($description !== '') {
            $stmt = $this->pdo->prepare('INSERT INTO service_order_tasks (order_id, description) VALUES (:o, :d)');
            $stmt->execute([':o' => (int) $orderId, ':d' => mb_substr($description, 0, 255)]);
            $this->session->flash('success', 'Tarefa adicionada.');
        }

        return redirect('/service-orders/' . (int) $orderId);
    }

    /** Alterna pendente <-> concluída. */
    public function toggle(ServerRequestInterface $request, $taskId)
    {
        if (!$this->auth->check()) {
            return redirect('/login');
        }

        $stmt = $this->pdo->prepare('SELECT id, order_id, status FROM service_order_tasks WHERE id = :id');
        $stmt->execute([':id' => (int) $taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            $this->session->flash('error', 'Tarefa não encontrada.');
            return redirect('/service-orders');
        }

        if ($response = $this->guardCsrf($request, '/service-orders/' . (int) $task['order_id'])) {
            return $response;
        }

        $newStatus = $task['status'] === 'done' ? 'pending' : 'done';
        $this->pdo->prepare('UPDATE service_order_tasks SET status = :s WHERE id = :id')
            ->execute([':s' => $newStatus, ':id' => (int) $taskId]);

        return redirect('/service-orders/' . (int) $task['order_id']);
    }
}
