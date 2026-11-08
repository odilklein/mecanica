<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Support\Notify;
use Modules\Mechanic\Resources\ServiceOrderResource;
use Modules\Mechanic\Support\ServiceOrderStatus;
use Modules\Mechanic\Services\TicketService;
use Psr\Http\Message\ServerRequestInterface;

class ServiceOrderController extends CrudController
{
    protected function resource(): Resource
    {
        return new ServiceOrderResource();
    }

    // =========================================================
    // WIZARD (novo atendimento)
    // =========================================================

    public function create(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $clients = $this->pdo->query('SELECT id, name, phone, city FROM clients ORDER BY name')->fetchAll();
        $vehicles = $this->pdo->query('SELECT id, client_id, plate, brand, model, year FROM vehicles ORDER BY plate')->fetchAll();
        $mechanics = $this->pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 AND role IN ('lead_mechanic','mechanic') ORDER BY name")->fetchAll();
        $ticketUsers = $this->pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name")->fetchAll();
        $brands = $this->pdo->query('SELECT code, name FROM fipe_brands ORDER BY name')->fetchAll();

        return $this->render('mechanic::service_orders/wizard', [
            'title' => 'Novo atendimento',
            'resource' => $resource,
            'clients' => $clients,
            'vehicles' => $vehicles,
            'mechanics' => $mechanics,
            'ticketUsers' => $ticketUsers,
            'brands' => $brands,
            'ticketAudioEnabled' => audio_transcription_enabled(),
            'pageStyles' => array_merge($resource->formStyles(), [
                '/assets/vendor/libs/bs-stepper/bs-stepper.css',
            ]),
            'pageScripts' => array_merge($resource->formScripts(), [
                '/assets/vendor/libs/bs-stepper/bs-stepper.js',
                '/js/fipe.js',
                '/js/os-wizard.js',
                '/js/ticket-audio.js',
            ]),
        ]);
    }

