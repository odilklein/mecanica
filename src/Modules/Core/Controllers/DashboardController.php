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

class DashboardController extends Controller
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

        $kpis = [
            'clients' => (int) $this->pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn(),
            'employees' => (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
            'open_orders' => (int) $this->pdo->query("SELECT COUNT(*) FROM service_orders WHERE status NOT IN ('delivered','cancelled')")->fetchColumn(),
            'pending_parts' => (int) $this->pdo->query("SELECT COUNT(*) FROM part_orders WHERE status IN ('requested','validated','ordered','on_the_way')")->fetchColumn(),
        ];

        $recent = $this->pdo->query(
            "SELECT so.id, so.number, so.status, so.priority, so.entry_date,
                    c.name AS client, v.plate AS vehicle_plate, v.model AS vehicle_model
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
              WHERE so.status NOT IN ('delivered','cancelled')
              ORDER BY so.number DESC
              LIMIT 10"
        )->fetchAll();

        return $this->render('core::dashboard', [
            'title' => 'Painel',
            'kpis' => $kpis,
            'recent' => $recent,
        ]);
    }
}
