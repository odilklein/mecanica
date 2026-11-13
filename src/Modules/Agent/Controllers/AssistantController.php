<?php
// =============================================
// ASSISTENTE — página do chat, streaming (SSE) e
// decisão das propostas pendentes.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Modules\Agent\Services\AgentRunner;
use Modules\Agent\Services\AgentStore;
use Modules\Agent\Services\AttachmentService;
use Modules\Agent\Services\LlmClient;
use Modules\Agent\Services\ModelCatalog;
use Modules\Agent\Services\OfferToken;
use Modules\Agent\Services\PrefillService;
use Modules\Agent\Services\ProposalService;
use Modules\Mechanic\Support\OrderAccess;
use Modules\Agent\Services\ToolRegistry;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class AssistantController extends Controller
{
    private PDO $pdo;
    private AgentStore $store;
    private LlmClient $llm;
    private ProposalService $proposals;
    private AttachmentService $attachments;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
        $this->store = new AgentStore($pdo);
        $this->llm = new LlmClient();
        $this->proposals = new ProposalService($pdo, $auth);
        $this->attachments = new AttachmentService($pdo);
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
        $requested = (int) ($query['s'] ?? 0);

        $session = $requested > 0 ? $this->store->findSession($requested, $userId) : null;
        $session = $session ?: $this->store->activeSession($userId);

        $messages = $this->store->messages((int) $session['id'], 40);
        // No chat, cada usuário vê apenas as próprias pendências.
        $proposals = $this->proposals->pending($userId, false, 20);
        $usage = $this->store->usageToday($userId);

        // Reidrata os cartões de proposta do histórico com o estado atual.
        $proposalIds = [];
        foreach ($proposals as $proposal) {
            $proposalIds[] = (int) $proposal['id'];
        }
        foreach ($messages as $message) {
            $meta = is_array($message['meta'] ?? null) ? $message['meta'] : [];
            foreach ((array) ($meta['renders'] ?? []) as $render) {
                if (($render['type'] ?? '') === 'proposal' && !empty($render['proposal']['id'])) {
                    $proposalIds[] = (int) $render['proposal']['id'];
                }
            }
        }
        $proposalIndex = $this->proposals->findMany($proposalIds);

        return $this->render('agent::index', [
            'title' => 'Assistente',
            'hideChrome' => true,
            'pageStyles' => ['/css/assistant.css'],
            'pageScripts' => ['/js/assistant-audio.js', '/js/assistant.js'],
            'session' => $session,
            'sessions' => $this->store->sessions($userId, 15),
            'messages' => $messages,
            'proposals' => $proposals,
            'proposalIndex' => $proposalIndex,
            'usage' => $usage,
            'configured' => $this->llm->enabled(),
            'mock' => $this->llm->mock(),
            'model' => $this->llm->model(),
            'models' => ModelCatalog::forFront(),
            'canReasoning' => $this->auth->hasRole(['admin']),
            'audioEnabled' => trim((string) env('GROQ_API_KEY', '')) !== '',
            'attachmentLimits' => [
                'max_bytes' => AttachmentService::MAX_BYTES,
                'max_image_bytes' => AttachmentService::MAX_IMAGE_BYTES,
                'max_images' => AttachmentService::MAX_IMAGES_PER_TURN,
            ],
            'limits' => [
                'hour' => max(1, (int) env('AGENT_MAX_TURNS_HOUR', '30')),
                'day' => max(1, (int) env('AGENT_MAX_TURNS_DAY', '200')),
                'turns_today' => $usage['turns'],
                'tokens_today' => $usage['tokens_in'] + $usage['tokens_out'],
            ],
            'isAdmin' => $isAdmin,
            'tools' => array_map(
                static fn (array $tool): string => (string) $tool['function']['name'],
                (new ToolRegistry($this->pdo, $this->auth, $this->proposals))->defs()
            ),
        ]);
    }

    // =========================================================
    // STREAMING (SSE)
    // =========================================================

    public function stream(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }

        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            return json_response(['error' => 'Sessão expirada. Recarregue a página.'], 403);
        }

        $userId = (int) $this->auth->id();
        $text = trim((string) ($body['message'] ?? ''));
        if ($text === '') {
            return json_response(['error' => 'Mensagem vazia.'], 422);
        }
        if (mb_strlen($text) > 4000) {
            return json_response(['error' => 'Mensagem muito longa (limite de 4000 caracteres).'], 422);
        }

        $sessionId = (int) ($body['session_id'] ?? 0);
        $session = $this->store->findSession($sessionId, $userId);
        if (!$session) {
            return json_response(['error' => 'Sessão inválida.'], 404);
        }

        $limitError = $this->checkRateLimit($userId);
        if ($limitError !== null) {
            return json_response(['error' => $limitError], 429);
        }

        // Libera a sessão PHP para o stream não bloquear outras abas do usuário.
        session_write_close();

        $this->sseStart();

        $emit = function (string $event, array $data): bool {
            if (connection_aborted()) {
                return false;
            }
            $this->sseSend($event, $data);

            return true;
        };

        try {
            $attachmentIds = array_map('intval', array_filter(
                array_map('trim', explode(',', (string) ($body['attachments'] ?? ''))),
                static fn (string $id): bool => $id !== ''
            ));

            $runner = new AgentRunner(
                $this->auth,
                $this->store,
                $this->llm,
                new ToolRegistry($this->pdo, $this->auth, $this->proposals),
                $this->attachments
            );
            $runner->run(
                $sessionId,
                $userId,
                $text,
                $attachmentIds,
                [
                    'model' => (string) ($body['model'] ?? ''),
                    'effort' => (string) ($body['effort'] ?? ''),
                ],
                $emit
            );
        } catch (Throwable $e) {
            error_log('[assistant] ' . $e->getMessage());
            $emit('error', ['message' => $e->getMessage()]);
        }

        exit;
    }

    // =========================================================
    // SESSÕES
    // =========================================================

    public function newSession(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'csrf'], 403);
        }

        $userId = (int) $this->auth->id();
        $session = $this->store->createSession($userId);

        return json_response(['ok' => true, 'session_id' => (int) $session['id']]);
    }

    public function history(ServerRequestInterface $request, string $id)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }

        $userId = (int) $this->auth->id();
        $session = $this->store->findSession((int) $id, $userId);
        if (!$session) {
            return json_response(['error' => 'not_found'], 404);
        }

        return json_response([
            'ok' => true,
            'session' => $session,
            'messages' => $this->store->messages((int) $session['id'], 40),
        ]);
    }

    public function archiveSession(ServerRequestInterface $request, string $id)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'csrf'], 403);
        }

        $this->store->archive((int) $id, (int) $this->auth->id());

        return json_response(['ok' => true]);
    }

    // =========================================================
    // ANEXOS
    // =========================================================

    public function uploadAttachment(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'Sessão expirada. Recarregue a página.'], 403);
        }

        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file) {
            return json_response(['error' => 'Nenhum arquivo enviado.'], 422);
        }

        $sessionId = (int) ($request->getParsedBody()['session_id'] ?? 0);
        $userId = (int) $this->auth->id();
        $session = $this->store->findSession($sessionId, $userId);

        try {
            $attachment = $this->attachments->store(
                $userId,
                $session ? (int) $session['id'] : 0,
                $file
            );
        } catch (RuntimeException $e) {
            return json_response(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('[assistant:upload] ' . $e->getMessage());

            return json_response(['error' => 'Não foi possível processar o arquivo.'], 500);
        }

        return json_response([
            'ok' => true,
            'attachment' => $this->attachments->forUi([$attachment])[0] ?? null,
        ]);
    }

    public function downloadAttachment(ServerRequestInterface $request, string $id)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }

        $attachment = $this->attachments->find(
            (int) $id,
            (int) $this->auth->id(),
            $this->auth->hasRole(['admin'])
        );
        if (!$attachment) {
            return json_response(['error' => 'not_found'], 404);
        }

        $path = $this->attachments->absolutePath($attachment);
        if ($path === null) {
            return json_response(['error' => 'not_found'], 404);
        }

        $inline = in_array((string) $attachment['kind'], ['image', 'pdf'], true);
        $name = str_replace(['"', "\r", "\n"], '', (string) $attachment['filename']);

        header('Content-Type: ' . (string) $attachment['mime']);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function deleteAttachment(ServerRequestInterface $request, string $id)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'Sessão expirada. Recarregue a página.'], 403);
        }

        $this->attachments->delete(
            (int) $id,
            (int) $this->auth->id(),
            $this->auth->hasRole(['admin'])
        );

        return json_response(['ok' => true]);
    }

    // =========================================================
    // PREENCHIMENTO DE FORMULÁRIO COM IA
    // =========================================================

    public function prefill(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'Sessão expirada. Recarregue a página.'], 403);
        }

        if (!$this->llm->enabled()) {
            return json_response(['error' => 'O preenchimento com IA não está configurado.'], 503);
        }

        if ($limit = $this->prefillLimit()) {
            return json_response(['error' => $limit], 429);
        }

        $body = $request->getParsedBody();
        $current = json_decode((string) ($body['current'] ?? '{}'), true);
        if (!is_array($current)) {
            $current = [];
        }

        try {
            $service = new PrefillService($this->pdo, $this->llm);
            $result = $service->extract(
                (string) ($body['form'] ?? ''),
                (string) ($body['text'] ?? ''),
                $current
            );

            return json_response(['ok' => true, 'prefill' => $result]);
        } catch (RuntimeException $e) {
            return json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('[assistant:prefill] ' . $e->getMessage());

            return json_response(['ok' => false, 'error' => 'Não foi possível interpretar o texto agora.'], 500);
        }
    }

    /**
     * Limite simples por sessão (janela de 1 hora).
     */
    private function prefillLimit(): ?string
    {
        return $this->sessionLimit('agent_prefill', 30, 'Muitas extrações em sequência. Aguarde alguns minutos.');
    }

    private function sessionLimit(string $key, int $max, string $message): ?string
    {
        $now = time();
        $bucket = (array) ($this->session->get($key) ?? []);
        $bucket = array_values(array_filter($bucket, static fn ($time): bool => (int) $time > $now - 3600));

        if (count($bucket) >= $max) {
            return $message;
        }

        $bucket[] = $now;
        $this->session->set($key, $bucket);

        return null;
    }

    // =========================================================
    // TEXTO ASSISTIDO (queixa / laudo / mensagem ao cliente)
    // =========================================================

    public function enhance(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'Sessão expirada. Recarregue a página.'], 403);
        }
        if (!$this->llm->enabled()) {
            return json_response(['error' => 'A IA não está configurada.'], 503);
        }
        if ($limit = $this->sessionLimit('agent_enhance', 60, 'Muitos textos em sequência. Aguarde alguns minutos.')) {
            return json_response(['error' => $limit], 429);
        }

        $body = $request->getParsedBody();
        $text = trim((string) ($body['text'] ?? ''));
        if (mb_strlen($text) < 3) {
            return json_response(['error' => 'Fale ou escreva o texto primeiro.'], 422);
        }
        $text = mb_substr($text, 0, 4000);

        $kind = (string) ($body['kind'] ?? 'complaint');
        if (!in_array($kind, ['complaint', 'diagnosis'], true)) {
            $kind = 'complaint';
        }

        $context = mb_substr(trim((string) ($body['context'] ?? '')), 0, 500);

        $label = $kind === 'diagnosis' ? 'diagnóstico técnico' : 'queixa do cliente';

        $prompt = "Reescreva o texto abaixo como {$label} de uma ordem de serviço.\n"
            . "REGRAS:\n"
            . "- Português do Brasil, claro, objetivo e um pouco mais detalhado que o original.\n"
            . "- Mantenha todos os fatos. Não invente sintomas, peças, valores ou prazos.\n"
            . "- Corrija gramática e organize as ideias em ordem lógica.\n"
            . "- No máximo 3 frases. Sem listas, sem markdown, sem emojis.\n"
            . "- Se o texto já estiver bom, apenas refine.\n"
            . ($context !== '' ? "Contexto do veículo: {$context}\n" : '')
            . "Texto bruto:\n\"\"\"\n{$text}\n\"\"\"";

        try {
            $improved = $this->llm->text(
                [
                    ['role' => 'system', 'content' => 'Você é um assistente experiente de oficina mecânica e escreve em português do Brasil.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                ['max_tokens' => 600, 'temperature' => 0.4]
            );
        } catch (RuntimeException $e) {
            return json_response(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('[assistant:enhance] ' . $e->getMessage());

            return json_response(['error' => 'Não foi possível melhorar o texto agora.'], 500);
        }

        return json_response(['ok' => true, 'text' => trim($improved)]);
    }

    public function customerMessage(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'Sessão expirada. Recarregue a página.'], 403);
        }
        if (!$this->llm->enabled()) {
            return json_response(['error' => 'A IA não está configurada.'], 503);
        }
        if ($limit = $this->sessionLimit('agent_message', 20, 'Muitas mensagens em sequência. Aguarde alguns minutos.')) {
            return json_response(['error' => $limit], 429);
        }

        $body = $request->getParsedBody();
        $orderId = (int) ($body['order_id'] ?? 0);
        if ($orderId <= 0) {
            return json_response(['error' => 'OS inválida.'], 422);
        }

        if (!OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            return json_response(['error' => 'Você não tem acesso a esta OS.'], 403);
        }

        $stmt = $this->pdo->prepare(
            'SELECT so.number, so.initial_complaint, so.diagnosis, so.status,
                    c.name AS client_name, c.phone AS client_phone,
                    v.plate, v.brand, v.model, v.year
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
              WHERE so.id = :id'
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return json_response(['error' => 'OS não encontrada.'], 404);
        }

        $budgetStmt = $this->pdo->prepare(
            'SELECT id, number, status, total FROM budgets
              WHERE service_order_id = :id
              ORDER BY id DESC LIMIT 1'
        );
        $budgetStmt->execute([':id' => $orderId]);
        $budget = $budgetStmt->fetch() ?: null;

        $items = [];
        if ($budget) {
            $itemsStmt = $this->pdo->prepare(
                'SELECT type, description, quantity FROM budget_items
                  WHERE budget_id = :id ORDER BY type DESC, id'
            );
            $itemsStmt->execute([':id' => (int) $budget['id']]);
            foreach ($itemsStmt->fetchAll() as $item) {
                $items[] = [
                    'type' => (string) $item['type'],
                    'description' => (string) $item['description'],
                ];
            }
        }

        $shop = (string) (setting('company_name', 'Mecânica') ?: 'Mecânica');
        $vehicle = trim((string) $order['plate'] . ' ' . (string) $order['brand'] . ' ' . (string) $order['model']);
        $firstName = trim(explode(' ', trim((string) ($order['client_name'] ?: 'cliente')))[0]);

        $facts = 'Cliente: ' . ($order['client_name'] ?: 'cliente') . "\n"
            . "Veículo: {$vehicle}\n"
            . 'Queixa: ' . ($order['initial_complaint'] ?: '—') . "\n"
            . ($order['diagnosis'] ? 'Diagnóstico: ' . $order['diagnosis'] . "\n" : '')
            . 'Quantidade de itens no orçamento: ' . count($items) . "\n"
            . ($budget ? 'Total: R$ ' . number_format((float) $budget['total'], 2, ',', '.')
                . ' (status ' . (string) $budget['status'] . ")\n" : '');

        $prompt = "Escreva três partes de uma mensagem de WhatsApp para o cliente da oficina: "
            . "saudação, contexto e encerramento.\n"
            . "Responda SOMENTE JSON: {\"greeting\": \"...\", \"context\": \"...\", \"closing\": \"...\"}\n"
            . "REGRAS:\n"
            . "- Português do Brasil, cordial e direto.\n"
            . "- greeting: cumprimente pelo primeiro nome.\n"
            . "- context: 1 ou 2 frases sobre o veículo e o atendimento. NÃO cite peças, serviços, valores, prazos ou garantias.\n"
            . "- closing: peça aprovação ou diga o próximo passo, em uma frase.\n"
            . "- Não invente nada. Sem listas, sem markdown e sem emojis.\n\n"
            . "DADOS:\n{$facts}";

        $greeting = '';
        $context = '';
        $closing = '';

        try {
            $parts = $this->llm->json(
                [
                    ['role' => 'system', 'content' => 'Você escreve mensagens claras e humanas para clientes de oficina mecânica.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                ['max_tokens' => 400, 'temperature' => 0.5]
            );
            $greeting = trim((string) ($parts['greeting'] ?? ''));
            $context = trim((string) ($parts['context'] ?? ''));
            $closing = trim((string) ($parts['closing'] ?? ''));
        } catch (Throwable $e) {
            error_log('[assistant:customer-message] ' . $e->getMessage());
        }

        // Fallback determinístico: a mensagem nunca fica vazia.
        if ($greeting === '') {
            $greeting = 'Olá ' . $firstName . ', tudo bem?';
        }
        if ($closing === '') {
            $closing = $budget
                ? 'Qualquer dúvida sobre o orçamento, é só chamar.'
                : 'Assim que tivermos novidades, avisamos por aqui.';
        }

        $blocks = [$greeting];
        if ($context === '') {
            $context = 'Sobre o seu ' . ($vehicle !== '' ? $vehicle : 'veículo') . '.';
        }
        $blocks[] = $context;

        if ($items) {
            $list = [];
            foreach ($items as $item) {
                $list[] = '• ' . $item['description'];
            }
            $blocks[] = "Itens do orçamento:\n" . implode("\n", $list);
        }

        if ($budget) {
            $blocks[] = 'Total: R$ ' . number_format((float) $budget['total'], 2, ',', '.');
        }

        $blocks[] = $closing;
        $blocks[] = $shop;

        $message = implode("\n\n", array_filter($blocks, static fn (string $block): bool => trim($block) !== ''));
        $phone = preg_replace('/\D/', '', (string) ($order['client_phone'] ?? '')) ?? '';

        return json_response([
            'ok' => true,
            'message' => trim($message),
            'phone' => $phone,
            'client_name' => (string) ($order['client_name'] ?? ''),
        ]);
    }

    // =========================================================
    // SELEÇÃO DE PEÇA (a partir do cartão de ofertas)
    // =========================================================

    public function selectPart(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'Sessão expirada. Recarregue a página.'], 403);
        }

        $body = $request->getParsedBody();
        $offer = OfferToken::verify((string) ($body['token'] ?? ''));
        if (!$offer) {
            return json_response(['error' => 'Esta oferta expirou. Peça a busca novamente.'], 422);
        }

        $orderId = (int) ($offer['os'] ?? 0);
        $item = (array) ($offer['item'] ?? []);
        if ($orderId <= 0 || trim((string) ($item['description'] ?? '')) === '') {
            return json_response(['error' => 'Oferta inválida.'], 422);
        }

        $userId = (int) $this->auth->id();
        if (!OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            return json_response(['error' => 'Você não tem acesso a esta OS.'], 403);
        }

        $stmt = $this->pdo->prepare(
            'SELECT so.number, c.name AS client_name, v.plate
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
              WHERE so.id = :id'
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return json_response(['error' => 'OS não encontrada.'], 404);
        }

        $quantity = max(1, (int) ($offer['q'] ?? 1));
        $item['quantity'] = $quantity;

        $session = $this->store->findSession((int) ($body['session_id'] ?? 0), $userId);
        $sessionId = $session
            ? (int) $session['id']
            : (int) $this->store->activeSession($userId)['id'];

        $label = 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT);

        $proposal = $this->proposals->create(
            $sessionId,
            $userId,
            'propor_pedido_pecas',
            [
                'service_order_id' => $orderId,
                'items' => [$item],
                'notes' => '',
            ],
            [
                'kind' => 'part_order_draft',
                'title' => 'Solicitar peças',
                'subtitle' => $label . ' — ' . ($order['client_name'] ?: 'sem cliente'),
                'risk' => 'low',
                'approve_label' => 'Criar pedido',
                'fields' => [
                    ['label' => 'OS', 'value' => $label],
                    ['label' => 'Cliente', 'value' => (string) ($order['client_name'] ?: '—')],
                    ['label' => 'Veículo', 'value' => (string) ($order['plate'] ?: '—')],
                    ['label' => 'Origem', 'value' => (string) ($item['supplier'] ?? '—')],
                ],
                'items' => [[
                    'description' => (string) $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $item['unit_price'] ?? null,
                ]],
                'warnings' => [],
                'tasks' => [],
            ],
            'partorder:os:' . $orderId,
            true
        );

        return json_response([
            'ok' => true,
            'message' => 'Peça adicionada à proposta de pedido.',
            'proposal' => ProposalService::card($proposal),
        ]);
    }

    // =========================================================
    // PROPOSTAS
    // =========================================================

    public function proposals(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }

        $userId = (int) $this->auth->id();

        return json_response([
            'ok' => true,
            'items' => $this->proposals->pending($userId, false, 20),
        ]);
    }

    public function applyProposal(ServerRequestInterface $request, string $id)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'Sessão expirada. Recarregue a página.'], 403);
        }

        try {
            $result = $this->proposals->apply(
                (int) $id,
                (int) $this->auth->id(),
                $this->auth->hasRole(['admin'])
            );

            return json_response($result);
        } catch (RuntimeException $e) {
            return json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('[assistant:apply] ' . $e->getMessage());

            return json_response(['ok' => false, 'error' => 'Não foi possível aplicar a proposta.'], 500);
        }
    }

    public function rejectProposal(ServerRequestInterface $request, string $id)
    {
        if ($response = $this->guardAuthJson()) {
            return $response;
        }
        if (!$this->csrfOk($request)) {
            return json_response(['error' => 'Sessão expirada. Recarregue a página.'], 403);
        }

        try {
            return json_response($this->proposals->reject(
                (int) $id,
                (int) $this->auth->id(),
                $this->auth->hasRole(['admin'])
            ));
        } catch (RuntimeException $e) {
            return json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // =========================================================
    // INTERNOS
    // =========================================================

    private function checkRateLimit(int $userId): ?string
    {
        $perHour = max(1, (int) env('AGENT_MAX_TURNS_HOUR', '30'));
        $perDay = max(1, (int) env('AGENT_MAX_TURNS_DAY', '200'));

        if ($this->store->turnsLastHour($userId) >= $perHour) {
            return 'Limite de ' . $perHour . ' mensagens por hora atingido. Tente novamente mais tarde.';
        }

        $usage = $this->store->usageToday($userId);
        if ($usage['turns'] >= $perDay) {
            return 'Limite diário de ' . $perDay . ' mensagens atingido.';
        }

        return null;
    }

    private function csrfOk(ServerRequestInterface $request): bool
    {
        $body = $request->getParsedBody();

        return $this->session->verifyCsrf($body['csrf_token'] ?? null);
    }

    private function guardAuthJson()
    {
        return $this->auth->check()
            ? null
            : json_response(['error' => 'unauthorized'], 401);
    }

    private function sseStart(): void
    {
        @set_time_limit(0);
        @ini_set('zlib.output_compression', '0');

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        // Descarrega qualquer buffer do PHP/Apache para o cliente receber
        // os deltas em tempo real.
        $guard = 0;
        while (ob_get_level() > 0 && $guard < 10) {
            if (!@ob_end_flush()) {
                break;
            }
            $guard++;
        }
        @ob_implicit_flush(true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sseSend(string $event, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        echo 'event: ' . $event . "\n";
        echo 'data: ' . ($json !== false ? $json : '{}') . "\n\n";
        @flush();
    }
}
