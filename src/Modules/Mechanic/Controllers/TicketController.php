<?php
// =============================================
// TICKETS — central de atendimento estilo chat.
// Lista, conversa, criação e status dos tickets.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Modules\Core\Support\Notify;
use Modules\Mechanic\Services\TicketService;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

class TicketController extends Controller
{
    private PDO $pdo;
    private TicketService $tickets;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
        $this->tickets = new TicketService($pdo);
    }

    // =========================================================
    // PÁGINA
    // =========================================================

    /**
     * Usuários que podem ser atribuídos a um ticket.
     *
     * Administração, recepção e chefia veem todos; mecânicos veem apenas a
     * chefia, a recepção e os colegas com quem já dividiram uma OS.
     *
     * @return array<int, array<string, mixed>>
     */
    private function visibleUsers(int $userId): array
    {
        if ($this->auth->hasRole(['admin', 'secretary', 'lead_mechanic'])) {
            return $this->pdo->query(
                'SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name'
            )->fetchAll();
        }

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT u.id, u.name, u.role
               FROM users u
              WHERE u.is_active = 1
                AND (u.id = :me
                     OR u.role IN ('admin', 'secretary', 'lead_mechanic')
                     OR u.id IN (
                        SELECT r2.user_id
                          FROM service_order_responsibles r2
                         WHERE r2.order_id IN (
                            SELECT r1.order_id FROM service_order_responsibles r1 WHERE r1.user_id = :me
                         )
                     ))
              ORDER BY u.name"
        );
        $stmt->execute([':me' => $userId]);

        return $stmt->fetchAll();
    }

    public function index(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuth()) {
            return $response;
        }

        $userId = (int) $this->auth->id();
        $isAdmin = $this->auth->hasRole(['admin']);
        $query = $request->getQueryParams();

        $status = in_array(($query['status'] ?? ''), ['open', 'closed'], true) ? (string) $query['status'] : null;
        $search = clean($query['q'] ?? '');
        $scope = (string) ($query['scope'] ?? 'all');

        if ($scope === 'unread') {
            $status = 'open';
        }

        $tickets = $this->tickets->visibleTickets($userId, $isAdmin, 100, $status, $search);
        if ($scope === 'unread') {
            $tickets = array_values(array_filter($tickets, static fn (array $t): bool => (int) ($t['unread'] ?? 0) > 0));
        }

        $selectedId = (int) ($query['ticket'] ?? 0);
        if ($selectedId <= 0 && $tickets) {
            $selectedId = (int) $tickets[0]['id'];
        }

        $selected = $selectedId > 0 ? $this->tickets->find($selectedId) : null;
        if ($selected && !$this->tickets->canAccess($selected, $userId, $isAdmin)) {
            $selected = null;
            $selectedId = 0;
        }

        $messages = [];
        $context = null;
        $contextHtml = '';
        if ($selected) {
            if (!empty($selected['service_order_id'])) {
                $this->tickets->ensureOrderSummary((int) $selected['id'], (int) $selected['service_order_id']);
                $context = $this->tickets->orderContext((int) $selected['service_order_id']);
                if ($context) {
                    $contextHtml = $this->view->render('mechanic::tickets/_context', ['context' => $context]);
                }
            }
            $messages = $this->tickets->messages((int) $selected['id']);
            $this->tickets->markRead((int) $selected['id'], $userId);
        }

        // Só lista OS que o usuário pode acessar (evita expor OS de terceiros).
        $ordersSql = 'SELECT so.id, so.number, c.name AS client_name
                        FROM service_orders so
                        LEFT JOIN clients c ON c.id = so.client_id';
        if ($isAdmin) {
            $orders = $this->pdo->query($ordersSql . ' ORDER BY so.id DESC LIMIT 200')->fetchAll();
        } else {
            $ordersStmt = $this->pdo->prepare(
                $ordersSql . '
                 WHERE so.created_by = :uid_created
                    OR so.lead_mechanic_id = :uid_lead
                    OR EXISTS (SELECT 1 FROM service_order_responsibles r
                                WHERE r.order_id = so.id AND r.user_id = :uid_resp)
                 ORDER BY so.id DESC
                 LIMIT 200'
            );
            $ordersStmt->execute([
                ':uid_created' => $userId,
                ':uid_lead' => $userId,
                ':uid_resp' => $userId,
            ]);
            $orders = $ordersStmt->fetchAll();
        }

        $users = $this->visibleUsers($userId);

        return $this->render('mechanic::tickets/index', [
            'title' => 'Tickets',
            'tickets' => $tickets,
            'selected' => $selected,
            'selectedId' => $selectedId,
            'messages' => $messages,
            'context' => $context,
            'contextHtml' => $contextHtml,
            'ticketAudioEnabled' => audio_transcription_enabled(),
            'orders' => $orders,
            'users' => $users,
            'status' => $status,
            'scope' => $scope,
            'search' => $search,
            'pageStyles' => [
                '/assets/vendor/css/pages/app-chat.css',
            ],
            'pageScripts' => [
                '/js/tickets.js',
                '/js/ticket-audio.js',
            ],
        ]);
    }

    // =========================================================
    // API JSON
    // =========================================================

    /** Lista de tickets do usuário (busca e filtros do chat). */
    public function list(ServerRequestInterface $request)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $query = $request->getQueryParams();
        $status = in_array(($query['status'] ?? ''), ['open', 'closed'], true) ? (string) $query['status'] : null;
        $search = clean($query['q'] ?? '');
        $scope = (string) ($query['scope'] ?? 'all');

        if ($scope === 'unread') {
            $status = 'open';
        }

        $tickets = $this->tickets->visibleTickets(
            (int) $this->auth->id(),
            $this->auth->hasRole(['admin']),
            100,
            $status,
            $search
        );

        if ($scope === 'unread') {
            $tickets = array_values(array_filter($tickets, static fn (array $t): bool => (int) ($t['unread'] ?? 0) > 0));
        }

        return json_response(['tickets' => $tickets]);
    }

    /** Detalhe do ticket + mensagens (marca como lido). */
    public function show(ServerRequestInterface $request, $id)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $ticket = $this->tickets->find((int) $id);
        if (!$ticket) {
            return json_response(['error' => 'not_found'], 404);
        }
        if (!$this->tickets->canAccess($ticket, (int) $this->auth->id(), $this->auth->hasRole(['admin']))) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $this->tickets->markRead((int) $ticket['id'], (int) $this->auth->id());

        $context = null;
        $contextHtml = '';
        if (!empty($ticket['service_order_id'])) {
            $this->tickets->ensureOrderSummary((int) $ticket['id'], (int) $ticket['service_order_id']);
            $context = $this->tickets->orderContext((int) $ticket['service_order_id']);
            if ($context) {
                $contextHtml = $this->view->render('mechanic::tickets/_context', ['context' => $context]);
            }
        }

        return json_response([
            'ticket' => $ticket,
            'messages' => $this->tickets->messages((int) $ticket['id']),
            'context' => $context,
            'context_html' => $contextHtml,
            'audio_enabled' => audio_transcription_enabled(),
            'current_user_id' => (int) $this->auth->id(),
            'csrf' => $this->session->csrfToken(),
        ]);
    }

    /** Cria um ticket manual. */
    public function store(ServerRequestInterface $request)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            return json_response(['error' => 'csrf'], 403);
        }

        $subject = clean($body['subject'] ?? '');
        if ($subject === '') {
            return json_response(['error' => 'subject_required'], 422);
        }

        $orderId = (int) ($body['service_order_id'] ?? 0) ?: null;
        $assignedTo = (int) ($body['assigned_to'] ?? 0) ?: null;
        $userId = (int) $this->auth->id();

        if ($assignedTo) {
            $assignee = $this->pdo->prepare('SELECT 1 FROM users WHERE id = ? AND is_active = 1');
            $assignee->execute([$assignedTo]);
            if (!$assignee->fetchColumn()) {
                return json_response(['error' => 'invalid_assignee'], 422);
            }
        }

        // Ticket vinculado a uma OS: valida participação e reaproveita o existente.
        if ($orderId) {
            $stmt = $this->pdo->prepare('SELECT id, created_by, lead_mechanic_id FROM service_orders WHERE id = ?');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                return json_response(['error' => 'order_not_found'], 404);
            }
            if (!$this->tickets->canAccessOrder($order, $userId, $this->auth->hasRole(['admin']))) {
                return json_response(['error' => 'forbidden'], 403);
            }

            $existing = $this->tickets->findByOrder($orderId);
            if ($existing) {
                $ticketId = (int) $existing['id'];
                $message = clean($body['message'] ?? '');
                if ($message !== '') {
                    if (($existing['status'] ?? 'open') === 'closed') {
                        return json_response(['error' => 'ticket_closed'], 409);
                    }
                    $this->tickets->send($ticketId, $userId, $message);
                    $this->notifyTicket(
                        $ticketId,
                        'Nova mensagem no ticket "' . mb_substr((string) $existing['subject'], 0, 80) . '".',
                        $userId
                    );
                }

                return json_response(['ok' => true, 'id' => $ticketId, 'existing' => true], 200);
            }
        }

        $ticketId = $this->tickets->create($subject, $orderId, $assignedTo, $userId);

        $message = clean($body['message'] ?? '');
        if ($message !== '') {
            $this->tickets->send($ticketId, $userId, $message);
        }

        $this->notifyTicket(
            $ticketId,
            'Novo ticket "' . mb_substr($subject, 0, 80) . '".',
            $userId
        );

        return json_response(['ok' => true, 'id' => $ticketId], 201);
    }

    /** Envia mensagem no ticket. */
    public function sendMessage(ServerRequestInterface $request, $id)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $ticket = $this->tickets->find((int) $id);
        if (!$ticket) {
            return json_response(['error' => 'not_found'], 404);
        }

        $userId = (int) $this->auth->id();
        if (!$this->tickets->canAccess($ticket, $userId, $this->auth->hasRole(['admin']))) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            return json_response(['error' => 'csrf'], 403);
        }

        if (($ticket['status'] ?? 'open') === 'closed') {
            return json_response(['error' => 'ticket_closed'], 409);
        }

        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            return json_response(['error' => 'empty'], 422);
        }

        $created = $this->tickets->send((int) $ticket['id'], $userId, $message);
        $this->tickets->markRead((int) $ticket['id'], $userId);

        $this->notifyTicket(
            (int) $ticket['id'],
            'Nova mensagem no ticket "' . mb_substr((string) $ticket['subject'], 0, 80) . '".',
            $userId
        );

        return json_response(['ok' => true, 'message' => $created], 201);
    }

    /** Abre/fecha o ticket. */
    public function updateStatus(ServerRequestInterface $request, $id)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $ticket = $this->tickets->find((int) $id);
        if (!$ticket) {
            return json_response(['error' => 'not_found'], 404);
        }

        if (!$this->tickets->canAccess($ticket, (int) $this->auth->id(), $this->auth->hasRole(['admin']))) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            return json_response(['error' => 'csrf'], 403);
        }

        $status = (string) ($body['status'] ?? '');
        if (!in_array($status, ['open', 'closed'], true)) {
            return json_response(['error' => 'invalid_status'], 422);
        }

        $previous = (string) ($ticket['status'] ?? 'open');
        if ($previous !== $status) {
            $this->tickets->updateStatus((int) $ticket['id'], $status);
            $this->notifyTicket(
                (int) $ticket['id'],
                'Ticket "' . mb_substr((string) $ticket['subject'], 0, 80) . '" '
                    . ($status === 'closed' ? 'encerrado' : 'reaberto') . '.',
                (int) $this->auth->id()
            );
        }

        return json_response(['ok' => true, 'status' => $status]);
    }

    /** Altera o responsável pelo ticket. */
    public function assign(ServerRequestInterface $request, $id)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $ticket = $this->tickets->find((int) $id);
        if (!$ticket) {
            return json_response(['error' => 'not_found'], 404);
        }

        $userId = (int) $this->auth->id();
        if (!$this->tickets->canAccess($ticket, $userId, $this->auth->hasRole(['admin']))) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            return json_response(['error' => 'csrf'], 403);
        }

        $assignedTo = (int) ($body['assigned_to'] ?? 0) ?: null;
        $assignedName = null;
        if ($assignedTo !== null) {
            $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$assignedTo]);
            $assignedName = $stmt->fetchColumn();
            if (!$assignedName) {
                return json_response(['error' => 'invalid_assignee'], 422);
            }
        }

        $this->pdo->prepare('UPDATE tickets SET assigned_to = :assigned WHERE id = :id')
            ->execute([':assigned' => $assignedTo, ':id' => (int) $ticket['id']]);

        if ($assignedTo !== null && $assignedTo !== $userId) {
            Notify::to(
                $this->pdo,
                [$assignedTo],
                'ticket',
                'Você é o novo responsável pelo ticket "' . mb_substr((string) $ticket['subject'], 0, 80) . '".',
                '/tickets?ticket=' . (int) $ticket['id'],
                $userId
            );
        }

        return json_response([
            'ok' => true,
            'assigned_to' => $assignedTo,
            'assigned_name' => $assignedName,
        ]);
    }

    /** Notifica todos os participantes do ticket, exceto quem executou a ação. */
    private function notifyTicket(int $ticketId, string $message, int $actorId): void
    {
        $recipients = array_values(array_filter(
            $this->tickets->participants($ticketId),
            static fn (int $uid): bool => $uid > 0 && $uid !== $actorId
        ));
        if (!$recipients) {
            return;
        }

        Notify::to(
            $this->pdo,
            $recipients,
            'ticket',
            $message,
            '/tickets?ticket=' . $ticketId,
            $actorId
        );
    }

    /** Abre (ou garante) o ticket vinculado a uma OS. */
    public function forOrder(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guardAuth()) {
            return $response;
        }

        $stmt = $this->pdo->prepare('SELECT id, number, created_by, lead_mechanic_id FROM service_orders WHERE id = ?');
        $stmt->execute([(int) $id]);
        $order = $stmt->fetch();

        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect('/service-orders');
        }

        if (!$this->tickets->canAccessOrder($order, (int) $this->auth->id(), $this->auth->hasRole(['admin']))) {
            $this->session->flash('error', 'Você não participa do ticket desta OS.');
            return redirect('/service-orders/' . (int) $id);
        }

        $existing = $this->tickets->findByOrder((int) $id);
        $ticketId = $this->tickets->ensureForOrder((int) $id);

        if (!$existing) {
            $this->notifyTicket(
                $ticketId,
                'Novo ticket da OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) . '.',
                (int) $this->auth->id()
            );
        }

        return redirect('/tickets?ticket=' . $ticketId);
    }
}
