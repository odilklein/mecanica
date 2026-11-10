<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use DateTimeImmutable;
use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Modules\Mechanic\Support\OrderAccess;
use Modules\Mechanic\Support\PartOrderStatus;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Painel principal — visão gerencial no padrão do dashboard CRM do Vuexy.
 * Todos os números e séries dos gráficos vêm do banco (MySQL).
 */
class DashboardController extends Controller
{
    /** Avanço estimado de cada etapa da OS (barra de progresso). */
    private const STAGE_PROGRESS = [
        'received' => 10,
        'in_analysis' => 30,
        'awaiting_approval' => 45,
        'awaiting_part' => 55,
        'in_progress' => 70,
        'ready_for_delivery' => 90,
    ];

    private const MONTH_LABELS = [
        1 => 'jan', 2 => 'fev', 3 => 'mar', 4 => 'abr', 5 => 'mai', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'set', 10 => 'out', 11 => 'nov', 12 => 'dez',
    ];

    private const WEEKDAY_LABELS = [
        1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sáb', 7 => 'dom',
    ];

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

        // Data de referência única (evita divergência PHP x MySQL).
        $today = new DateTimeImmutable((string) $this->pdo->query('SELECT CURDATE()')->fetchColumn());

        // Faturamento/orçamentos são restritos aos mesmos papéis de /reports.
        $canSeeFinancials = $this->auth->hasRole(['admin', 'secretary', 'lead_mechanic']);
        $canCreateOrder = $canSeeFinancials;
        $canManageParts = $canSeeFinancials;
        $scopeUserId = OrderAccess::isMechanicOnly($this->auth) ? (int) $this->auth->id() : null;

        $ordersByDay = $this->ordersByDay($today, 7, $scopeUserId);
        $openOrdersByDay = $this->openOrdersByDay($today, 7, $scopeUserId);
        $weekOrders = array_sum($ordersByDay['values']);
        $previousWeek = $this->countOrders(
            $today->modify('-13 days'),
            $today->modify('-7 days'),
            $scopeUserId
        );

        $kpis = $this->kpis($today, $weekOrders, $previousWeek, $scopeUserId);

