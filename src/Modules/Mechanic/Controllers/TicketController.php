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

        $orders = $this->pdo->query(
            'SELECT so.id, so.number, c.name AS client_name
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
              ORDER BY so.id DESC
              LIMIT 200'
        )->fetchAll();

        $users = $this->pdo->query(
            "SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name"
        )->fetchAll();

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
            return json_response(['error' => 'csrf'], 419);
        }

        $subject = clean($body['subject'] ?? '');
        if ($subject === '') {
            return json_response(['error' => 'subject_required'], 422);
        }

        $orderId = (int) ($body['service_order_id'] ?? 0) ?: null;
        $assignedTo = (int) ($body['assigned_to'] ?? 0) ?: null;
        $userId = (int) $this->auth->id();

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
                    $this->tickets->send($ticketId, $userId, $message);
                }

                return json_response(['ok' => true, 'id' => $ticketId, 'existing' => true], 200);
            }
        }

        $ticketId = $this->tickets->create($subject, $orderId, $assignedTo, $userId);

        $message = clean($body['message'] ?? '');
        if ($message !== '') {
            $this->tickets->send($ticketId, $userId, $message);
        }

        if ($assignedTo && $assignedTo !== $userId) {
            Notify::to(
                $this->pdo,
                [$assignedTo],
                'ticket',
                'Você foi designado para o ticket "' . mb_substr($subject, 0, 80) . '".',
                '/tickets?ticket=' . $ticketId,
                $userId
            );
        }

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
            return json_response(['error' => 'csrf'], 419);
        }

        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            return json_response(['error' => 'empty'], 422);
        }

        $created = $this->tickets->send((int) $ticket['id'], $userId, $message);
        $this->tickets->markRead((int) $ticket['id'], $userId);

        $recipients = array_values(array_filter([
            (int) $ticket['created_by'],
            (int) ($ticket['assigned_to'] ?? 0),
        ], static fn (int $uid): bool => $uid > 0 && $uid !== $userId));

        if ($recipients) {
            Notify::to(
                $this->pdo,
                $recipients,
                'ticket',
                'Nova mensagem no ticket "' . mb_substr((string) $ticket['subject'], 0, 80) . '".',
                '/tickets?ticket=' . (int) $ticket['id'],
                $userId
            );
        }

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
            return json_response(['error' => 'csrf'], 419);
        }

        $status = (string) ($body['status'] ?? '');
        if (!in_array($status, ['open', 'closed'], true)) {
            return json_response(['error' => 'invalid_status'], 422);
        }

        $this->tickets->updateStatus((int) $ticket['id'], $status);

        return json_response(['ok' => true, 'status' => $status]);
    }

    /** Abre (ou garante) o ticket vinculado a uma OS. */
    public function forOrder(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guardAuth()) {
            return $response;
        }

        $stmt = $this->pdo->prepare('SELECT id, created_by, lead_mechanic_id FROM service_orders WHERE id = ?');
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

        $ticketId = $this->tickets->ensureForOrder((int) $id);

        return redirect('/tickets?ticket=' . $ticketId);
    }
}
