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

class AuditController extends Controller
{
    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    public function index(ServerRequestInterface $request)
    {
        if ($response = $this->guardRoles(['admin'])) {
            return $response;
        }

        $query = $request->getQueryParams();
        $scope = (string) ($query['scope'] ?? 'changes');
        if (!in_array($scope, ['changes', 'logins', 'all'], true)) {
            $scope = 'changes';
        }
        $userId = (int) ($query['user_id'] ?? 0);
        $action = trim((string) ($query['action'] ?? ''));
        $entity = trim((string) ($query['entity'] ?? ''));
        $dateFrom = trim((string) ($query['date_from'] ?? ''));
        $dateTo = trim((string) ($query['date_to'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = '';
        }

        $where = [];
        $params = [];
        if ($scope === 'changes') {
            $where[] = "a.entity <> 'auth'";
        } elseif ($scope === 'logins') {
            $where[] = "a.entity = 'auth'";
        }
        if ($userId > 0) {
            $where[] = 'a.user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        if ($action !== '') {
            $where[] = 'a.action = :action';
            $params[':action'] = $action;
        }
        if ($entity !== '') {
            $where[] = 'a.entity = :entity';
            $params[':entity'] = $entity;
        }
        if ($dateFrom !== '') {
            $where[] = 'a.created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'a.created_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $perPage = 20;
        $page = max(1, (int) ($query['page'] ?? 1));
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs a' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.name AS user_name
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id"
            . $whereSql .
            " ORDER BY a.id DESC
               LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        $users = $this->pdo->query('SELECT id, name FROM users ORDER BY name')->fetchAll();
        $actions = $this->pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(\PDO::FETCH_COLUMN);
        $entities = $this->pdo->query('SELECT DISTINCT entity FROM audit_logs ORDER BY entity')->fetchAll(\PDO::FETCH_COLUMN);

        $queryParams = array_filter([
            'scope' => $scope,
            'user_id' => $userId > 0 ? (string) $userId : '',
            'action' => $action,
            'entity' => $entity,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ], static fn (string $value): bool => $value !== '');

        return $this->render('core::audit/index', [
            'title' => 'Auditoria',
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
            'filters' => [
                'scope' => $scope,
                'user_id' => $userId,
                'action' => $action,
                'entity' => $entity,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'users' => $users,
            'actions' => $actions,
            'entities' => $entities,
            'queryParams' => $queryParams,
        ]);
    }
}
