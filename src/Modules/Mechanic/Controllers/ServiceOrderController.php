<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Support\Notify;
use Modules\Mechanic\Resources\ServiceOrderResource;
use Modules\Mechanic\Support\OrderAccess;
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
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }

        $clients = $this->pdo->query('SELECT id, name, phone, city FROM clients WHERE is_active = 1 ORDER BY name')->fetchAll();
        $vehicles = $this->pdo->query('SELECT id, client_id, plate, brand, model, year FROM vehicles WHERE is_active = 1 ORDER BY plate')->fetchAll();
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
                '/assets/vendor/libs/pickr/pickr-themes.css',
            ]),
            'pageScripts' => array_merge($resource->formScripts(), [
                '/assets/vendor/libs/bs-stepper/bs-stepper.js',
                '/js/fipe.js',
                '/js/os-wizard.js',
                '/assets/vendor/libs/pickr/pickr.js',
                '/js/color-picker.js',
            ]),
        ]);
    }

    public function store(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
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

            $clientCheck = $this->pdo->prepare('SELECT 1 FROM clients WHERE id = ? AND is_active = 1');
            $clientCheck->execute([$clientId]);
            if (!$clientCheck->fetchColumn()) {
                throw new \RuntimeException('Cliente inativo ou não encontrado.');
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

            $vehicleCheck = $this->pdo->prepare(
                'SELECT 1 FROM vehicles WHERE id = ? AND client_id = ? AND is_active = 1'
            );
            $vehicleCheck->execute([$vehicleId, $clientId]);
            if (!$vehicleCheck->fetchColumn()) {
                throw new \RuntimeException('Veículo inválido ou inativo para o cliente selecionado.');
            }

            $mechanics = array_values(array_unique(array_filter(
                array_map('intval', (array) ($body['mechanics'] ?? []))
            )));
            if ($mechanics) {
                $in = implode(',', array_fill(0, count($mechanics), '?'));
                $validStmt = $this->pdo->prepare(
                    "SELECT id FROM users WHERE is_active = 1 AND role IN ('lead_mechanic','mechanic') AND id IN ({$in})"
                );
                $validStmt->execute($mechanics);
                $mechanics = array_values(array_map('intval', $validStmt->fetchAll(\PDO::FETCH_COLUMN)));
            }

            $lead = (int) ($body['lead_mechanic_id'] ?? 0);
            if ($lead > 0 && !in_array($lead, $mechanics, true)) {
                $leadCheck = $this->pdo->prepare(
                    "SELECT 1 FROM users WHERE id = ? AND is_active = 1 AND role IN ('lead_mechanic','mechanic')"
                );
                $leadCheck->execute([$lead]);
                if ($leadCheck->fetchColumn()) {
                    $mechanics[] = $lead;
                } else {
                    $lead = 0;
                }
            }
            $lead = $lead ?: ($mechanics[0] ?? null);

            $priority = clean($body['priority'] ?? 'normal');
            if (!isset(ServiceOrderStatus::PRIORITIES[$priority])) {
                $priority = 'normal';
            }

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
                ':pr' => $priority,
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
                    if ($assignedTo !== null) {
                        $assignedCheck = $this->pdo->prepare(
                            "SELECT 1 FROM users WHERE id = ? AND is_active = 1 AND role IN ('lead_mechanic','mechanic')"
                        );
                        $assignedCheck->execute([$assignedTo]);
                        if (!$assignedCheck->fetchColumn()) {
                            $assignedTo = null;
                        }
                    }
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
            $parts = array_values(array_filter($parts, static function ($part): bool {
                $desc = is_array($part) ? (string) ($part['description'] ?? '') : (string) $part;
                return trim($desc) !== '';
            }));
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
            $ticketAssigned = (int) ($body['ticket_assigned_to'] ?? 0) ?: null;
            if ($ticketAssigned !== null) {
                $ticketUserCheck = $this->pdo->prepare('SELECT 1 FROM users WHERE id = ? AND is_active = 1');
                $ticketUserCheck->execute([$ticketAssigned]);
                if (!$ticketUserCheck->fetchColumn()) {
                    $ticketAssigned = null;
                }
            }

            $ticketService = new TicketService($this->pdo);
            $ticketId = $ticketService->ensureForOrder($orderId, [
                'subject' => clean($body['ticket_subject'] ?? ''),
                'assigned_to' => $ticketAssigned,
            ]);

            $initialMessage = trim((string) ($body['ticket_initial_message'] ?? ''));
            if ($initialMessage !== '') {
                $ticketService->send($ticketId, (int) $userId, $initialMessage);
            }

            $this->pdo->commit();

            $notifyMechanics = array_values(array_filter(
                $mechanics,
                static fn ($uid): bool => (int) $uid !== (int) $userId
            ));
            if ($notifyMechanics) {
                Notify::to($this->pdo, $notifyMechanics, 'os_created', 'Você foi designado para a OS #' . str_pad((string) $number, 4, '0', STR_PAD_LEFT) . '.', '/service-orders/' . $orderId, $userId);
            }

            if ($parts) {
                $partRecipients = array_values(array_unique(array_filter(
                    array_merge($mechanics, [$lead]),
                    static fn ($uid): bool => (int) $uid > 0 && (int) $uid !== (int) $userId
                )));
                if ($partRecipients) {
                    Notify::to(
                        $this->pdo,
                        $partRecipients,
                        'part',
                        'OS #' . str_pad((string) $number, 4, '0', STR_PAD_LEFT) . ' tem ' . count($parts) . ' peça(s) solicitada(s).',
                        '/service-orders/' . $orderId,
                        $userId
                    );
                }
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
    // LISTAGEM / ACESSO
    // =========================================================

    /** DataTables: mecânicos veem apenas as OS em que participam. */
    public function datatable(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }
        if ($resource->roles() && !$this->auth->hasRole($resource->roles())) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $scopeUserId = OrderAccess::isMechanicOnly($this->auth) ? (int) $this->auth->id() : null;
        $data = [];
        foreach ($resource->paginate($this->pdo, 1, 10000, '', $scopeUserId)['items'] as $row) {
            $row = $resource->datatableRow($row);
            unset($row['password'], $row['remember_token']);
            $data[] = $row;
        }

        return json_response(['data' => $data]);
    }

    /** Bloqueia o acesso de mecânicos a OS em que não participam. */
    private function guardOrderAccess(array $order): ?\Psr\Http\Message\ResponseInterface
    {
        if (OrderAccess::canAccess($this->pdo, $this->auth, $order)) {
            return null;
        }
        $this->session->flash('error', 'Você não tem acesso a esta ordem de serviço.');
        return redirect('/service-orders');
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

        if ($response = $this->guardOrderAccess($order)) {
            return $response;
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
            'canManage' => $this->auth->hasRole(['admin', 'secretary', 'lead_mechanic']),
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
    // EDIÇÃO / EXCLUSÃO
    // =========================================================

    public function edit(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }
        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect('/service-orders');
        }
        if ($response = $this->guardOrderAccess($order)) {
            return $response;
        }
        return parent::edit($request, $id);
    }

    public function update(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }
        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect('/service-orders');
        }
        if ($response = $this->guardOrderAccess($order)) {
            return $response;
        }

        $body = $request->getParsedBody();
        $clientId = (int) ($body['client_id'] ?? 0);
        $vehicleId = (int) ($body['vehicle_id'] ?? 0);

        if ($clientId <= 0) {
            $this->session->flash('error', 'Selecione o cliente.');
            return redirect('/service-orders/' . (int) $id . '/edit');
        }
        $clientCheck = $this->pdo->prepare('SELECT 1 FROM clients WHERE id = ? AND is_active = 1');
        $clientCheck->execute([$clientId]);
        if (!$clientCheck->fetchColumn()) {
            $this->session->flash('error', 'Cliente inválido ou inativo.');
            return redirect('/service-orders/' . (int) $id . '/edit');
        }

        if ($vehicleId > 0) {
            $vehicleCheck = $this->pdo->prepare(
                'SELECT 1 FROM vehicles WHERE id = ? AND client_id = ? AND is_active = 1'
            );
            $vehicleCheck->execute([$vehicleId, $clientId]);
            if (!$vehicleCheck->fetchColumn()) {
                $this->session->flash('error', 'Veículo inválido ou inativo para o cliente selecionado.');
                return redirect('/service-orders/' . (int) $id . '/edit');
            }
        }

        // A edição não pode pular etapas: só o status atual e os próximos
        // válidos. "Entregue" é concluído exclusivamente pela entrega.
        $currentStatus = (string) ($order['status'] ?? '');
        $newStatus = (string) ($body['status'] ?? '');
        if ($newStatus === '' || !isset(ServiceOrderStatus::STATUSES[$newStatus])) {
            $this->session->flash('error', 'Status inválido.');
            return redirect('/service-orders/' . (int) $id . '/edit');
        }
        $allowedStatuses = array_values(array_diff(
            array_merge([$currentStatus], ServiceOrderStatus::next($currentStatus)),
            ['delivered']
        ));
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $this->session->flash(
                'error',
                'Transição de status inválida: '
                    . ServiceOrderStatus::label($currentStatus) . ' → ' . ServiceOrderStatus::label($newStatus) . '.'
            );
            return redirect('/service-orders/' . (int) $id . '/edit');
        }

        $priority = (string) ($body['priority'] ?? 'normal');
        if (!isset(ServiceOrderStatus::PRIORITIES[$priority])) {
            $priority = 'normal';
        }
        $body['priority'] = $priority;

        // O responsável também entra na equipe da OS.
        $leadId = (int) ($body['lead_mechanic_id'] ?? 0);
        if ($leadId > 0) {
            $leadCheck = $this->pdo->prepare(
                "SELECT name FROM users WHERE id = ? AND is_active = 1 AND role IN ('lead_mechanic','mechanic')"
            );
            $leadCheck->execute([$leadId]);
            if (!$leadCheck->fetchColumn()) {
                $this->session->flash('error', 'Selecione um mecânico ativo como responsável.');
                return redirect('/service-orders/' . (int) $id . '/edit');
            }
            $this->pdo->prepare(
                'INSERT IGNORE INTO service_order_responsibles (order_id, user_id) VALUES (:o, :u)'
            )->execute([':o' => (int) $id, ':u' => $leadId]);
        }

        return parent::update($request->withParsedBody($body), $id);
    }

    /** OS não é excluída: o fluxo correto é cancelar (status). */
    public function destroy(ServerRequestInterface $request, $id)
    {
        $order = $this->findOrder((int) $id);
        if ($order && !OrderAccess::canAccess($this->pdo, $this->auth, $order)) {
            $this->session->flash('error', 'Você não tem acesso a esta ordem de serviço.');
            return redirect('/service-orders');
        }
        $this->session->flash('error', 'Não é possível excluir uma OS. Use o status "Cancelada".');
        return redirect('/service-orders/' . (int) $id);
    }

    // =========================================================
    // AÇÕES
    // =========================================================

    public function updateStatus(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles(['admin', 'secretary', 'lead_mechanic'])) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path() . '/' . (int) $id)) {
            return $response;
        }

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }

        if ($response = $this->guardOrderAccess($order)) {
            return $response;
        }

        $status = (string) ($request->getParsedBody()['status'] ?? '');
        if (!isset(ServiceOrderStatus::STATUSES[$status])) {
            $this->session->flash('error', 'Status inválido.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        if ((string) ($order['status'] ?? '') === $status) {
            $this->session->flash('success', 'O status já é "' . ServiceOrderStatus::label($status) . '".');
            return redirect($resource->path() . '/' . (int) $id);
        }

        // Respeita o fluxo definido (a UI já usa ServiceOrderStatus::next).
        // "Entregue" é concluído exclusivamente pelo registro de entrega.
        $allowed = array_values(array_diff(
            array_merge([(string) $order['status']], ServiceOrderStatus::next((string) $order['status'])),
            ['delivered']
        ));
        if (!in_array($status, $allowed, true)) {
            $this->session->flash('error', 'Transição de status inválida.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $completion = $status === 'delivered' ? date('Y-m-d') : null;
        $this->pdo->prepare('UPDATE service_orders SET status = :s, completion_date = :c WHERE id = :id')
            ->execute([':s' => $status, ':c' => $completion, ':id' => (int) $id]);

        $this->event((int) $id, 'status', 'Status alterado para "' . ServiceOrderStatus::label($status) . '".');

        $recipients = array_values(array_filter(
            $this->orderParticipants((int) $id, $order),
            fn (int $uid): bool => $uid !== (int) $this->auth->id()
        ));
        if ($recipients) {
            Notify::to(
                $this->pdo,
                $recipients,
                'os_status',
                'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) . ' mudou para "' . ServiceOrderStatus::label($status) . '".',
                '/service-orders/' . (int) $id,
                $this->auth->id()
            );
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

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }
        if ($response = $this->guardOrderAccess($order)) {
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

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }
        if ($response = $this->guardOrderAccess($order)) {
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

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }
        if ($response = $this->guardOrderAccess($order)) {
            return $response;
        }

        $userId = (int) ($request->getParsedBody()['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->session->flash('error', 'Selecione um mecânico.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $userStmt = $this->pdo->prepare(
            "SELECT name FROM users WHERE id = ? AND is_active = 1 AND role IN ('lead_mechanic','mechanic')"
        );
        $userStmt->execute([$userId]);
        $userName = (string) ($userStmt->fetchColumn() ?: '');
        if ($userName === '') {
            $this->session->flash('error', 'Selecione um mecânico ativo.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $exists = $this->pdo->prepare('SELECT 1 FROM service_order_responsibles WHERE order_id = ? AND user_id = ?');
        $exists->execute([(int) $id, $userId]);
        if ($exists->fetchColumn()) {
            $this->session->flash('success', 'Mecânico já está designado para esta OS.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $this->pdo->prepare('INSERT IGNORE INTO service_order_responsibles (order_id, user_id) VALUES (:o, :u)')
            ->execute([':o' => (int) $id, ':u' => $userId]);
        $this->event((int) $id, 'responsible', 'Mecânico designado: ' . $userName . '.');

        if ($userId !== (int) $this->auth->id()) {
            Notify::to(
                $this->pdo,
                [$userId],
                'os_responsible',
                'Você foi designado para a OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) . '.',
                '/service-orders/' . (int) $id,
                $this->auth->id()
            );
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

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }
        if ($response = $this->guardOrderAccess($order)) {
            return $response;
        }

        $userStmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ?');
        $userStmt->execute([(int) $userId]);
        $userName = (string) ($userStmt->fetchColumn() ?: 'Mecânico');

        $stmt = $this->pdo->prepare('DELETE FROM service_order_responsibles WHERE order_id = :o AND user_id = :u');
        $stmt->execute([':o' => (int) $id, ':u' => (int) $userId]);
        if ($stmt->rowCount() > 0) {
            $this->event((int) $id, 'responsible', 'Mecânico removido da equipe: ' . $userName . '.');
        }

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

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }
        if ($response = $this->guardOrderAccess($order)) {
            return $response;
        }

        $body = $request->getParsedBody();
        $desc = clean($body['description'] ?? '');
        if ($desc !== '') {
            $assignedTo = (int) ($body['assigned_to'] ?? 0) ?: null;
            // Mecânico cria tarefas para si; a gestão pode designar qualquer um.
            if (OrderAccess::isMechanicOnly($this->auth)) {
                $assignedTo = (int) $this->auth->id();
            }
            if ($assignedTo !== null) {
                $assignedCheck = $this->pdo->prepare(
                    "SELECT 1 FROM users WHERE id = ? AND is_active = 1 AND role IN ('lead_mechanic','mechanic')"
                );
                $assignedCheck->execute([$assignedTo]);
                if (!$assignedCheck->fetchColumn()) {
                    $assignedTo = null;
                }
            }
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

            if ($assignedTo && $assignedTo !== (int) $this->auth->id()) {
                Notify::to(
                    $this->pdo,
                    [$assignedTo],
                    'task',
                    'Você recebeu uma tarefa na OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) . ': ' . mb_substr($desc, 0, 120) . '.',
                    '/service-orders/' . (int) $id,
                    $this->auth->id()
                );
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

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }
        if ($response = $this->guardOrderAccess($order)) {
            return $response;
        }

        $taskId = (int) ($request->getParsedBody()['task_id'] ?? 0);
        $status = (string) ($request->getParsedBody()['task_status'] ?? '');
        if ($taskId > 0 && in_array($status, ['pending', 'in_progress', 'done'], true)) {
            $taskStmt = $this->pdo->prepare('SELECT assigned_to, status FROM service_order_tasks WHERE id = ? AND order_id = ?');
            $taskStmt->execute([$taskId, (int) $id]);
            $task = $taskStmt->fetch();
            if (!$task) {
                return redirect($resource->path() . '/' . (int) $id);
            }

            $assignedTo = (int) ($task['assigned_to'] ?? 0);
            if (OrderAccess::isMechanicOnly($this->auth)
                && $assignedTo > 0
                && $assignedTo !== (int) $this->auth->id()) {
                $this->session->flash('error', 'Você só pode atualizar tarefas atribuídas a você.');
                return redirect($resource->path() . '/' . (int) $id);
            }

            $previousStatus = (string) ($task['status'] ?? 'pending');

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

            if ($order && $previousStatus !== $status && $assignedTo > 0 && $assignedTo !== (int) $this->auth->id()) {
                Notify::to(
                    $this->pdo,
                    [$assignedTo],
                    'task',
                    'Tarefa da OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT)
                        . ' mudou para "' . ($labels[$status] ?? $status) . '".',
                    '/service-orders/' . (int) $id,
                    $this->auth->id()
                );
            }
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

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }
        if ($response = $this->guardOrderAccess($order)) {
            return $response;
        }

        $body = $request->getParsedBody();
        $taskId = (int) ($body['task_id'] ?? 0);
        if ($taskId <= 0) {
            return redirect($resource->path() . '/' . (int) $id);
        }

        $taskStmt = $this->pdo->prepare('SELECT assigned_to FROM service_order_tasks WHERE id = ? AND order_id = ?');
        $taskStmt->execute([$taskId, (int) $id]);
        $oldAssigned = (int) ($taskStmt->fetchColumn() ?: 0);

        $isMechanicOnly = OrderAccess::isMechanicOnly($this->auth);
        if ($isMechanicOnly && $oldAssigned > 0 && $oldAssigned !== (int) $this->auth->id()) {
            $this->session->flash('error', 'Você só pode editar tarefas atribuídas a você.');
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
        if ($isMechanicOnly) {
            // Mecânico não reatribui tarefas: mantém o responsável atual ou
            // assume a tarefa se ela estiver sem responsável.
            $assignedTo = $oldAssigned ?: (int) $this->auth->id();
        } elseif ($assignedTo !== null) {
            $assignedCheck = $this->pdo->prepare(
                "SELECT 1 FROM users WHERE id = ? AND is_active = 1 AND role IN ('lead_mechanic','mechanic')"
            );
            $assignedCheck->execute([$assignedTo]);
            if (!$assignedCheck->fetchColumn()) {
                $assignedTo = null;
            }
        }
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

        if ($assignedTo && $assignedTo !== $oldAssigned && $assignedTo !== (int) $this->auth->id()) {
            Notify::to(
                $this->pdo,
                [$assignedTo],
                'task',
                'Você recebeu uma tarefa na OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) . ': ' . mb_substr($desc, 0, 120) . '.',
                '/service-orders/' . (int) $id,
                $this->auth->id()
            );
        }

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

        $order = $this->findOrder((int) $id);
        if (!$order) {
            $this->session->flash('error', 'Ordem de serviço não encontrada.');
            return redirect($resource->path());
        }
        if ($response = $this->guardOrderAccess($order)) {
            return $response;
        }

        $currentStatus = (string) ($order['status'] ?? '');
        if ($currentStatus !== 'ready_for_delivery') {
            $this->session->flash('error', 'A entrega só pode ser registrada quando a OS estiver "Pronta para entrega".');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $deliveryCheck = $this->pdo->prepare('SELECT 1 FROM service_order_deliveries WHERE order_id = ?');
        $deliveryCheck->execute([(int) $id]);
        if ($deliveryCheck->fetchColumn()) {
            $this->session->flash('error', 'Esta OS já possui entrega/retirada registrada.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $body = $request->getParsedBody();
        $exitKm = (int) ($body['exit_km'] ?? 0) ?: null;
        $currentKm = $order['vehicle_km'] !== null ? (int) $order['vehicle_km'] : null;
        if ($exitKm !== null && $currentKm !== null && $currentKm > 0 && $exitKm < $currentKm) {
            $this->session->flash(
                'error',
                'O KM de saída não pode ser menor que o KM atual do veículo ('
                    . number_format($currentKm, 0, ',', '.') . ').'
            );
            return redirect($resource->path() . '/' . (int) $id);
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'INSERT INTO service_order_deliveries (order_id, modality, responsible, destination, exit_km)
                 VALUES (:o, :m, :r, :d, :km)'
            )->execute([
                ':o' => (int) $id,
                ':m' => ($body['modality'] ?? 'pickup') === 'delivery' ? 'delivery' : 'pickup',
                ':r' => clean($body['responsible'] ?? ''),
                ':d' => clean($body['destination'] ?? ''),
                ':km' => $exitKm,
            ]);

            // O KM de saída é o novo KM do veículo (nunca retrocede).
            if ($exitKm !== null && !empty($order['vehicle_id'])) {
                $this->pdo->prepare(
                    'UPDATE vehicles SET current_km = :km
                      WHERE id = :id AND (current_km IS NULL OR current_km <= :km2)'
                )->execute([
                    ':km' => $exitKm,
                    ':id' => (int) $order['vehicle_id'],
                    ':km2' => $exitKm,
                ]);
            }

            // A entrega/retirada conclui o atendimento.
            $this->pdo->prepare('UPDATE service_orders SET status = :s, completion_date = :c WHERE id = :id')
                ->execute([':s' => 'delivered', ':c' => date('Y-m-d'), ':id' => (int) $id]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('OS delivery: ' . $e->getMessage());
            $this->session->flash('error', 'Não foi possível registrar a entrega.');
            return redirect($resource->path() . '/' . (int) $id);
        }

        $this->event((int) $id, 'delivery', 'Entrega/retirada registrada.');
        $this->event((int) $id, 'status', 'Status alterado para "Entregue".');

        $recipients = array_values(array_filter(
            $this->orderParticipants((int) $id, $order),
            fn (int $uid): bool => $uid !== (int) $this->auth->id()
        ));
        if ($recipients) {
            Notify::to(
                $this->pdo,
                $recipients,
                'os_status',
                'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT) . ' foi entregue.',
                '/service-orders/' . (int) $id,
                $this->auth->id()
            );
        }

        $this->session->flash('success', 'Entrega registrada.');
        return redirect($resource->path() . '/' . (int) $id);
    }

    /**
     * IDs dos participantes da OS (criador, responsável e mecânicos designados).
     *
     * @param array<string, mixed> $order
     * @return int[]
     */
    private function orderParticipants(int $orderId, array $order): array
    {
        $ids = [
            (int) ($order['created_by'] ?? 0),
            (int) ($order['lead_mechanic_id'] ?? 0),
        ];

        $stmt = $this->pdo->prepare('SELECT user_id FROM service_order_responsibles WHERE order_id = ?');
        $stmt->execute([$orderId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $userId) {
            $ids[] = (int) $userId;
        }

        return array_values(array_unique(array_filter($ids, static fn (int $uid): bool => $uid > 0)));
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
