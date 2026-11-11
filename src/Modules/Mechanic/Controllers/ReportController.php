<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Modules\Mechanic\Support\BudgetStatus;
use Modules\Mechanic\Support\PartOrderStatus;
use Modules\Mechanic\Support\ServiceOrderStatus;
use Nyholm\Psr7\Response;
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

        $period = $this->normalizePeriod((string) ($request->getQueryParams()['period'] ?? '30'));
        $data = $this->reportData($period);

        return $this->render('mechanic::reports/index', [
            'title' => 'Relatórios',
            'period' => $period,
            'periodLabel' => $data['periodLabel'],
            'from' => $data['from'],
            'to' => $data['to'],
            'kpis' => $data['kpis'],
            'osByStatus' => $data['osByStatus'],
            'budgetsByStatus' => $data['budgetsByStatus'],
            'partsByStatus' => $data['partsByStatus'],
        ]);
    }

    public function export(ServerRequestInterface $request)
    {
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }

        $period = $this->normalizePeriod((string) ($request->getQueryParams()['period'] ?? '30'));
        $data = $this->reportData($period);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Indicador', 'Valor'], ';');
        fputcsv($handle, ['Período', $data['periodLabel']], ';');
        fputcsv($handle, ['Clientes', (string) $data['kpis']['clients']], ';');
        fputcsv($handle, ['Veículos', (string) $data['kpis']['vehicles']], ';');
        fputcsv($handle, ['OS em aberto', (string) $data['kpis']['open_orders']], ';');
        fputcsv($handle, ['Orçamentos aprovados', number_format((float) $data['kpis']['approved_revenue'], 2, ',', '')], ';');
        fputcsv($handle, ['Ticket médio aprovado', number_format((float) $data['kpis']['average_ticket'], 2, ',', '')], ';');
        fputcsv($handle, [], ';');

        fputcsv($handle, ['OS por status', 'Quantidade'], ';');
        foreach (ServiceOrderStatus::STATUSES as $key => $label) {
            fputcsv($handle, [$label, (string) ($data['osByStatus'][$key] ?? 0)], ';');
        }
        fputcsv($handle, [], ';');

        fputcsv($handle, ['Orçamentos por status', 'Quantidade', 'Total'], ';');
        foreach (BudgetStatus::STATUSES as $key => $label) {
            $row = $data['budgetsByStatus'][$key] ?? ['count' => 0, 'total' => 0.0];
            fputcsv($handle, [$label, (string) $row['count'], number_format((float) $row['total'], 2, ',', '')], ';');
        }
        fputcsv($handle, [], ';');

        fputcsv($handle, ['Pedidos de peças por status', 'Quantidade'], ';');
        foreach (PartOrderStatus::STATUSES as $key => $label) {
            fputcsv($handle, [$label, (string) ($data['partsByStatus'][$key] ?? 0)], ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return new Response(200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="relatorio-' . date('Y-m-d') . '.csv"',
        ], $csv);
    }

    private function normalizePeriod(string $period): string
    {
        return in_array($period, ['30', 'month', '90', 'year', 'all'], true) ? $period : '30';
    }

    /** @return array{from:?string,to:?string,periodLabel:string,kpis:array,osByStatus:array,budgetsByStatus:array,partsByStatus:array} */
    private function reportData(string $period): array
    {
        $today = date('Y-m-d');
        switch ($period) {
            case 'month':
                $from = date('Y-m-01');
                $to = $today;
                $label = 'Este mês';
                break;
            case '90':
                $from = date('Y-m-d', strtotime('-89 days'));
                $to = $today;
                $label = 'Últimos 90 dias';
                break;
            case 'year':
                $from = date('Y-01-01');
                $to = $today;
                $label = 'Este ano';
                break;
            case 'all':
                $from = null;
                $to = null;
                $label = 'Todo o período';
                break;
            default:
                $from = date('Y-m-d', strtotime('-29 days'));
                $to = $today;
                $label = 'Últimos 30 dias';
                break;
        }

        $osByStatus = [];
        $osSql = 'SELECT status, COUNT(*) AS c FROM service_orders'
            . ($from !== null ? ' WHERE entry_date BETWEEN :from AND :to' : '')
            . ' GROUP BY status';
        $osStmt = $this->pdo->prepare($osSql);
        $osStmt->execute($from !== null ? [':from' => $from, ':to' => $to] : []);
        foreach ($osStmt->fetchAll() as $row) {
            $osByStatus[$row['status']] = (int) $row['c'];
        }

        $budgetsByStatus = [];
        $budgetSql = 'SELECT status, COUNT(*) AS c, COALESCE(SUM(total),0) AS t FROM budgets'
            . ($from !== null ? ' WHERE budget_date BETWEEN :from AND :to' : '')
            . ' GROUP BY status';
        $budgetStmt = $this->pdo->prepare($budgetSql);
        $budgetStmt->execute($from !== null ? [':from' => $from, ':to' => $to] : []);
        foreach ($budgetStmt->fetchAll() as $row) {
            $budgetsByStatus[$row['status']] = [
                'count' => (int) $row['c'],
                'total' => (float) $row['t'],
            ];
        }

        $partsByStatus = [];
        $partSql = 'SELECT status, COUNT(*) AS c FROM part_orders'
            . ($from !== null ? ' WHERE DATE(created_at) BETWEEN :from AND :to' : '')
            . ' GROUP BY status';
        $partStmt = $this->pdo->prepare($partSql);
        $partStmt->execute($from !== null ? [':from' => $from, ':to' => $to] : []);
        foreach ($partStmt->fetchAll() as $row) {
            $partsByStatus[$row['status']] = (int) $row['c'];
        }

        $approvedSql = "SELECT COALESCE(SUM(total),0), COUNT(*) FROM budgets WHERE status = 'approved'"
            . ($from !== null ? ' AND budget_date BETWEEN :from AND :to' : '');
        $approvedStmt = $this->pdo->prepare($approvedSql);
        $approvedStmt->execute($from !== null ? [':from' => $from, ':to' => $to] : []);
        [$approvedRevenue, $approvedCount] = $approvedStmt->fetch(\PDO::FETCH_NUM);
        $approvedRevenue = (float) $approvedRevenue;
        $approvedCount = (int) $approvedCount;

        $kpis = [
            'clients' => (int) $this->pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn(),
            'vehicles' => (int) $this->pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn(),
            'open_orders' => (int) $this->pdo->query("SELECT COUNT(*) FROM service_orders WHERE status NOT IN ('delivered','cancelled')")->fetchColumn(),
            'approved_revenue' => $approvedRevenue,
            'approved_count' => $approvedCount,
            'average_ticket' => $approvedCount > 0 ? $approvedRevenue / $approvedCount : 0.0,
        ];

        return [
            'from' => $from,
            'to' => $to,
            'periodLabel' => $label,
            'kpis' => $kpis,
            'osByStatus' => $osByStatus,
            'budgetsByStatus' => $budgetsByStatus,
            'partsByStatus' => $partsByStatus,
        ];
    }
}
