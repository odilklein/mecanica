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

class ReportController extends Controller
{
    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    public function index(ServerRequestInterface $request)
    {
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }

        $osByStatus = [];
        foreach ($this->pdo->query('SELECT status, COUNT(*) AS c FROM service_orders GROUP BY status')->fetchAll() as $row) {
            $osByStatus[$row['status']] = (int) $row['c'];
        }

        $budgetsByStatus = [];
        foreach ($this->pdo->query('SELECT status, COUNT(*) AS c, COALESCE(SUM(total),0) AS t FROM budgets GROUP BY status')->fetchAll() as $row) {
            $budgetsByStatus[$row['status']] = ['count' => (int) $row['c'], 'total' => (float) $row['t']];
        }

        $partsByStatus = [];
        foreach ($this->pdo->query('SELECT status, COUNT(*) AS c FROM part_orders GROUP BY status')->fetchAll() as $row) {
            $partsByStatus[$row['status']] = (int) $row['c'];
        }

        $kpis = [
            'clients' => (int) $this->pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn(),
            'vehicles' => (int) $this->pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn(),
            'open_orders' => (int) $this->pdo->query("SELECT COUNT(*) FROM service_orders WHERE status NOT IN ('delivered','cancelled')")->fetchColumn(),
            'approved_revenue' => (float) $this->pdo->query("SELECT COALESCE(SUM(total),0) FROM budgets WHERE status = 'approved'")->fetchColumn(),
        ];

        return $this->render('mechanic::reports/index', [
            'title' => 'Relatórios',
            'kpis' => $kpis,
            'osByStatus' => $osByStatus,
            'budgetsByStatus' => $budgetsByStatus,
            'partsByStatus' => $partsByStatus,
        ]);
    }
}