    public function store(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path())) {
            return $response;
        }

        $body = $request->getParsedBody();
        $userId = $this->auth->id();

        $this->pdo->beginTransaction();
        try {
            // Cliente: existente ou novo
            $clientId = (int) ($body['client_id'] ?? 0);
            if ($clientId <= 0) {
                $name = clean($body['client_name'] ?? '');
                if ($name === '') {
                    throw new \RuntimeException('Informe o nome do cliente.');
                }
                $stmt = $this->pdo->prepare(
                    'INSERT INTO clients (name, phone, email, zip, address, address_number, neighborhood, city, state)
                     VALUES (:n, :p, :e, :z, :a, :an, :nb, :c, :s)'
                );
                $stmt->execute([
                    ':n' => $name,
                    ':p' => clean($body['client_phone'] ?? ''),
                    ':e' => strtolower(clean($body['client_email'] ?? '')),
                    ':z' => clean($body['client_zip'] ?? ''),
                    ':a' => clean($body['client_address'] ?? ''),
                    ':an' => clean($body['client_address_number'] ?? ''),
                    ':nb' => clean($body['client_neighborhood'] ?? ''),
                    ':c' => clean($body['client_city'] ?? ''),
                    ':s' => strtoupper(clean($body['client_state'] ?? '')),
                ]);
                $clientId = (int) $this->pdo->lastInsertId();
            }

            // Veículo: existente ou novo
            $vehicleId = (int) ($body['vehicle_id'] ?? 0);
            if ($vehicleId <= 0) {
                $plate = strtoupper(clean($body['plate'] ?? ''));
                if ($plate === '') {
                    throw new \RuntimeException('Informe a placa do veículo.');
                }
                $stmt = $this->pdo->prepare(
                    'INSERT INTO vehicles (client_id, plate, brand, model, year, color, fipe_brand_code, fipe_model_code, fipe_year_code, fipe_year, fipe_value)
                     VALUES (:cl, :pl, :br, :mo, :yr, :co, :fbc, :fmc, :fyc, :fy, :fv)'
                );
                $brandCode = clean($body['fipe_brand_code'] ?? '');
                $modelCode = clean($body['fipe_model_code'] ?? '');
                $yearCode = clean($body['fipe_year_code'] ?? '');
                $brand = clean($body['brand'] ?? '');
                $model = clean($body['model'] ?? '');
                $year = (int) ($body['fipe_year'] ?? 0) ?: (int) ($body['year'] ?? 0);
                if ($year === 0 && $yearCode !== '' && preg_match('/(\d{4})/', $yearCode, $ym)) {
                    $year = (int) $ym[1];
                }
                if ($brandCode !== '' && $brand === '') {
                    $b = $this->pdo->prepare('SELECT name FROM fipe_brands WHERE code = :c');
                    $b->execute([':c' => $brandCode]);
                    $brand = (string) ($b->fetchColumn() ?: '');
                }
                if ($brandCode !== '' && $modelCode !== '' && $model === '') {
                    $m = $this->pdo->prepare('SELECT m.name FROM fipe_models m JOIN fipe_brands b ON b.id = m.brand_id WHERE b.code = :b AND m.code = :m');
                    $m->execute([':b' => $brandCode, ':m' => $modelCode]);
                    $model = (string) ($m->fetchColumn() ?: '');
                }
                $stmt->execute([
                    ':cl' => $clientId,
                    ':pl' => $plate,
                    ':br' => $brand,
                    ':mo' => $model,
                    ':yr' => $year ?: null,
                    ':co' => clean($body['color'] ?? ''),
                    ':fbc' => $brandCode,
                    ':fmc' => $modelCode,
                    ':fyc' => $yearCode,
                    ':fy' => $year ?: null,
                    ':fv' => ($body['fipe_value'] ?? '') !== '' ? (float) $body['fipe_value'] : null,
                ]);
                $vehicleId = (int) $this->pdo->lastInsertId();
            }

            $mechanics = array_values(array_filter(array_map('intval', (array) ($body['mechanics'] ?? []))));
            $lead = (int) ($body['lead_mechanic_id'] ?? 0) ?: ($mechanics[0] ?? null);

            // OS
            $number = (int) $this->pdo->query('SELECT COALESCE(MAX(number), 0) + 1 FROM service_orders')->fetchColumn();
            $stmt = $this->pdo->prepare(
                'INSERT INTO service_orders (number, client_id, vehicle_id, status, priority, initial_complaint, internal_notes, diagnosis, entry_date, lead_mechanic_id, created_by)
                 VALUES (:num, :cl, :ve, :st, :pr, :ic, :inotes, :diag, :ed, :lead, :cb)'
            );
            $stmt->execute([
                ':num' => $number,
                ':cl' => $clientId,
                ':ve' => $vehicleId,
                ':st' => 'received',
                ':pr' => clean($body['priority'] ?? 'normal'),
                ':ic' => clean($body['initial_complaint'] ?? ''),
                ':inotes' => clean($body['internal_notes'] ?? ''),
                ':diag' => clean($body['diagnosis'] ?? ''),
                ':ed' => date('Y-m-d'),
                ':lead' => $lead,
                ':cb' => $userId,
            ]);
            $orderId = (int) $this->pdo->lastInsertId();

            // Tarefas (aceita string simples ou objeto expandido)
            $tasks = json_decode((string) ($body['tasks_json'] ?? '[]'), true) ?: [];
            $taskStmt = $this->pdo->prepare(
                'INSERT INTO service_order_tasks (order_id, description, status, assigned_to, due_date, notes, created_by)
                 VALUES (:o, :d, :s, :a, :due, :n, :u)'
            );
            foreach ($tasks as $task) {
                if (is_array($task)) {
                    $desc = trim((string) ($task['description'] ?? ''));
                    $assignedTo = (int) ($task['assigned_to'] ?? 0) ?: null;
                    $dueDate = trim((string) ($task['due_date'] ?? ''));
                    $dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) ? $dueDate : null;
                    $notes = trim((string) ($task['notes'] ?? '')) ?: null;
                } else {
                    $desc = trim((string) $task);
                    $assignedTo = null;
                    $dueDate = null;
                    $notes = null;
                }

                if ($desc !== '') {
                    $taskStmt->execute([
                        ':o' => $orderId,
                        ':d' => mb_substr($desc, 0, 255),
                        ':s' => 'pending',
                        ':a' => $assignedTo,
                        ':due' => $dueDate,
                        ':n' => $notes ? mb_substr($notes, 0, 2000) : null,
                        ':u' => $userId,
                    ]);
                }
            }

            // Peças → pedido de peças com itens
            $parts = json_decode((string) ($body['parts_json'] ?? '[]'), true) ?: [];
            if ($parts) {
                $this->pdo->prepare("INSERT INTO part_orders (service_order_id, status, created_by) VALUES (:o, 'requested', :u)")
                    ->execute([':o' => $orderId, ':u' => $userId]);
                $partOrderId = (int) $this->pdo->lastInsertId();
                $itemStmt = $this->pdo->prepare(
                    'INSERT INTO part_order_items
                        (part_order_id, description, quantity, sku, unit_price, supplier, product_url, catalog_notes)
                     VALUES (:p, :d, :q, :sku, :price, :supplier, :url, :notes)'
                );
                foreach ($parts as $part) {
                    $desc = trim((string) (is_array($part) ? ($part['description'] ?? '') : $part));
                    $qty = is_array($part) ? max(1, (int) ($part['quantity'] ?? 1)) : 1;
                    if ($desc === '') {
                        continue;
                    }
                    $itemStmt->execute([
                        ':p' => $partOrderId,
                        ':d' => mb_substr($desc, 0, 200),
                        ':q' => $qty,
                        ':sku' => is_array($part) ? (trim((string) ($part['sku'] ?? '')) ?: null) : null,
                        ':price' => is_array($part) && isset($part['unit_price']) && $part['unit_price'] !== ''
                            ? (float) $part['unit_price']
                            : null,
                        ':supplier' => is_array($part) ? (trim((string) ($part['supplier'] ?? '')) ?: null) : null,
                        ':url' => is_array($part) ? (trim((string) ($part['product_url'] ?? '')) ?: null) : null,
                        ':notes' => is_array($part) ? (trim((string) ($part['catalog_notes'] ?? '')) ?: null) : null,
                    ]);
                }
            }

            // Responsáveis
            $respStmt = $this->pdo->prepare('INSERT IGNORE INTO service_order_responsibles (order_id, user_id) VALUES (:o, :u)');
            foreach ($mechanics as $mechanicId) {
                $respStmt->execute([':o' => $orderId, ':u' => $mechanicId]);
            }

            // Evento inicial
            $this->pdo->prepare('INSERT INTO service_order_events (order_id, type, description, created_by) VALUES (:o, :t, :d, :u)')
                ->execute([':o' => $orderId, ':t' => 'created', ':d' => 'OS criada.', ':u' => $userId]);

            // Ticket da OS: todo atendimento cria uma conversa vinculada.
            $ticketService = new TicketService($this->pdo);
            $ticketId = $ticketService->ensureForOrder($orderId, [
                'subject' => clean($body['ticket_subject'] ?? ''),
                'assigned_to' => (int) ($body['ticket_assigned_to'] ?? 0) ?: null,
            ]);

            $initialMessage = trim((string) ($body['ticket_initial_message'] ?? ''));
            if ($initialMessage !== '') {
                $ticketService->send($ticketId, (int) $userId, $initialMessage);
            }
            $ticketAssigned = (int) ($body['ticket_assigned_to'] ?? 0) ?: null;

            $this->pdo->commit();

            if ($mechanics) {
                Notify::to($this->pdo, $mechanics, 'os_created', 'Você foi designado para a OS #' . str_pad((string) $number, 4, '0', STR_PAD_LEFT) . '.', '/service-orders/' . $orderId, $userId);
            }

            if ($ticketId && $ticketAssigned && $ticketAssigned !== (int) $userId) {
                Notify::to(
                    $this->pdo,
                    [$ticketAssigned],
                    'ticket',
                    'Você foi designado para o ticket da OS #' . str_pad((string) $number, 4, '0', STR_PAD_LEFT) . '.',
                    '/tickets?ticket=' . $ticketId,
                    $userId
                );
            }

            $this->session->flash('success', 'OS #' . str_pad((string) $number, 4, '0', STR_PAD_LEFT) . ' criada com sucesso.');
            return redirect($resource->path() . '/' . $orderId);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('Wizard OS: ' . $e->getMessage());
            $this->session->flash('error', 'Não foi possível criar a OS: ' . $e->getMessage());
            return redirect($resource->path() . '/create');
        }
    }

    // =========================================================
    // DETALHES
    // =========================================================

    public function show(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }

        $tasks = $this->pdo->prepare(
            'SELECT t.*, u.name AS assigned_name, c.name AS creator_name
               FROM service_order_tasks t
               LEFT JOIN users u ON u.id = t.assigned_to
               LEFT JOIN users c ON c.id = t.created_by
              WHERE t.order_id = :id
              ORDER BY t.id'
        );
        $tasks->execute([':id' => (int) $id]);

        $responsibles = $this->pdo->prepare('SELECT r.*, u.name, u.role FROM service_order_responsibles r JOIN users u ON u.id = r.user_id WHERE r.order_id = :id ORDER BY u.name');
        $responsibles->execute([':id' => (int) $id]);

        $events = $this->pdo->prepare('SELECT e.*, u.name AS author FROM service_order_events e LEFT JOIN users u ON u.id = e.created_by WHERE e.order_id = :id ORDER BY e.id ASC');
        $events->execute([':id' => (int) $id]);

        $parts = $this->pdo->prepare(
            "SELECT p.*, GROUP_CONCAT(CONCAT(i.description, ' ×', i.quantity) SEPARATOR ', ') AS items
               FROM part_orders p LEFT JOIN part_order_items i ON i.part_order_id = p.id
              WHERE p.service_order_id = :id GROUP BY p.id ORDER BY p.id DESC"
        );
        $parts->execute([':id' => (int) $id]);

        $delivery = $this->pdo->prepare('SELECT * FROM service_order_deliveries WHERE order_id = :id ORDER BY id DESC LIMIT 1');
        $delivery->execute([':id' => (int) $id]);

        // Orçamento mais recente vinculado à OS e seus itens.
        $budgetStmt = $this->pdo->prepare('SELECT * FROM budgets WHERE service_order_id = :id ORDER BY id DESC LIMIT 1');
        $budgetStmt->execute([':id' => (int) $id]);
        $budget = $budgetStmt->fetch() ?: null;

        $budgetItems = [];
        if ($budget) {
            $itemsStmt = $this->pdo->prepare('SELECT * FROM budget_items WHERE budget_id = :id ORDER BY id');
            $itemsStmt->execute([':id' => (int) $budget['id']]);
            $budgetItems = $itemsStmt->fetchAll();
        }

        // Separa serviços das peças necessárias e enriquece as peças com o catálogo.
        $serviceItems = [];
        $partItems = [];
        foreach ($budgetItems as $item) {
            if (($item['type'] ?? '') === 'service') {
                $serviceItems[] = $item;
            } else {
                $partItems[] = $item;
            }
        }
        $partItems = $this->enrichPartItems($partItems);

        // Ticket da OS: garante a conversa e o resumo automático.
        $ticketService = new TicketService($this->pdo);
        $ticketId = $ticketService->ensureForOrder((int) $id);
        $ticket = $ticketService->find($ticketId);
        $ticketMessages = [];
        if ($ticket && $ticketService->canAccess($ticket, (int) $this->auth->id(), $this->auth->hasRole(['admin']))) {
            $ticketService->markRead($ticketId, (int) $this->auth->id());
            $ticketMessages = $ticketService->messages($ticketId);
        } else {
            $ticket = null;
        }

        $clientOrders = $this->pdo->prepare('SELECT COUNT(*) FROM service_orders WHERE client_id = :id');
        $clientOrders->execute([':id' => (int) $order['client_id']]);
        $clientOrdersCount = (int) $clientOrders->fetchColumn();

        $mechanics = $this->pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 AND role IN ('lead_mechanic','mechanic') ORDER BY name")->fetchAll();

        $statusAvailable = array_values(array_unique(array_merge(
            [$order['status']],
            array_diff(ServiceOrderStatus::next((string) $order['status']), ['delivered'])
        )));

        return $this->render('mechanic::service_orders/show', [
            'title' => 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT),
            'resource' => $resource,
            'order' => $order,
            'tasks' => $tasks->fetchAll(),
            'responsibles' => $responsibles->fetchAll(),
            'events' => $events->fetchAll(),
            'parts' => $parts->fetchAll(),
            'delivery' => $delivery->fetch() ?: null,
            'budget' => $budget,
            'budgetItems' => $budgetItems,
            'serviceItems' => $serviceItems,
            'partItems' => $partItems,
            'ticket' => $ticket,
            'ticketMessages' => $ticketMessages,
            'ticketAudioEnabled' => audio_transcription_enabled(),
            'clientOrdersCount' => $clientOrdersCount,
            'mechanics' => $mechanics,
            'statuses' => ServiceOrderStatus::STATUSES,
            'statusAvailable' => $statusAvailable,
            'pageStyles' => [
                '/assets/vendor/css/pages/app-chat.css',
            ],
            'pageScripts' => [
                '/js/ticket-audio.js',
                '/js/os-ticket.js',
            ],
        ]);
    }

    /**
     * Tenta associar cada peça do orçamento ao catálogo, para exibir
     * imagem, marca, código e categoria nos cards da OS.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function enrichPartItems(array $items): array
    {
        foreach ($items as &$item) {
            $item['catalog'] = null;
            $name = trim((string) ($item['description'] ?? ''));
            if ($name === '') {
                continue;
            }

            $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $prefix = implode(' ', array_slice($words, 0, 4));
            if ($prefix === '') {
                continue;
            }

            $stmt = $this->pdo->prepare(
                "SELECT id, name, brand, part_code, sku, category, image, price
                   FROM parts
                  WHERE is_active = 1
                    AND (LOWER(name) = LOWER(:exact) OR name LIKE :prefix)
                  ORDER BY (LOWER(name) = LOWER(:exact2)) DESC, (image IS NULL) ASC, id ASC
                  LIMIT 1"
            );
            $stmt->execute([
                ':exact' => $name,
                ':exact2' => $name,
                ':prefix' => $prefix . '%',
            ]);
            $catalog = $stmt->fetch();
            if ($catalog) {
                $item['catalog'] = $catalog;
            }
        }
        unset($item);

        return $items;
    }

    // =========================================================
    // AÇÕES
    // =========================================================

    public function updateStatus(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $status = (string) ($request->getParsedBody()['status'] ?? '');
        if (!isset(ServiceOrderStatus::STATUSES[$status])) {
            $this->session->flash('error', 'Status inválido.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $completion = $status === 'delivered' ? date('Y-m-d') : null;
        $this->pdo->prepare('UPDATE service_orders SET status = :s, completion_date = :c WHERE id = :id')
            ->execute([':s' => $status, ':c' => $completion, ':id' => (int) $id]);

        $this->event((int) $id, 'status', 'Status alterado para "' . ServiceOrderStatus::label($status) . '".');

        $order = $this->findOrder((int) $id);
        if ($order) {
            $recipients = array_filter([
                (int) ($order['lead_mechanic_id'] ?? 0),
                (int) ($order['created_by'] ?? 0),
                (int) ($this->auth->id() ?? 0),
            ]);
            Notify::to($this->pdo, $recipients, 'os_status', 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) . ' mudou para "' . ServiceOrderStatus::label($status) . '".', '/service-orders/' . (int) $id, $this->auth->id());
        }

        $this->session->flash('success', 'Status atualizado para "' . ServiceOrderStatus::label($status) . '".');
        return redirect($resource->path() . '/' . (int) $id);
    }

    public function updateDiagnosis(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $body = $request->getParsedBody();
        $this->pdo->prepare('UPDATE service_orders SET diagnosis = :d WHERE id = :id')
            ->execute([':d' => clean($body['diagnosis'] ?? ''), ':id' => (int) $id]);

        $this->event((int) $id, 'diagnosis', 'Diagnóstico atualizado.');
        $this->session->flash('success', 'Diagnóstico salvo.');
        return redirect($resource->path() . '/' . (int) $id);
    }

    public function updateInternalNotes(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $notes = trim((string) ($request->getParsedBody()['internal_notes'] ?? ''));
        $this->pdo->prepare('UPDATE service_orders SET internal_notes = :n WHERE id = :id')
            ->execute([':n' => $notes, ':id' => (int) $id]);

        // Atualiza o resumo automático exibido no ticket.
        (new TicketService($this->pdo))->ensureForOrder((int) $id);

        $this->event((int) $id, 'diagnosis', 'Observações internas atualizadas.');
        $this->session->flash('success', 'Observações internas salvas.');
        return redirect($resource->path() . '/' . (int) $id);
    }

    public function assignResponsible(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $userId = (int) ($request->getParsedBody()['user_id'] ?? 0);
        if ($userId > 0) {
            $this->pdo->prepare('INSERT IGNORE INTO service_order_responsibles (order_id, user_id) VALUES (:o, :u)')
                ->execute([':o' => (int) $id, ':u' => $userId]);
            $this->event((int) $id, 'responsible', 'Mecânico designado.');
            Notify::to($this->pdo, [$userId], 'os_created', 'Você foi designado para uma OS.', '/service-orders/' . (int) $id, $this->auth->id());
        }

        return redirect($resource->path() . '/' . (int) $id);
    }

    public function removeResponsible(ServerRequestInterface $request, $id, $userId)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $this->pdo->prepare('DELETE FROM service_order_responsibles WHERE order_id = :o AND user_id = :u')
            ->execute([':o' => (int) $id, ':u' => (int) $userId]);

        return redirect($resource->path() . '/' . (int) $id);
    }

    public function addTask(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $body = $request->getParsedBody();
        $desc = clean($body['description'] ?? '');
        if ($desc !== '') {
            $assignedTo = (int) ($body['assigned_to'] ?? 0) ?: null;
            $dueDate = clean($body['due_date'] ?? '');
            $dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) ? $dueDate : null;
            $notes = trim((string) ($body['notes'] ?? '')) ?: null;

            $this->pdo->prepare(
                'INSERT INTO service_order_tasks (order_id, description, status, assigned_to, due_date, notes, created_by)
                 VALUES (:o, :d, :s, :a, :due, :n, :u)'
            )->execute([
                ':o' => (int) $id,
                ':d' => mb_substr($desc, 0, 255),
                ':s' => 'pending',
                ':a' => $assignedTo,
                ':due' => $dueDate,
                ':n' => $notes ? mb_substr($notes, 0, 2000) : null,
                ':u' => $this->auth->id(),
            ]);

            $this->event((int) $id, 'task', 'Tarefa adicionada: ' . mb_substr($desc, 0, 120));

            if ($assignedTo) {
                Notify::to($this->pdo, [$assignedTo], 'task', 'Você recebeu uma tarefa na OS #' . (int) $id . '.', '/service-orders/' . (int) $id, $this->auth->id());
            }
        }

        return redirect($resource->path() . '/' . (int) $id);
    }

    public function updateTaskStatus(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $taskId = (int) ($request->getParsedBody()['task_id'] ?? 0);
        $status = (string) ($request->getParsedBody()['task_status'] ?? '');
        if ($taskId > 0 && in_array($status, ['pending', 'in_progress', 'done'], true)) {
            $completedAt = $status === 'done' ? date('Y-m-d H:i:s') : null;
            $completedBy = $status === 'done' ? $this->auth->id() : null;
            $this->pdo->prepare(
                'UPDATE service_order_tasks
                    SET status = :s, completed_at = :ca, completed_by = :cb
                  WHERE id = :id AND order_id = :o'
            )->execute([
                ':s' => $status,
                ':ca' => $completedAt,
                ':cb' => $completedBy,
                ':id' => $taskId,
                ':o' => (int) $id,
            ]);

            $labels = ['pending' => 'Pendente', 'in_progress' => 'Em execução', 'done' => 'Concluída'];
            $this->event((int) $id, 'task', 'Tarefa atualizada para "' . ($labels[$status] ?? $status) . '".');
        }

        return redirect($resource->path() . '/' . (int) $id);
    }

    public function updateTask(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $body = $request->getParsedBody();
        $taskId = (int) ($body['task_id'] ?? 0);
        if ($taskId <= 0) {
            return redirect($resource->path() . '/' . (int) $id);
        }

        $desc = clean($body['description'] ?? '');
        if ($desc === '') {
            return redirect($resource->path() . '/' . (int) $id);
        }

        $status = (string) ($body['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'in_progress', 'done'], true)) {
            $status = 'pending';
        }
        $assignedTo = (int) ($body['assigned_to'] ?? 0) ?: null;
        $dueDate = clean($body['due_date'] ?? '');
        $dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) ? $dueDate : null;
        $notes = trim((string) ($body['notes'] ?? '')) ?: null;

        $completedAt = $status === 'done' ? date('Y-m-d H:i:s') : null;
        $completedBy = $status === 'done' ? $this->auth->id() : null;

        $this->pdo->prepare(
            'UPDATE service_order_tasks
                SET description = :d, status = :s, assigned_to = :a, due_date = :due,
                    notes = :n, completed_at = :ca, completed_by = :cb
              WHERE id = :id AND order_id = :o'
        )->execute([
            ':d' => mb_substr($desc, 0, 255),
            ':s' => $status,
            ':a' => $assignedTo,
            ':due' => $dueDate,
            ':n' => $notes ? mb_substr($notes, 0, 2000) : null,
            ':ca' => $completedAt,
            ':cb' => $completedBy,
            ':id' => $taskId,
            ':o' => (int) $id,
        ]);

        $this->event((int) $id, 'task', 'Tarefa atualizada: ' . mb_substr($desc, 0, 120));
        $this->session->flash('success', 'Tarefa atualizada.');
        return redirect($resource->path() . '/' . (int) $id);
    }

    public function registerDelivery(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $body = $request->getParsedBody();
        $this->pdo->prepare(
            'INSERT INTO service_order_deliveries (order_id, modality, responsible, destination, exit_km)
             VALUES (:o, :m, :r, :d, :km)'
        )->execute([
            ':o' => (int) $id,
            ':m' => ($body['modality'] ?? 'pickup') === 'delivery' ? 'delivery' : 'pickup',
            ':r' => clean($body['responsible'] ?? ''),
            ':d' => clean($body['destination'] ?? ''),
            ':km' => (int) ($body['exit_km'] ?? 0) ?: null,
        ]);

        $this->event((int) $id, 'delivery', 'Entrega/retirada registrada.');
        $this->session->flash('success', 'Entrega registrada.');
        return redirect($resource->path() . '/' . (int) $id);
    }

    private function event(int $orderId, string $type, string $description): void
    {
        $this->pdo->prepare('INSERT INTO service_order_events (order_id, type, description, created_by) VALUES (:o, :t, :d, :u)')
            ->execute([':o' => $orderId, ':t' => $type, ':d' => mb_substr($description, 0, 255), ':u' => $this->auth->id()]);
    }

    private function findOrder(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT so.*, c.name AS client_name, c.phone AS client_phone, c.email AS client_email,
                    c.cpf AS client_cpf, c.city AS client_city, c.state AS client_state,
                    c.created_at AS client_since,
                    v.plate AS vehicle_plate, v.model AS vehicle_model, v.brand AS vehicle_brand,
                    v.year AS vehicle_year, v.color AS vehicle_color, v.current_km AS vehicle_km,
                    v.fipe_value AS vehicle_fipe_value,
                    u.name AS lead_mechanic, creator.name AS creator_name
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
               LEFT JOIN users u ON u.id = so.lead_mechanic_id
               LEFT JOIN users creator ON creator.id = so.created_by
              WHERE so.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