        return $this->render('core::dashboard', [
            'title' => 'Painel',
            'kpis' => $kpis,
            'canSeeFinancials' => $canSeeFinancials,
            'canCreateOrder' => $canCreateOrder,
            'canManageParts' => $canManageParts,
            'topClients' => $canSeeFinancials
                ? $this->topClients(6)
                : $this->topClientsByOrders(6),
            'inProgress' => $this->ordersInProgress(6),
            'latestBudgets' => $canSeeFinancials ? $this->latestBudgets(5) : [],
            'events' => $this->recentEvents(5),
            'chartData' => [
                'ordersByDay' => $ordersByDay,
                'openOrdersByDay' => $openOrdersByDay,
                'revenueByMonth' => $canSeeFinancials ? $this->revenueByMonth($today, 6) : null,
                'indicatorTabs' => $this->indicatorTabs($today, 6, $canSeeFinancials, $scopeUserId),
                'partsByStatus' => $this->partsByStatus($scopeUserId),
                'ordersByWeek' => $this->ordersByWeek($today, 8, $scopeUserId),
            ],
            'pageStyles' => [
                '/assets/vendor/libs/apex-charts/apex-charts.css',
            ],
            'pageScripts' => [
                '/assets/vendor/libs/apex-charts/apexcharts.js',
                '/js/dashboard.js',
            ],
        ]);
    }

    /**
     * Indicadores dos cartões do topo.
     *
     * @return array<string, mixed>
     */
    private function kpis(DateTimeImmutable $today, int $weekOrders, int $previousWeek, ?int $scopeUserId = null): array
    {
        $row = $this->pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM clients) AS clients,
                (SELECT COUNT(*) FROM service_orders WHERE status NOT IN ('delivered','cancelled')) AS open_orders,
                (SELECT COUNT(*) FROM service_orders WHERE status = 'ready_for_delivery') AS ready_orders,
                (SELECT COUNT(*) FROM service_orders WHERE status = 'awaiting_approval') AS awaiting_approval,
                (SELECT COUNT(*) FROM service_orders WHERE status = 'awaiting_part') AS awaiting_part,
                (SELECT COUNT(*) FROM part_orders WHERE status IN ('requested','validated','ordered','on_the_way')) AS pending_parts,
                (SELECT COUNT(*) FROM part_orders WHERE status = 'on_the_way') AS parts_on_the_way,
                (SELECT COALESCE(SUM(total),0) FROM budgets WHERE status = 'approved') AS approved_revenue,
                (SELECT COUNT(*) FROM budgets WHERE status = 'approved') AS approved_count,
                (SELECT COUNT(*) FROM budgets WHERE status = 'rejected') AS rejected_count,
                (SELECT COALESCE(AVG(total),0) FROM budgets
                  WHERE status = 'approved'
                    AND budget_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS ticket_avg,
                (SELECT COALESCE(AVG(total),0) FROM budgets
                  WHERE status = 'approved'
                    AND budget_date >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
                    AND budget_date < DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS ticket_previous,
                (SELECT COALESCE(AVG(total),0) FROM budgets WHERE status = 'approved') AS ticket_all_time,
                (SELECT COUNT(*) FROM tickets WHERE status = 'open') AS open_tickets"
        )->fetch();

        // Mecânico vê apenas os números das OS/tickets em que participa.
        if ($scopeUserId !== null) {
            foreach ($this->scopedKpis($scopeUserId) as $key => $value) {
                $row[$key] = $value;
            }
            // Dados financeiros não são exibidos para o mecânico.
            $row['approved_revenue'] = 0.0;
            $row['approved_count'] = 0;
            $row['rejected_count'] = 0;
            $row['ticket_avg'] = 0.0;
            $row['ticket_previous'] = 0.0;
            $row['ticket_all_time'] = 0.0;
        }

        $approved = (int) $row['approved_count'];
        $rejected = (int) $row['rejected_count'];
        $decided = $approved + $rejected;
        $ticketAvg = (float) $row['ticket_avg'];
        $ticketPrevious = (float) $row['ticket_previous'];

        return [
            'clients' => (int) $row['clients'],
            'open_orders' => (int) $row['open_orders'],
            'ready_orders' => (int) $row['ready_orders'],
            'awaiting_approval' => (int) $row['awaiting_approval'],
            'awaiting_part' => (int) $row['awaiting_part'],
            'pending_parts' => (int) $row['pending_parts'],
            'parts_on_the_way' => (int) $row['parts_on_the_way'],
            'approved_revenue' => (float) $row['approved_revenue'],
            'approved_count' => $approved,
            'approval_rate' => $decided > 0 ? ($approved / $decided) * 100 : 0.0,
            'ticket_avg' => $ticketAvg,
            'ticket_previous' => $ticketPrevious,
            'ticket_all_time' => (float) $row['ticket_all_time'],
            'ticket_delta' => $this->percentDelta($ticketAvg, $ticketPrevious),
            'week_orders' => $weekOrders,
            'previous_week' => $previousWeek,
            'week_delta' => $this->percentDelta($weekOrders, $previousWeek),
            'open_tickets' => (int) $row['open_tickets'],
        ];
    }

    /**
     * Contadores do dashboard restritos às OS/tickets do mecânico.
     *
     * @return array<string, int>
     */
    private function scopedKpis(int $userId): array
    {
        [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');

        $scalar = function (string $sql, array $params = []): int {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        };

        $orderWhere = ' WHERE ' . $scopeSql;
        $partFrom = ' FROM part_orders po INNER JOIN service_orders so ON so.id = po.service_order_id WHERE ' . $scopeSql;

        $ticketParams = array_merge([
            ':ticket_created' => $userId,
            ':ticket_assigned' => $userId,
        ], $scopeParams);
        $ticketSql = "SELECT COUNT(*) FROM tickets t
                       LEFT JOIN service_orders so ON so.id = t.service_order_id
                      WHERE t.status = 'open'
                        AND (t.created_by = :ticket_created
                             OR t.assigned_to = :ticket_assigned
                             OR (so.id IS NOT NULL AND ({$scopeSql})))";

        return [
            'clients' => $scalar(
                'SELECT COUNT(DISTINCT so.client_id) FROM service_orders so' . $orderWhere
                    . ' AND so.client_id IS NOT NULL',
                $scopeParams
            ),
            'open_orders' => $scalar(
                "SELECT COUNT(*) FROM service_orders so" . $orderWhere
                    . " AND so.status NOT IN ('delivered','cancelled')",
                $scopeParams
            ),
            'ready_orders' => $scalar(
                "SELECT COUNT(*) FROM service_orders so" . $orderWhere
                    . " AND so.status = 'ready_for_delivery'",
                $scopeParams
            ),
            'awaiting_approval' => $scalar(
                "SELECT COUNT(*) FROM service_orders so" . $orderWhere
                    . " AND so.status = 'awaiting_approval'",
                $scopeParams
            ),
            'awaiting_part' => $scalar(
                "SELECT COUNT(*) FROM service_orders so" . $orderWhere
                    . " AND so.status = 'awaiting_part'",
                $scopeParams
            ),
            'pending_parts' => $scalar(
                "SELECT COUNT(*) " . $partFrom
                    . " AND po.status IN ('requested','validated','ordered','on_the_way')",
                $scopeParams
            ),
            'parts_on_the_way' => $scalar(
                "SELECT COUNT(*) " . $partFrom . " AND po.status = 'on_the_way'",
                $scopeParams
            ),
            'open_tickets' => $scalar($ticketSql, $ticketParams),
        ];
    }

    /**
     * OS criadas por dia nos últimos N dias (para o gráfico de barras).
     *
     * @return array{labels: array<int, string>, values: array<int, int>, dates: array<int, string>}
     */
    private function ordersByDay(DateTimeImmutable $today, int $days, ?int $scopeUserId = null): array
    {
        $start = $today->modify('-' . ($days - 1) . ' days');
        $sql = 'SELECT so.entry_date AS d, COUNT(*) AS c
                  FROM service_orders so
                 WHERE so.entry_date BETWEEN :start AND :end';
        $params = [':start' => $start->format('Y-m-d'), ':end' => $today->format('Y-m-d')];

        if ($scopeUserId !== null) {
            [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
            $sql .= ' AND ' . $scopeSql;
            $params = array_merge($params, $scopeParams);
        }

        $sql .= ' GROUP BY so.entry_date';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['d']] = (int) $row['c'];
        }

        $labels = [];
        $values = [];
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $today->modify('-' . $i . ' days');
            $key = $date->format('Y-m-d');
            $labels[] = self::WEEKDAY_LABELS[(int) $date->format('N')];
            $values[] = $counts[$key] ?? 0;
            $dates[] = $date->format('d/m');
        }

        return ['labels' => $labels, 'values' => $values, 'dates' => $dates];
    }

    /**
     * OS abertas ao final de cada um dos últimos N dias.
     *
     * Considera a OS aberta enquanto não foi entregue/cancelada, usando a
     * data de conclusão para reconstruir os dias anteriores.
     *
     * @return array{labels: array<int, string>, values: array<int, int>, dates: array<int, string>}
     */
    private function openOrdersByDay(DateTimeImmutable $today, int $days, ?int $scopeUserId = null): array
    {
        $daySelects = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $daySelects[] = 'SELECT CURDATE() - INTERVAL ' . $i . ' DAY AS day';
        }

        $sql = 'SELECT d.day, COUNT(so.id) AS c
                  FROM (' . implode(' UNION ALL ', $daySelects) . ') d
                  LEFT JOIN service_orders so
                    ON so.entry_date <= d.day
                   AND (
                        so.status NOT IN (\'delivered\', \'cancelled\')
                        OR (so.status = \'delivered\'
                            AND so.completion_date IS NOT NULL
                            AND so.completion_date > d.day)
                   )';
        $params = [];
        if ($scopeUserId !== null) {
            [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
            $sql .= ' AND ' . $scopeSql;
            $params = $scopeParams;
        }
        $sql .= ' GROUP BY d.day ORDER BY d.day';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['day']] = (int) $row['c'];
        }

        $labels = [];
        $values = [];
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $today->modify('-' . $i . ' days');
            $key = $date->format('Y-m-d');
            $labels[] = self::WEEKDAY_LABELS[(int) $date->format('N')];
            $values[] = $counts[$key] ?? 0;
            $dates[] = $date->format('d/m');
        }

        return ['labels' => $labels, 'values' => $values, 'dates' => $dates];
    }

    /** Faturamento aprovado por mês (últimos N meses). */
    private function revenueByMonth(DateTimeImmutable $today, int $months): array
    {
        $start = $today->modify('first day of this month')->modify('-' . ($months - 1) . ' months');
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(budget_date, '%Y-%m') AS ym, COALESCE(SUM(total),0) AS total
               FROM budgets
              WHERE status = 'approved' AND budget_date >= :start
              GROUP BY ym"
        );
        $stmt->execute([':start' => $start->format('Y-m-d')]);

        $totals = [];
        foreach ($stmt->fetchAll() as $row) {
            $totals[(string) $row['ym']] = (float) $row['total'];
        }

        $labels = [];
        $values = [];
        for ($i = 0; $i < $months; $i++) {
            $date = $start->modify('+' . $i . ' months');
            $labels[] = self::MONTH_LABELS[(int) $date->format('n')];
            $values[] = $totals[$date->format('Y-m')] ?? 0.0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Séries mensais das abas "Indicadores".
     *
     * @return array<int, array<string, mixed>>
     */
    private function indicatorTabs(DateTimeImmutable $today, int $months, bool $canSeeFinancials, ?int $scopeUserId = null): array
    {
        $start = $today->modify('first day of this month')->modify('-' . ($months - 1) . ' months');
        $scopeSql = '';
        $scopeParams = [];
        if ($scopeUserId !== null) {
            [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
        }

        $ordersSql = 'SELECT DATE_FORMAT(so.entry_date, "%Y-%m") AS ym, COUNT(*) AS c
                        FROM service_orders so
                       WHERE so.entry_date >= :start';
        $deliveredSql = "SELECT DATE_FORMAT(so.completion_date, '%Y-%m') AS ym, COUNT(*) AS c
                           FROM service_orders so
                          WHERE so.status = 'delivered' AND so.completion_date >= :start";
        $partsSql = 'SELECT DATE_FORMAT(po.created_at, "%Y-%m") AS ym, COUNT(*) AS c
                       FROM part_orders po
                       INNER JOIN service_orders so ON so.id = po.service_order_id
                      WHERE po.created_at >= :start';
        if ($scopeSql !== '') {
            $ordersSql .= ' AND ' . $scopeSql;
            $deliveredSql .= ' AND ' . $scopeSql;
            $partsSql .= ' AND ' . $scopeSql;
        }

        $buckets = [
            'orders' => $this->monthlyCounts($ordersSql . ' GROUP BY ym', $start, $scopeParams),
            'delivered' => $this->monthlyCounts($deliveredSql . ' GROUP BY ym', $start, $scopeParams),
            'parts' => $this->monthlyCounts($partsSql . ' GROUP BY ym', $start, $scopeParams),
        ];

        if ($canSeeFinancials) {
            $buckets['budgets'] = $this->monthlyCounts(
                'SELECT DATE_FORMAT(budget_date, "%Y-%m") AS ym, COUNT(*) AS c
                   FROM budgets
                  WHERE budget_date >= :start
                  GROUP BY ym',
                $start
            );
            $buckets['revenue'] = $this->monthlyTotals($start);
        }

        $labels = [];
        for ($i = 0; $i < $months; $i++) {
            $labels[] = self::MONTH_LABELS[(int) $start->modify('+' . $i . ' months')->format('n')];
        }

        $definitions = [
            ['key' => 'orders', 'label' => 'Atendimentos', 'icon' => 'clipboard-list', 'type' => 'count'],
            ['key' => 'delivered', 'label' => 'Entregas', 'icon' => 'truck-delivery', 'type' => 'count'],
            ['key' => 'parts', 'label' => 'Peças', 'icon' => 'package', 'type' => 'count'],
        ];

        if ($canSeeFinancials) {
            $definitions[] = ['key' => 'budgets', 'label' => 'Orçamentos', 'icon' => 'file-text', 'type' => 'count'];
            $definitions[] = ['key' => 'revenue', 'label' => 'Receita', 'icon' => 'currency-real', 'type' => 'money'];
        }

        $tabs = [];
        foreach ($definitions as $i => $definition) {
            $tabs[] = $definition + [
                'active' => $i === 0,
                'labels' => $labels,
                'values' => $this->fillMonths($buckets[$definition['key']], $start, $months, $definition['type'] === 'money'),
                'highlight' => $months - 1,
            ];
        }

        return $tabs;
    }

    /** @return array<string, int> */
    private function monthlyCounts(string $sql, DateTimeImmutable $start, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([':start' => $start->format('Y-m-d')], $params));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['ym']] = (int) $row['c'];
        }
        return $result;
    }

    /** @return array<string, float> */
    private function monthlyTotals(DateTimeImmutable $start): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(budget_date, '%Y-%m') AS ym, COALESCE(SUM(total),0) AS total
               FROM budgets
              WHERE status = 'approved' AND budget_date >= :start
              GROUP BY ym"
        );
        $stmt->execute([':start' => $start->format('Y-m-d')]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['ym']] = (float) $row['total'];
        }
        return $result;
    }

    /** @param array<string, int|float> $bucket */
    private function fillMonths(array $bucket, DateTimeImmutable $start, int $months, bool $money): array
    {
        $values = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->modify('+' . $i . ' months')->format('Y-m');
            $values[] = $money
                ? round((float) ($bucket[$key] ?? 0.0), 2)
                : (int) ($bucket[$key] ?? 0);
        }
        return $values;
    }

    /** Pedidos de peças por status (gráfico de rosca). */
    private function partsByStatus(?int $scopeUserId = null): array
    {
        if ($scopeUserId !== null) {
            [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
            $stmt = $this->pdo->prepare(
                'SELECT po.status, COUNT(*) AS c
                   FROM part_orders po
                   INNER JOIN service_orders so ON so.id = po.service_order_id
                  WHERE ' . $scopeSql . '
                  GROUP BY po.status'
            );
            $stmt->execute($scopeParams);
            $rows = $stmt->fetchAll();
        } else {
            $rows = $this->pdo->query('SELECT status, COUNT(*) AS c FROM part_orders GROUP BY status')->fetchAll();
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }

        $labels = [];
        $values = [];
        $keys = [];
        foreach (PartOrderStatus::STATUSES as $key => $label) {
            $keys[] = $key;
            $labels[] = $label;
            $values[] = $counts[$key] ?? 0;
        }

        return ['keys' => $keys, 'labels' => $labels, 'values' => $values];
    }

    /** Clientes ordenados pelo volume de orçamentos emitidos. */
    private function topClients(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.name,
                    COALESCE(SUM(b.total), 0) AS total,
                    COUNT(b.id) AS budgets
               FROM budgets b
               INNER JOIN clients c ON c.id = b.client_id
              WHERE b.status = 'approved'
              GROUP BY c.id, c.name
              ORDER BY total DESC
              LIMIT {$limit}"
        );
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $grandTotal = (float) $this->pdo->query(
            "SELECT COALESCE(SUM(total), 0) FROM budgets WHERE status = 'approved'"
        )->fetchColumn();

        foreach ($rows as &$row) {
            $row['total'] = (float) $row['total'];
            $row['budgets'] = (int) $row['budgets'];
            $row['share'] = $grandTotal > 0 ? ($row['total'] / $grandTotal) * 100 : 0.0;
        }
        unset($row);

        return $rows;
    }

    /**
     * Clientes com mais OS (visão sem valores financeiros).
     */
    private function topClientsByOrders(int $limit): array
    {
        [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
        $sql = "SELECT c.id, c.name, COUNT(so.id) AS total
                  FROM service_orders so
                  INNER JOIN clients c ON c.id = so.client_id"
            . ($scopeSql !== '' ? ' WHERE ' . $scopeSql : '') . "
                 GROUP BY c.id, c.name
                 ORDER BY total DESC
                 LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($scopeParams);
        $rows = $stmt->fetchAll();
        $countSql = 'SELECT COUNT(*) FROM service_orders so'
            . ($scopeSql !== '' ? ' WHERE ' . $scopeSql : '');
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($scopeParams);
        $grandTotal = (int) $countStmt->fetchColumn();

        foreach ($rows as &$row) {
            $row['total'] = (int) $row['total'];
            $row['budgets'] = 0;
            $row['share'] = $grandTotal > 0 ? ($row['total'] / $grandTotal) * 100 : 0.0;
        }
        unset($row);

        return $rows;
    }

    /** OS criadas por semana (últimas N semanas) — gráfico de área. */
    private function ordersByWeek(DateTimeImmutable $today, int $weeks, ?int $scopeUserId = null): array
    {
        $monday = $today->modify('monday this week');
        $start = $monday->modify('-' . ($weeks - 1) . ' weeks');

        $sql = "SELECT DATE_FORMAT(DATE_SUB(so.entry_date, INTERVAL WEEKDAY(so.entry_date) DAY), '%Y-%m-%d') AS week_start,
                       COUNT(*) AS c
                  FROM service_orders so
                 WHERE so.entry_date >= :start";
        $params = [':start' => $start->format('Y-m-d')];
        if ($scopeUserId !== null) {
            [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
            $sql .= ' AND ' . $scopeSql;
            $params = array_merge($params, $scopeParams);
        }
        $sql .= ' GROUP BY week_start';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['week_start']] = (int) $row['c'];
        }

        $labels = [];
        $values = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->modify('+' . $i . ' weeks');
            $labels[] = $weekStart->format('d/m');
            $values[] = $counts[$weekStart->format('Y-m-d')] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Ordens de serviço abertas com percentual estimado de andamento. */
    private function ordersInProgress(int $limit): array
    {
        [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
        $sql = "SELECT so.id, so.number, so.status, so.priority,
                    c.name AS client, v.plate AS vehicle_plate, v.model AS vehicle_model
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
              WHERE so.status NOT IN ('delivered','cancelled')"
            . ($scopeSql !== '' ? ' AND ' . $scopeSql : '') . "
              ORDER BY so.number DESC
              LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($scopeParams);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['progress'] = self::STAGE_PROGRESS[$row['status']] ?? 5;
        }
        unset($row);

        return $rows;
    }

    /** Últimos orçamentos emitidos. */
    private function latestBudgets(int $limit): array
    {
        return $this->pdo->query(
            "SELECT b.id, b.number, b.budget_date, b.status, b.total, c.name AS client
               FROM budgets b
               LEFT JOIN clients c ON c.id = b.client_id
              ORDER BY COALESCE(b.budget_date, DATE(b.created_at)) DESC, b.number DESC
              LIMIT {$limit}"
        )->fetchAll();
    }

    /** Atividade recente (eventos das OS). */
    private function recentEvents(int $limit): array
    {
        [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
        $sql = "SELECT e.type, e.description, e.created_at,
                    TIMESTAMPDIFF(MINUTE, e.created_at, NOW()) AS minutes_ago,
                    so.id AS order_id, so.number AS order_number,
                    u.name AS user_name
               FROM service_order_events e
               LEFT JOIN service_orders so ON so.id = e.order_id
               LEFT JOIN users u ON u.id = e.created_by"
            . ($scopeSql !== '' ? ' WHERE ' . $scopeSql : '') . "
              ORDER BY e.id DESC
              LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($scopeParams);
        return $stmt->fetchAll();
    }

    /** Quantidade de OS criadas em um intervalo fechado. */
    private function countOrders(DateTimeImmutable $from, DateTimeImmutable $to, ?int $scopeUserId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM service_orders so WHERE so.entry_date BETWEEN :from AND :to';
        $params = [':from' => $from->format('Y-m-d'), ':to' => $to->format('Y-m-d')];
        if ($scopeUserId !== null) {
            [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
            $sql .= ' AND ' . $scopeSql;
            $params = array_merge($params, $scopeParams);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Variação percentual (100% quando o período anterior é zero). */
    private function percentDelta(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return (($current - $previous) / $previous) * 100;
    }
}
