<?php
// =============================================
// TOOL REGISTRY — catálogo único de ferramentas do agente.
//
// Regras:
//  - a autorização é aplicada aqui (papel + escopo por objeto),
//    nunca a partir do que o modelo "disse";
//  - tools de leitura devolvem dados + widgets;
//  - tools de escrita NÃO escrevem: criam propostas.
// =============================================

declare(strict_types=1);

namespace Modules\Agent\Services;

use Kernel\Auth\Auth;
use Modules\Mechanic\Support\OrderAccess;
use Modules\Mechanic\Support\PartOrderStatus;
use Modules\Mechanic\Support\ServiceOrderStatus;
use PDO;
use RuntimeException;

class ToolRegistry
{
    private PDO $pdo;
    private Auth $auth;
    private ProposalService $proposals;
    private VehicleSpecService $specs;
    private PartFinder $finder;

    public function __construct(PDO $pdo, Auth $auth, ProposalService $proposals)
    {
        $this->pdo = $pdo;
        $this->auth = $auth;
        $this->proposals = $proposals;
        $this->specs = new VehicleSpecService($pdo);
        $this->finder = new PartFinder($pdo, $this->specs);
    }

    /** Papéis autorizados por ferramenta (null = todos os autenticados). */
    private const ROLES = [
        'buscar_os' => null,
        'detalhar_os' => null,
        'buscar_pedidos_pecas' => null,
        'buscar_cliente' => ['admin', 'secretary', 'lead_mechanic'],
        'buscar_veiculo' => ['admin', 'secretary', 'lead_mechanic'],
        'resumo_operacional' => ['admin', 'secretary', 'lead_mechanic'],
        'buscar_peca' => ['admin', 'secretary', 'lead_mechanic'],
        'buscar_pecas_veiculo' => null,
        'ficha_veiculo' => null,
        'salvar_ficha_veiculo' => ['admin'],
        'propor_abrir_os' => ['admin', 'secretary', 'lead_mechanic'],
        'propor_cadastro_cliente' => ['admin', 'secretary', 'lead_mechanic'],
        'propor_pedido_pecas' => null,
        'propor_status_pedido_peca' => ['admin', 'secretary', 'lead_mechanic'],
        'propor_status_os' => ['admin', 'secretary', 'lead_mechanic'],
    ];

    /**
     * Definições no formato OpenAI (JSON Schema), filtradas por papel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function defs(): array
    {
        $defs = [
            'buscar_os' => [
                'description' => 'Busca ordens de serviço por número, placa, modelo ou nome do cliente. Use antes de detalhar.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'termo' => ['type' => 'string', 'description' => 'Número da OS, placa, modelo ou nome do cliente.'],
                        'status' => ['type' => 'string', 'enum' => array_keys(ServiceOrderStatus::STATUSES)],
                        'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'detalhar_os' => [
                'description' => 'Retorna o detalhe completo de uma OS: cliente, veículo, tarefas, peças e orçamento.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'os' => ['type' => 'string', 'description' => 'Número da OS (ex.: 12) ou o id interno.'],
                    ],
                    'required' => ['os'],
                ],
            ],
            'buscar_pedidos_pecas' => [
                'description' => 'Lista pedidos de peças por cliente, OS, placa, item ou id do pedido, com itens e status. Use para responder se existe pedido de peça para um cliente ou quais pedidos estão pendentes.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'termo' => ['type' => 'string', 'description' => 'Nome do cliente, número da OS, placa, descrição do item ou id do pedido.'],
                        'status' => ['type' => 'string', 'enum' => array_keys(PartOrderStatus::STATUSES)],
                        'pendentes' => ['type' => 'boolean', 'description' => 'Quando verdadeiro, mostra apenas pedidos ainda não recebidos nem cancelados.'],
                        'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'buscar_cliente' => [
                'description' => 'Busca clientes por nome, CPF, telefone, e-mail ou cidade.',                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'termo' => ['type' => 'string'],
                        'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['termo'],
                ],
            ],
            'buscar_veiculo' => [
                'description' => 'Busca veículos por placa, marca, modelo ou nome do cliente.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'termo' => ['type' => 'string'],
                        'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['termo'],
                ],
            ],
            'resumo_operacional' => [
                'description' => 'Resumo do dia: OS por status, entregas pendentes e pedidos de peças em aberto.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
            ],
            'buscar_peca' => [
                'description' => 'Busca peças no catálogo local por nome, marca, código ou categoria.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'termo' => ['type' => 'string'],
                        'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['termo'],
                ],
            ],
            'buscar_pecas_veiculo' => [
                'description' => 'Busca peças compatíveis com o veículo (óleo, filtros, freio, suspensão etc.) no catálogo local e no Canal da Peça, devolvendo opções com preço, estoque, link e compatibilidade. Use quando o mecânico pedir para solicitar uma peça. O mecânico escolhe no cartão.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'descricao' => ['type' => 'string', 'description' => 'Descrição do mecânico, mesmo que genérica (ex.: "amortecedor dianteiro de alta performance").'],
                        'os' => ['type' => 'string', 'description' => 'Número ou id da OS. Necessário para depois solicitar a peça.'],
                        'placa' => ['type' => 'string', 'description' => 'Alternativa à OS quando o veículo é identificado pela placa.'],
                        'categoria' => [
                            'type' => 'string',
                            'enum' => ['oleo_motor', 'oleo_cambio', 'filtro_oleo', 'filtro_ar', 'filtro_cabine', 'filtro_combustivel', 'freio', 'suspensao', 'correia', 'vela', 'bateria', 'arrefecimento', 'outros'],
                        ],
                        'quantidade' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['descricao'],
                ],
            ],
            'ficha_veiculo' => [
                'description' => 'Consulta a ficha técnica cadastrada do veículo (viscosidade e capacidade do óleo, códigos de filtro, pneus) com fonte e confiança. Use antes de indicar óleo ou filtrar peças.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'os' => ['type' => 'string'],
                        'placa' => ['type' => 'string'],
                        'marca' => ['type' => 'string'],
                        'modelo' => ['type' => 'string'],
                        'ano' => ['type' => 'integer'],
                    ],
                    'required' => [],
                ],
            ],
            'salvar_ficha_veiculo' => [
                'description' => 'Cadastra ou atualiza a ficha técnica de um veículo (somente admin). Sempre informe a fonte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'marca' => ['type' => 'string'],
                        'modelo' => ['type' => 'string'],
                        'ano_de' => ['type' => 'integer'],
                        'ano_ate' => ['type' => 'integer'],
                        'motor' => ['type' => 'string', 'description' => 'Ex.: 1.2'],
                        'oleo_viscosidade' => ['type' => 'string', 'description' => 'Ex.: 0W20'],
                        'oleo_capacidade_l' => ['type' => 'number'],
                        'oleo_especificacao' => ['type' => 'string', 'description' => 'Ex.: API SP / Dexos1 Gen3'],
                        'filtro_oleo' => ['type' => 'string'],
                        'filtro_ar' => ['type' => 'string'],
                        'filtro_cabine' => ['type' => 'string'],
                        'filtro_combustivel' => ['type' => 'string'],
                        'pneus' => ['type' => 'string'],
                        'fonte' => ['type' => 'string', 'description' => 'URL ou nome do documento consultado.'],
                        'confianca' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                        'observacoes' => ['type' => 'string'],
                    ],
                    'required' => ['marca', 'modelo', 'fonte'],
                ],
            ],
            'propor_cadastro_cliente' => [
                'description' => 'Cria uma PROPOSTA de cadastro de cliente. Use quando o usuário disser que vai criar/cadastrar um cliente; não busque antes. Monte com os dados que ele já passou e peça apenas o que faltar (nome é obrigatório; telefone ou e-mail é recomendado).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'nome' => ['type' => 'string', 'description' => 'Nome completo do cliente.'],
                        'cpf' => ['type' => 'string'],
                        'telefone' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'cidade' => ['type' => 'string'],
                        'estado' => ['type' => 'string', 'description' => 'UF com 2 letras.'],
                        'observacoes' => ['type' => 'string'],
                    ],
                    'required' => ['nome'],
                ],
            ],
            'propor_abrir_os' => [
                'description' => 'Cria uma PROPOSTA de abertura de OS. Nada é gravado até o usuário aprovar. Informe client_id e vehicle_id sempre que possível.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'client_id' => ['type' => 'integer'],
                        'vehicle_id' => ['type' => 'integer'],
                        'plate' => ['type' => 'string', 'description' => 'Alternativa ao vehicle_id.'],
                        'client_name' => ['type' => 'string', 'description' => 'Alternativa ao client_id.'],
                        'initial_complaint' => ['type' => 'string', 'description' => 'Queixa relatada pelo cliente.'],
                        'priority' => ['type' => 'string', 'enum' => array_keys(ServiceOrderStatus::PRIORITIES)],
                        'tasks' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'parts' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'description' => ['type' => 'string'],
                                    'quantity' => ['type' => 'integer', 'minimum' => 1],
                                ],
                                'required' => ['description'],
                            ],
                        ],
                        'internal_notes' => ['type' => 'string'],
                    ],
                    'required' => ['initial_complaint'],
                ],
            ],
            'propor_pedido_pecas' => [
                'description' => 'Cria uma PROPOSTA de pedido de peças para uma OS existente. Nada é gravado até a aprovação.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'os' => ['type' => 'string', 'description' => 'Número ou id da OS.'],
                        'items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'description' => ['type' => 'string'],
                                    'quantity' => ['type' => 'integer', 'minimum' => 1],
                                ],
                                'required' => ['description'],
                            ],
                        ],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['os', 'items'],
                ],
            ],
            'propor_status_pedido_peca' => [
                'description' => 'Cria uma PROPOSTA de mudança de status de um PEDIDO DE PEÇAS (não da OS), respeitando o fluxo Solicitado → Validado → Pedido → A caminho → Recebido. Use o id do pedido retornado por buscar_pedidos_pecas.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'pedido_id' => ['type' => 'integer', 'description' => 'ID interno do pedido de peças. Não use o número da OS.'],
                        'os' => ['type' => 'string', 'description' => 'Opcional. Número da OS do pedido; usado para conferir se o pedido é o certo.'],
                        'status' => ['type' => 'string', 'enum' => array_keys(PartOrderStatus::STATUSES)],
                    ],
                    'required' => ['pedido_id', 'status'],
                ],
            ],
            'propor_status_os' => [
                'description' => 'Cria uma PROPOSTA de mudança de status da OS, respeitando o fluxo permitido.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'os' => ['type' => 'string'],
                        'to' => ['type' => 'string', 'enum' => array_keys(ServiceOrderStatus::STATUSES)],
                    ],
                    'required' => ['os', 'to'],
                ],
            ],
        ];

        $tools = [];
        foreach ($defs as $name => $def) {
            if (!$this->allowed($name)) {
                continue;
            }
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $def['description'],
                    'parameters' => $def['parameters'],
                ],
            ];
        }

        return $tools;
    }

    public function allowed(string $name): bool
    {
        if (!array_key_exists($name, self::ROLES)) {
            return false;
        }
        $roles = self::ROLES[$name];

        return $roles === null || $this->auth->hasRole($roles);
    }

    /**
     * Executa a ferramenta e devolve um resultado estruturado.
     *
     * @param array<string, mixed> $args
     * @return array{ok: bool, summary: string, renders?: array<int, array<string, mixed>>, proposal_id?: int}
     */
    public function run(string $name, array $args): array
    {
        if (!$this->allowed($name)) {
            return ['ok' => false, 'summary' => 'Ferramenta não disponível para o seu perfil.'];
        }

        $method = 'run' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        if (!method_exists($this, $method)) {
            return ['ok' => false, 'summary' => 'Ferramenta desconhecida.'];
        }

        try {
            return $this->{$method}($args);
        } catch (RuntimeException $e) {
            // Mensagens de domínio são seguras; erros técnicos não vazam.
            if ($e instanceof \PDOException) {
                error_log('[agent:tool] ' . $e->getMessage());
                return ['ok' => false, 'summary' => 'Não foi possível consultar o sistema agora.'];
            }

            return ['ok' => false, 'summary' => $e->getMessage()];
        } catch (\Throwable $e) {
            error_log('[agent:tool] ' . $e->getMessage());
            return ['ok' => false, 'summary' => 'Falha interna ao executar a ferramenta.'];
        }
    }

    // =========================================================
    // LEITURA
    // =========================================================

    /** @param array<string, mixed> $args */
    private function runBuscarOs(array $args): array
    {
        $term = trim((string) ($args['termo'] ?? ''));
        $status = (string) ($args['status'] ?? '');
        $limit = max(1, min(20, (int) ($args['limite'] ?? 8)));

        [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');

        $where = ['1 = 1'];
        $params = $scopeParams;

        if ($status !== '' && isset(ServiceOrderStatus::STATUSES[$status])) {
            $where[] = 'so.status = :status';
            $params[':status'] = $status;
        }

        if ($term !== '') {
            $conditions = ['v.plate LIKE :term1', 'v.model LIKE :term2', 'c.name LIKE :term3'];
            $params[':term1'] = '%' . $term . '%';
            $params[':term2'] = '%' . $term . '%';
            $params[':term3'] = '%' . $term . '%';

            if (ctype_digit($term)) {
                $conditions[] = 'so.number = :num';
                $conditions[] = 'so.id = :numid';
                $params[':num'] = (int) $term;
                $params[':numid'] = (int) $term;
            }

            $where[] = '(' . implode(' OR ', $conditions) . ')';
        }

        if ($scopeSql !== '') {
            $where[] = $scopeSql;
        }

        $sql = 'SELECT so.id, so.number, so.status, so.priority, so.entry_date, so.initial_complaint,
                       c.name AS client_name, v.plate, v.model, u.name AS lead_name,
                       (SELECT COUNT(*) FROM part_orders po
                         WHERE po.service_order_id = so.id
                           AND po.status IN (\'requested\',\'validated\',\'ordered\',\'on_the_way\')) AS open_parts,
                       (SELECT GROUP_CONCAT(CONCAT(i.description, \' x\', i.quantity) SEPARATOR \', \')
                          FROM part_orders po
                          JOIN part_order_items i ON i.part_order_id = po.id
                         WHERE po.service_order_id = so.id
                           AND po.status IN (\'requested\',\'validated\',\'ordered\',\'on_the_way\')) AS open_part_items
                  FROM service_orders so
                  LEFT JOIN clients c ON c.id = so.client_id
                  LEFT JOIN vehicles v ON v.id = so.vehicle_id
                  LEFT JOIN users u ON u.id = so.lead_mechanic_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY so.id DESC
                 LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            // Sem resultados não há widget: o chat responde em texto.
            return ['ok' => true, 'summary' => 'Nenhuma OS encontrada para esse critério.'];
        }

        $tableRows = [];
        $lines = [];
        foreach ($rows as $row) {
            $openParts = (int) ($row['open_parts'] ?? 0);
            $partsText = $openParts > 0
                ? ($row['open_part_items'] ?: $openParts . ' pedido(s)')
                : '—';
            $tableRows[] = [
                'number' => '#' . str_pad((string) $row['number'], 4, '0', STR_PAD_LEFT),
                'id' => (int) $row['id'],
                'client' => (string) ($row['client_name'] ?: '—'),
                'vehicle' => trim((string) ($row['plate'] ?? '') . ' ' . (string) ($row['model'] ?? '')),
                'parts' => $partsText,
                'status' => ServiceOrderStatus::label((string) $row['status']),
                'status_raw' => (string) $row['status'],
                'priority' => ServiceOrderStatus::priorityLabel((string) $row['priority']),
                'entry_date' => (string) ($row['entry_date'] ?? ''),
            ];
            $lines[] = '#' . $row['number'] . ' — ' . ($row['client_name'] ?: 'sem cliente')
                . ' — ' . ServiceOrderStatus::label((string) $row['status'])
                . ' — ' . ($row['plate'] ?: 'sem placa')
                . ($openParts > 0 ? ' — peças em aberto: ' . $partsText : '');
        }

        return [
            'ok' => true,
            'summary' => count($rows) . " OS encontrada(s):\n" . implode("\n", $lines),
            'renders' => [[
                'type' => 'table',
                'title' => 'Ordens de serviço',
                'columns' => [
                    ['key' => 'number', 'label' => 'OS'],
                    ['key' => 'client', 'label' => 'Cliente'],
                    ['key' => 'vehicle', 'label' => 'Veículo'],
                    ['key' => 'parts', 'label' => 'Peças'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'priority', 'label' => 'Prioridade'],
                ],
                'rows' => $tableRows,
                'link' => ['key' => 'id', 'base' => '/service-orders/'],
            ]],
            'data' => ['ids' => array_map(static fn (array $r): int => (int) $r['id'], $rows)],
        ];
    }

    /**
     * Pedidos de peças por cliente, OS, placa ou item.
     *
     * @param array<string, mixed> $args
     */
    private function runBuscarPedidosPecas(array $args): array
    {
        $term = trim((string) ($args['termo'] ?? ''));
        $status = (string) ($args['status'] ?? '');
        $pendingOnly = !empty($args['pendentes']);
        $limit = max(1, min(20, (int) ($args['limite'] ?? 10)));

        [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
        $where = ['1 = 1'];
        $params = $scopeParams;

        if ($status !== '' && isset(PartOrderStatus::STATUSES[$status])) {
            $where[] = 'po.status = :status';
            $params[':status'] = $status;
        } elseif ($pendingOnly) {
            $where[] = "po.status IN ('requested','validated','ordered','on_the_way')";
        }

        if ($term !== '') {
            $conditions = [
                'c.name LIKE :term_client',
                'v.plate LIKE :term_plate',
                'EXISTS (SELECT 1 FROM part_order_items pi WHERE pi.part_order_id = po.id AND pi.description LIKE :term_item)',
            ];
            $params[':term_client'] = '%' . $term . '%';
            $params[':term_plate'] = '%' . $term . '%';
            $params[':term_item'] = '%' . $term . '%';

            $numericTerm = preg_replace('/^(?:os|pedido)?\s*#?\s*/i', '', $term);
            if ($numericTerm !== '' && ctype_digit($numericTerm)) {
                $conditions[] = 'so.number = :term_number';
                $conditions[] = 'so.id = :term_order_id';
                $conditions[] = 'po.id = :term_part_order_id';
                $params[':term_number'] = (int) $numericTerm;
                $params[':term_order_id'] = (int) $numericTerm;
                $params[':term_part_order_id'] = (int) $numericTerm;
            }

            $where[] = '(' . implode(' OR ', $conditions) . ')';
        }

        if ($scopeSql !== '') {
            $where[] = $scopeSql;
        }

        $sql = "SELECT po.id, po.status, po.supplier, po.expected_date, po.created_at,
                       so.id AS order_id, so.number AS order_number,
                       c.name AS client_name, v.plate, v.model,
                       GROUP_CONCAT(CONCAT(i.description, ' x', i.quantity) SEPARATOR ', ') AS items
                  FROM part_orders po
                  JOIN service_orders so ON so.id = po.service_order_id
                  LEFT JOIN clients c ON c.id = so.client_id
                  LEFT JOIN vehicles v ON v.id = so.vehicle_id
                  LEFT JOIN part_order_items i ON i.part_order_id = po.id
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY po.id, po.status, po.supplier, po.expected_date, po.created_at,
                          so.id, so.number, c.name, v.plate, v.model
                 ORDER BY po.id DESC
                 LIMIT " . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return ['ok' => true, 'summary' => 'Nenhum pedido de peça encontrado para esse critério.'];
        }

        $lines = [];
        $tableRows = [];
        foreach ($rows as $row) {
            $order = '#' . str_pad((string) $row['order_number'], 4, '0', STR_PAD_LEFT);
            $statusLabel = PartOrderStatus::label((string) $row['status']);
            $items = trim((string) ($row['items'] ?? '')) ?: 'sem itens';
            $supplier = trim((string) ($row['supplier'] ?? ''));

            $lines[] = 'Pedido ' . (int) $row['id'] . ' — ' . $order . ' — ' . ($row['client_name'] ?: 'sem cliente')
                . ' — ' . $statusLabel . ': ' . $items
                . ($supplier !== '' ? ' (' . $supplier . ')' : '');

            $tableRows[] = [
                'id' => (int) $row['id'],
                'pedido' => '#' . (int) $row['id'],
                'order' => $order,
                'client' => (string) ($row['client_name'] ?: '—'),
                'vehicle' => trim((string) ($row['plate'] ?? '') . ' ' . (string) ($row['model'] ?? '')),
                'items' => $items,
                'status' => $statusLabel,
                'supplier' => $supplier !== '' ? $supplier : '—',
            ];
        }

        $clientCounts = array_filter(
            array_count_values(array_map(
                static fn (array $row): string => (string) ($row['client_name'] ?? ''),
                $rows
            )),
            static fn (int $n): bool => $n > 1
        );
        $multipleClients = array_keys($clientCounts);

        $summary = count($rows) . " pedido(s) de peça:\n" . implode("\n", $lines);
        if ($multipleClients) {
            $summary .= "\nAtenção: há mais de um pedido para " . implode(', ', $multipleClients)
                . '. Confirme o id do pedido antes de alterar.';
        }

        return [
            'ok' => true,
            'summary' => $summary,
            'renders' => [[
                'type' => 'table',
                'title' => 'Pedidos de peças',
                'columns' => [
                    ['key' => 'pedido', 'label' => 'Pedido'],
                    ['key' => 'order', 'label' => 'OS'],
                    ['key' => 'client', 'label' => 'Cliente'],
                    ['key' => 'vehicle', 'label' => 'Veículo'],
                    ['key' => 'items', 'label' => 'Itens'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'supplier', 'label' => 'Fornecedor'],
                ],
                'rows' => $tableRows,
                'link' => ['key' => 'id', 'base' => '/part-orders/'],
            ]],
            'data' => ['ids' => array_map(static fn (array $r): int => (int) $r['id'], $rows)],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runDetalharOs(array $args): array
    {
        $order = $this->resolveOrder((string) ($args['os'] ?? ''));
        if (!$order) {
            return ['ok' => false, 'summary' => 'OS não encontrada. Use buscar_os primeiro.'];
        }

        $orderId = (int) $order['id'];
        if (!OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            return ['ok' => false, 'summary' => 'Você não tem acesso a esta OS.'];
        }

        $tasks = $this->pdo->prepare(
            'SELECT description, status, due_date FROM service_order_tasks WHERE order_id = :o ORDER BY id'
        );
        $tasks->execute([':o' => $orderId]);
        $taskRows = $tasks->fetchAll();

        $parts = $this->pdo->prepare(
            'SELECT po.id, po.status, po.supplier, po.created_at,
                    GROUP_CONCAT(CONCAT(i.description, " x", i.quantity) SEPARATOR ", ") AS items
               FROM part_orders po
               LEFT JOIN part_order_items i ON i.part_order_id = po.id
              WHERE po.service_order_id = :o
              GROUP BY po.id, po.status, po.supplier, po.created_at
              ORDER BY po.id DESC'
        );
        $parts->execute([':o' => $orderId]);
        $partRows = $parts->fetchAll();

        $budget = $this->pdo->prepare(
            'SELECT id, number, status, total FROM budgets WHERE service_order_id = :o ORDER BY id DESC LIMIT 1'
        );
        $budget->execute([':o' => $orderId]);
        $budgetRow = $budget->fetch() ?: null;

        $summaryLines = [
            'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT),
            'Cliente: ' . ($order['client_name'] ?: '—'),
            'Veículo: ' . trim((string) ($order['plate'] ?? '') . ' ' . (string) ($order['model'] ?? '')),
            'Status: ' . ServiceOrderStatus::label((string) $order['status']),
            'Prioridade: ' . ServiceOrderStatus::priorityLabel((string) $order['priority']),
            'Queixa: ' . mb_strimwidth((string) ($order['initial_complaint'] ?? ''), 0, 200, '…'),
            'Tarefas: ' . count($taskRows) . ' | Pedidos de peça: ' . count($partRows),
        ];

        return [
            'ok' => true,
            'summary' => implode("\n", $summaryLines),
            'renders' => [[
                'type' => 'os_detail',
                'title' => 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT),
                'url' => '/service-orders/' . $orderId,
                'os' => [
                    'id' => $orderId,
                    'number' => (int) $order['number'],
                    'status' => (string) $order['status'],
                    'status_label' => ServiceOrderStatus::label((string) $order['status']),
                    'priority' => (string) $order['priority'],
                    'priority_label' => ServiceOrderStatus::priorityLabel((string) $order['priority']),
                    'complaint' => (string) ($order['initial_complaint'] ?? ''),
                    'diagnosis' => (string) ($order['diagnosis'] ?? ''),
                    'entry_date' => (string) ($order['entry_date'] ?? ''),
                ],
                'client' => [
                    'id' => (int) ($order['client_id'] ?? 0),
                    'name' => (string) ($order['client_name'] ?? ''),
                    'phone' => (string) ($order['client_phone'] ?? ''),
                ],
                'vehicle' => [
                    'id' => (int) ($order['vehicle_id'] ?? 0),
                    'plate' => (string) ($order['plate'] ?? ''),
                    'model' => (string) ($order['model'] ?? ''),
                    'year' => $order['year'] !== null ? (int) $order['year'] : null,
                    'km' => (int) ($order['current_km'] ?? 0),
                ],
                'tasks' => array_map(static fn (array $t): array => [
                    'description' => (string) $t['description'],
                    'status' => (string) $t['status'],
                ], $taskRows),
                'part_orders' => array_map(static fn (array $p): array => [
                    'id' => (int) $p['id'],
                    'status' => (string) $p['status'],
                    'items' => (string) ($p['items'] ?? ''),
                ], $partRows),
                'budget' => $budgetRow ? [
                    'id' => (int) $budgetRow['id'],
                    'status' => (string) $budgetRow['status'],
                    'total' => (float) $budgetRow['total'],
                ] : null,
            ]],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runBuscarCliente(array $args): array
    {
        $term = trim((string) ($args['termo'] ?? ''));
        $limit = max(1, min(20, (int) ($args['limite'] ?? 8)));
        if ($term === '') {
            return ['ok' => false, 'summary' => 'Informe um termo de busca.'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, c.cpf, c.phone, c.email, c.city,
                    (SELECT COUNT(*) FROM vehicles v WHERE v.client_id = c.id AND v.is_active = 1) AS vehicles
               FROM clients c
              WHERE c.is_active = 1
                AND (c.name LIKE :t1 OR c.cpf LIKE :t2 OR c.phone LIKE :t3 OR c.email LIKE :t4 OR c.city LIKE :t5)
              ORDER BY c.name
              LIMIT ' . $limit
        );
        $like = '%' . $term . '%';
        $stmt->execute([':t1' => $like, ':t2' => $like, ':t3' => $like, ':t4' => $like, ':t5' => $like]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return ['ok' => true, 'summary' => 'Nenhum cliente encontrado.'];
        }

        return [
            'ok' => true,
            'summary' => count($rows) . ' cliente(s): ' . implode(', ', array_map(
                static fn (array $r): string => $r['name'] . ' (id ' . $r['id'] . ')',
                $rows
            )),
            'renders' => [[
                'type' => 'table',
                'title' => 'Clientes',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nome'],
                    ['key' => 'cpf', 'label' => 'CPF'],
                    ['key' => 'phone', 'label' => 'Telefone'],
                    ['key' => 'city', 'label' => 'Cidade'],
                    ['key' => 'vehicles', 'label' => 'Veículos'],
                ],
                'rows' => array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'],
                    'name' => (string) $r['name'],
                    'cpf' => (string) ($r['cpf'] ?? ''),
                    'phone' => (string) ($r['phone'] ?? ''),
                    'city' => (string) ($r['city'] ?? ''),
                    'vehicles' => (int) $r['vehicles'],
                ], $rows),
                'link' => ['key' => 'id', 'base' => '/clients/'],
            ]],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runBuscarVeiculo(array $args): array
    {
        $term = trim((string) ($args['termo'] ?? ''));
        $limit = max(1, min(20, (int) ($args['limite'] ?? 8)));
        if ($term === '') {
            return ['ok' => false, 'summary' => 'Informe um termo de busca.'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT v.id, v.plate, v.brand, v.model, v.year, v.current_km, c.name AS client_name
               FROM vehicles v
               LEFT JOIN clients c ON c.id = v.client_id
              WHERE v.is_active = 1
                AND (v.plate LIKE :t1 OR v.model LIKE :t2 OR v.brand LIKE :t3 OR c.name LIKE :t4)
              ORDER BY v.id DESC
              LIMIT ' . $limit
        );
        $like = '%' . $term . '%';
        $stmt->execute([':t1' => $like, ':t2' => $like, ':t3' => $like, ':t4' => $like]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return ['ok' => true, 'summary' => 'Nenhum veículo encontrado.'];
        }

        return [
            'ok' => true,
            'summary' => count($rows) . ' veículo(s): ' . implode(', ', array_map(
                static fn (array $r): string => ($r['plate'] ?: 'sem placa') . ' ' . $r['model'] . ' (id ' . $r['id'] . ')',
                $rows
            )),
            'renders' => [[
                'type' => 'table',
                'title' => 'Veículos',
                'columns' => [
                    ['key' => 'plate', 'label' => 'Placa'],
                    ['key' => 'model', 'label' => 'Veículo'],
                    ['key' => 'year', 'label' => 'Ano'],
                    ['key' => 'km', 'label' => 'KM'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                ],
                'rows' => array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'],
                    'plate' => (string) ($r['plate'] ?? ''),
                    'model' => trim((string) ($r['brand'] ?? '') . ' ' . (string) ($r['model'] ?? '')),
                    'year' => $r['year'] !== null ? (int) $r['year'] : null,
                    'km' => (int) ($r['current_km'] ?? 0),
                    'client_name' => (string) ($r['client_name'] ?? ''),
                ], $rows),
                'link' => ['key' => 'id', 'base' => '/vehicles/'],
            ]],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runResumoOperacional(array $args): array
    {
        [$scopeSql, $scopeParams] = OrderAccess::scopeSql($this->auth, 'so');
        $where = $scopeSql !== '' ? 'WHERE ' . $scopeSql : '';

        $stmt = $this->pdo->prepare(
            "SELECT so.status, COUNT(*) AS total FROM service_orders so {$where} GROUP BY so.status"
        );
        $stmt->execute($scopeParams);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $openTotal = 0;
        foreach ($counts as $status => $total) {
            if (!in_array($status, ['delivered', 'cancelled'], true)) {
                $openTotal += $total;
            }
        }

        $partsStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM part_orders WHERE status IN ('requested','validated','ordered','on_the_way')"
        );
        $partsStmt->execute();
        $openParts = (int) $partsStmt->fetchColumn();

        $kpis = [
            ['label' => 'OS em andamento', 'value' => $openTotal, 'tone' => 'primary'],
            ['label' => 'Aguardando peça', 'value' => $counts['awaiting_part'] ?? 0, 'tone' => 'warning'],
            ['label' => 'Prontas p/ entrega', 'value' => $counts['ready_for_delivery'] ?? 0, 'tone' => 'success'],
            ['label' => 'Pedidos de peça abertos', 'value' => $openParts, 'tone' => 'info'],
        ];

        $bars = [];
        foreach (ServiceOrderStatus::STATUSES as $status => $label) {
            $bars[] = ['label' => $label, 'value' => $counts[$status] ?? 0, 'key' => $status];
        }

        return [
            'ok' => true,
            'summary' => 'OS em andamento: ' . $openTotal
                . '; aguardando peça: ' . ($counts['awaiting_part'] ?? 0)
                . '; prontas para entrega: ' . ($counts['ready_for_delivery'] ?? 0)
                . '; pedidos de peça abertos: ' . $openParts . '.',
            'renders' => [
                ['type' => 'kpi', 'title' => 'Resumo do dia', 'items' => $kpis],
                ['type' => 'bars', 'title' => 'OS por status', 'items' => $bars],
            ],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runBuscarPeca(array $args): array
    {
        $term = trim((string) ($args['termo'] ?? ''));
        $limit = max(1, min(20, (int) ($args['limite'] ?? 8)));
        if ($term === '') {
            return ['ok' => false, 'summary' => 'Informe um termo de busca.'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, name, brand, part_code, category, price, stock
               FROM parts
              WHERE is_active = 1
                AND (name LIKE :t1 OR brand LIKE :t2 OR part_code LIKE :t3 OR category LIKE :t4)
              ORDER BY name
              LIMIT ' . $limit
        );
        $like = '%' . $term . '%';
        $stmt->execute([':t1' => $like, ':t2' => $like, ':t3' => $like, ':t4' => $like]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return ['ok' => true, 'summary' => 'Nenhuma peça encontrada no catálogo local.'];
        }

        return [
            'ok' => true,
            'summary' => count($rows) . ' peça(s): ' . implode(', ', array_map(
                static fn (array $r): string => $r['name'] . ' (R$ ' . number_format((float) $r['price'], 2, ',', '.') . ')',
                $rows
            )),
            'renders' => [[
                'type' => 'table',
                'title' => 'Peças',
                'columns' => [
                    ['key' => 'name', 'label' => 'Peça'],
                    ['key' => 'brand', 'label' => 'Marca'],
                    ['key' => 'part_code', 'label' => 'Código'],
                    ['key' => 'price', 'label' => 'Preço', 'money' => true],
                    ['key' => 'stock', 'label' => 'Estoque'],
                ],
                'rows' => array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'],
                    'name' => (string) $r['name'],
                    'brand' => (string) ($r['brand'] ?? ''),
                    'part_code' => (string) ($r['part_code'] ?? ''),
                    'price' => (float) ($r['price'] ?? 0),
                    'stock' => (int) ($r['stock'] ?? 0),
                ], $rows),
                'link' => ['key' => 'id', 'base' => '/parts/'],
            ]],
        ];
    }

    // =========================================================
    // PROPOSTAS (escrita sempre mediada)
    // =========================================================

    /** @param array<string, mixed> $args */
    private function runBuscarPecasVeiculo(array $args): array
    {
        $orderId = 0;
        if (trim((string) ($args['os'] ?? '')) !== '') {
            $order = $this->resolveOrder((string) $args['os']);
            if (!$order) {
                return ['ok' => false, 'summary' => 'OS não encontrada. Use buscar_os antes.'];
            }
            $orderId = (int) $order['id'];
            if (!OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
                return ['ok' => false, 'summary' => 'Você não tem acesso a esta OS.'];
            }
        }

        $vehicle = $orderId > 0 ? $this->finder->vehicleFromOrder($orderId) : null;
        if (!$vehicle && trim((string) ($args['placa'] ?? '')) !== '') {
            $vehicle = $this->finder->vehicleFromPlate((string) $args['placa']);
        }

        if (!$vehicle) {
            return [
                'ok' => false,
                'summary' => 'Preciso da OS ou da placa para consultar a peça correta para o veículo.',
            ];
        }

        $description = trim((string) ($args['descricao'] ?? ''));
        if ($description === '') {
            return ['ok' => false, 'summary' => 'Descreva a peça que o mecânico pediu.'];
        }

        $quantity = max(1, (int) ($args['quantidade'] ?? 1));
        $category = isset($args['categoria']) ? (string) $args['categoria'] : null;

        $result = $this->finder->find($vehicle, $description, $category, $orderId ?: null, $quantity);
        $offers = $result['offers'];

        $widgetOffers = array_map(static fn (array $offer): array => [
            'token' => $offer['token'],
            'source' => $offer['source'],
            'title' => $offer['title'],
            'brand' => $offer['brand'],
            'code' => $offer['code'],
            'price' => $offer['price'],
            'stock' => $offer['stock'],
            'url' => $offer['url'],
            'image' => $offer['image'],
            'fitment' => $offer['fitment'],
        ], $offers);

        $summary = 'Categoria: ' . $result['category']['label']
            . ' | Veículo: ' . $vehicle['brand'] . ' ' . $vehicle['model']
            . ($vehicle['year'] ? ' ' . $vehicle['year'] : '')
            . ' | Ficha: ' . $result['spec']['label'];

        if ($offers) {
            $best = $offers[0];
            $summary .= ' | ' . count($offers) . ' oferta(s). Melhor: ' . $best['title']
                . ($best['price'] !== null ? ' por R$ ' . number_format((float) $best['price'], 2, ',', '.') : '')
                . ' (' . $best['fitment'] . '). Os detalhes estão no cartão; '
                . 'aguarde o mecânico escolher — não repita a lista em texto.';
        } else {
            $summary .= ' | Nenhuma oferta com evidência suficiente de compatibilidade.';
        }

        if ($result['notes']) {
            $summary .= ' Observações: ' . implode(' ', $result['notes']);
        }

        return [
            'ok' => true,
            'summary' => $summary,
            'renders' => [[
                'type' => 'parts_choice',
                'title' => $result['category']['label'] . ' — ' . $vehicle['brand'] . ' ' . $vehicle['model'],
                'subtitle' => trim('Placa ' . $vehicle['plate'] . ($vehicle['year'] ? ' · ' . $vehicle['year'] : '')),
                'spec' => $result['spec'],
                'category' => $result['category']['label'],
                'offers' => $widgetOffers,
                'links' => $result['links'],
                'notes' => $result['notes'],
                'can_select' => $orderId > 0,
                'order_id' => $orderId ?: null,
            ]],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runFichaVeiculo(array $args): array
    {
        $vehicle = null;
        if (trim((string) ($args['os'] ?? '')) !== '') {
            $order = $this->resolveOrder((string) $args['os']);
            if ($order) {
                $vehicle = $this->finder->vehicleFromOrder((int) $order['id']);
            }
        } elseif (trim((string) ($args['placa'] ?? '')) !== '') {
            $vehicle = $this->finder->vehicleFromPlate((string) $args['placa']);
        }

        if (!$vehicle && trim((string) ($args['marca'] ?? '')) !== '' && trim((string) ($args['modelo'] ?? '')) !== '') {
            $vehicle = [
                'id' => 0,
                'plate' => '',
                'brand' => (string) $args['marca'],
                'model' => (string) $args['modelo'],
                'year' => (int) ($args['ano'] ?? 0),
                'make_slug' => VehicleSpecService::makeSlug((string) $args['marca']),
                'model_slug' => VehicleSpecService::modelSlug((string) $args['modelo']),
                'engine' => VehicleSpecService::engine((string) $args['modelo']),
            ];
        }

        if (!$vehicle) {
            return ['ok' => false, 'summary' => 'Informe a OS, a placa ou marca/modelo/ano.'];
        }

        $spec = $this->specs->forVehicle($vehicle);
        $info = $this->specs->describe($spec);

        $summary = 'Ficha de ' . $vehicle['brand'] . ' ' . $vehicle['model']
            . ($vehicle['year'] ? ' ' . $vehicle['year'] : '') . ': ' . $info['label'];
        if ($info['source']) {
            $summary .= ' | fonte: ' . $info['source'];
        }
        $confidenceLabels = ['high' => 'alta', 'medium' => 'média', 'low' => 'baixa'];
        $summary .= ' | confiança: ' . ($confidenceLabels[$info['confidence']] ?? $info['confidence']);
        if (!$info['known']) {
            $summary .= '. Não há ficha cadastrada; confirme a especificação no manual antes de solicitar. '
                . 'Um admin pode cadastrar com salvar_ficha_veiculo.';
        }

        return [
            'ok' => true,
            'summary' => $summary,
            'renders' => [[
                'type' => 'spec',
                'title' => 'Ficha técnica — ' . $vehicle['brand'] . ' ' . $vehicle['model'],
                'vehicle' => [
                    'plate' => $vehicle['plate'],
                    'brand' => $vehicle['brand'],
                    'model' => $vehicle['model'],
                    'year' => $vehicle['year'],
                ],
                'known' => $info['known'],
                'label' => $info['label'],
                'source' => $info['source'],
                'confidence' => $info['confidence'],
                'codes' => array_map(
                    static fn ($label, $value): array => ['label' => $label, 'value' => $value],
                    array_keys($info['codes']),
                    array_values($info['codes'])
                ),
                'notes' => $info['notes'] ?? null,
            ]],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runSalvarFichaVeiculo(array $args): array
    {
        try {
            $spec = $this->specs->upsert([
                'make' => $args['marca'] ?? '',
                'model' => $args['modelo'] ?? '',
                'year_from' => $args['ano_de'] ?? null,
                'year_to' => $args['ano_ate'] ?? null,
                'engine' => $args['motor'] ?? null,
                'oil_viscosity' => $args['oleo_viscosidade'] ?? null,
                'oil_capacity_l' => $args['oleo_capacidade_l'] ?? null,
                'oil_spec' => $args['oleo_especificacao'] ?? null,
                'oil_filter_code' => $args['filtro_oleo'] ?? null,
                'air_filter_code' => $args['filtro_ar'] ?? null,
                'cabin_filter_code' => $args['filtro_cabine'] ?? null,
                'fuel_filter_code' => $args['filtro_combustivel'] ?? null,
                'tire_size' => $args['pneus'] ?? null,
                'source' => $args['fonte'] ?? null,
                'confidence' => $args['confianca'] ?? 'medium',
                'notes' => $args['observacoes'] ?? null,
            ], (int) $this->auth->id());
        } catch (\Throwable $e) {
            return ['ok' => false, 'summary' => $e->getMessage()];
        }

        $info = $this->specs->describe($spec);

        return [
            'ok' => true,
            'summary' => 'Ficha salva para ' . $spec['make'] . ' ' . $spec['model'] . ': ' . $info['label']
                . ' (fonte: ' . ($spec['source'] ?: 'não informada') . ').',
            'renders' => [[
                'type' => 'spec',
                'title' => 'Ficha técnica salva',
                'vehicle' => [
                    'plate' => '',
                    'brand' => (string) $spec['make'],
                    'model' => (string) $spec['model'],
                    'year' => (int) ($spec['year_from'] ?? 0),
                ],
                'known' => true,
                'label' => $info['label'],
                'source' => $info['source'],
                'confidence' => $info['confidence'],
                'codes' => array_map(
                    static fn ($label, $value): array => ['label' => $label, 'value' => $value],
                    array_keys($info['codes']),
                    array_values($info['codes'])
                ),
                'notes' => $info['notes'] ?? null,
            ]],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runProporCadastroCliente(array $args): array
    {
        $name = trim((string) ($args['nome'] ?? $args['name'] ?? ''));
        if (mb_strlen($name) < 3) {
            return ['ok' => false, 'summary' => 'Informe o nome completo do cliente.'];
        }

        $cpf = trim((string) ($args['cpf'] ?? ''));
        $phone = trim((string) ($args['telefone'] ?? $args['phone'] ?? ''));
        $email = strtolower(trim((string) ($args['email'] ?? '')));
        $city = trim((string) ($args['cidade'] ?? ''));
        $state = strtoupper(trim((string) ($args['estado'] ?? '')));
        $notes = trim((string) ($args['observacoes'] ?? $args['notes'] ?? ''));

        $warnings = [];

        if ($cpf !== '') {
            $stmt = $this->pdo->prepare('SELECT id, name FROM clients WHERE cpf = :cpf LIMIT 1');
            $stmt->execute([':cpf' => $cpf]);
            if ($row = $stmt->fetch()) {
                $warnings[] = 'Já existe um cliente com este CPF: ' . $row['name'] . ' (#' . (int) $row['id'] . ').';
            }
        }

        if (!$warnings) {
            $stmt = $this->pdo->prepare('SELECT id, name FROM clients WHERE LOWER(name) = LOWER(:n) LIMIT 1');
            $stmt->execute([':n' => $name]);
            if ($row = $stmt->fetch()) {
                $warnings[] = 'Já existe um cliente com este nome: ' . $row['name'] . ' (#' . (int) $row['id']
                    . '). Confirme se é outra pessoa.';
            }
        }

        $payload = [
            'name' => mb_substr($name, 0, 120),
            'cpf' => $cpf !== '' ? mb_substr($cpf, 0, 14) : '',
            'phone' => mb_substr($phone, 0, 20),
            'email' => mb_substr($email, 0, 120),
            'city' => mb_substr($city, 0, 80),
            'state' => mb_substr($state, 0, 2),
            'notes' => mb_substr($notes, 0, 2000),
        ];

        $fields = [
            ['label' => 'Nome', 'value' => $payload['name']],
            ['label' => 'CPF', 'value' => $payload['cpf'] !== '' ? $payload['cpf'] : '—'],
            ['label' => 'Telefone', 'value' => $payload['phone'] !== '' ? $payload['phone'] : '—'],
            ['label' => 'E-mail', 'value' => $payload['email'] !== '' ? $payload['email'] : '—'],
            ['label' => 'Cidade', 'value' => $payload['city'] !== ''
                ? $payload['city'] . ($payload['state'] !== '' ? '/' . $payload['state'] : '')
                : '—'],
        ];

        $preview = [
            'kind' => 'client_draft',
            'title' => 'Cadastrar cliente',
            'subtitle' => $payload['name'] . ($payload['phone'] !== '' ? ' — ' . $payload['phone'] : ''),
            'risk' => 'low',
            'approve_label' => 'Criar cliente',
            'fields' => $fields,
            'items' => [],
            'warnings' => $warnings,
            'tasks' => [],
        ];

        $intent = 'client:new:' . ($cpf !== ''
            ? 'cpf:' . preg_replace('/\D/', '', $cpf)
            : 'name:' . VehicleSpecService::normalize($name));

        $proposal = $this->proposals->create(
            (int) ($args['__session_id'] ?? 0),
            (int) $this->auth->id(),
            'propor_cadastro_cliente',
            $payload,
            $preview,
            $intent,
            false
        );

        $summary = 'Proposta de cadastro criada para ' . $payload['name'] . '. Nada foi gravado; '
            . 'aguarda aprovação no cartão.';
        if ($warnings) {
            $summary .= ' Atenção: ' . implode(' ', $warnings);
        }

        return [
            'ok' => true,
            'summary' => $summary,
            'renders' => [[
                'type' => 'proposal',
                'proposal' => $this->proposalCard($proposal),
            ]],
            'proposal_id' => (int) $proposal['id'],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runProporAbrirOs(array $args): array
    {
        $client = null;
        $clientId = (int) ($args['client_id'] ?? 0);
        if ($clientId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, name, phone FROM clients WHERE id = :id AND is_active = 1');
            $stmt->execute([':id' => $clientId]);
            $client = $stmt->fetch() ?: null;
        } else {
            $name = trim((string) ($args['client_name'] ?? ''));
            if ($name !== '') {
                $stmt = $this->pdo->prepare(
                    'SELECT id, name, phone FROM clients WHERE name LIKE :n AND is_active = 1 ORDER BY name LIMIT 2'
                );
                $stmt->execute([':n' => '%' . $name . '%']);
                $found = $stmt->fetchAll();
                if (count($found) === 1) {
                    $client = $found[0];
                } elseif (count($found) > 1) {
                    return ['ok' => false, 'summary' => 'Mais de um cliente corresponde a "' . $name . '". Use buscar_cliente e informe o client_id.'];
                }
            }
        }

        if (!$client) {
            return ['ok' => false, 'summary' => 'Cliente não encontrado. Use buscar_cliente e informe o client_id.'];
        }

        $vehicle = null;
        $vehicleId = (int) ($args['vehicle_id'] ?? 0);
        $plate = strtoupper(trim((string) ($args['plate'] ?? '')));
        if ($vehicleId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT id, client_id, plate, brand, model, year, current_km FROM vehicles
                  WHERE id = :id AND client_id = :c AND is_active = 1'
            );
            $stmt->execute([':id' => $vehicleId, ':c' => (int) $client['id']]);
            $vehicle = $stmt->fetch() ?: null;
        } elseif ($plate !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT id, client_id, plate, brand, model, year, current_km FROM vehicles
                  WHERE plate = :p AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([':p' => $plate]);
            $vehicle = $stmt->fetch() ?: null;
            if ($vehicle && (int) $vehicle['client_id'] !== (int) $client['id']) {
                return ['ok' => false, 'summary' => 'A placa ' . $plate . ' pertence a outro cliente.'];
            }
        }

        if (!$vehicle) {
            return ['ok' => false, 'summary' => 'Veículo não encontrado para este cliente. Use buscar_veiculo e informe o vehicle_id.'];
        }

        $complaint = trim((string) ($args['initial_complaint'] ?? ''));
        if ($complaint === '') {
            return ['ok' => false, 'summary' => 'A queixa do cliente é obrigatória.'];
        }

        $priority = (string) ($args['priority'] ?? 'normal');
        if (!isset(ServiceOrderStatus::PRIORITIES[$priority])) {
            $priority = 'normal';
        }

        $tasks = array_values(array_filter(array_map(
            static fn ($task): string => trim((string) (is_array($task) ? ($task['description'] ?? '') : $task)),
            (array) ($args['tasks'] ?? [])
        )));
        $tasks = array_map(static fn (string $t): string => mb_substr($t, 0, 255), $tasks);

        $parts = [];
        foreach ((array) ($args['parts'] ?? []) as $part) {
            $description = trim((string) (is_array($part) ? ($part['description'] ?? '') : $part));
            if ($description === '') {
                continue;
            }
            $parts[] = [
                'description' => mb_substr($description, 0, 200),
                'quantity' => max(1, (int) (is_array($part) ? ($part['quantity'] ?? 1) : 1)),
            ];
        }

        $payload = [
            'client_id' => (int) $client['id'],
            'vehicle_id' => (int) $vehicle['id'],
            'initial_complaint' => mb_substr($complaint, 0, 5000),
            'priority' => $priority,
            'tasks' => $tasks,
            'parts' => $parts,
            'internal_notes' => mb_substr(trim((string) ($args['internal_notes'] ?? '')), 0, 5000),
        ];

        $warnings = [];
        $openStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM service_orders
              WHERE vehicle_id = :v AND status NOT IN ('delivered','cancelled')"
        );
        $openStmt->execute([':v' => $payload['vehicle_id']]);
        $open = (int) $openStmt->fetchColumn();
        if ($open > 0) {
            $warnings[] = 'Este veículo já tem ' . $open . ' OS em andamento.';
        }

        $preview = [
            'kind' => 'os_draft',
            'title' => 'Abrir ordem de serviço',
            'subtitle' => $client['name'] . ' — ' . ($vehicle['plate'] ?: $vehicle['model']),
            'risk' => 'medium',
            'approve_label' => 'Criar OS',
            'fields' => [
                ['label' => 'Cliente', 'value' => (string) $client['name']],
                ['label' => 'Veículo', 'value' => trim((string) $vehicle['plate'] . ' ' . (string) $vehicle['brand'] . ' ' . (string) $vehicle['model'])],
                ['label' => 'Prioridade', 'value' => ServiceOrderStatus::priorityLabel($priority)],
                ['label' => 'Queixa', 'value' => $complaint],
            ],
            'items' => $parts,
            'warnings' => $warnings,
            'tasks' => $tasks,
        ];

        $proposal = $this->proposals->create(
            (int) ($args['__session_id'] ?? 0),
            (int) $this->auth->id(),
            'propor_abrir_os',
            $payload,
            $preview,
            'os:vehicle:' . (int) $payload['vehicle_id'],
            false
        );

        return [
            'ok' => true,
            'summary' => 'Proposta criada: abrir OS para ' . $client['name'] . ' (' . ($vehicle['plate'] ?: $vehicle['model']) . '). '
                . 'Nada foi gravado. O usuário precisa aprovar na tela.',
            'renders' => [[
                'type' => 'proposal',
                'proposal' => $this->proposalCard($proposal),
            ]],
            'proposal_id' => (int) $proposal['id'],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runProporPedidoPecas(array $args): array
    {
        $order = $this->resolveOrder((string) ($args['os'] ?? ''));
        if (!$order) {
            return ['ok' => false, 'summary' => 'OS não encontrada. Use buscar_os primeiro.'];
        }

        $orderId = (int) $order['id'];
        if (!OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            return ['ok' => false, 'summary' => 'Você não tem acesso a esta OS.'];
        }

        $items = [];
        foreach ((array) ($args['items'] ?? []) as $item) {
            $description = trim((string) (is_array($item) ? ($item['description'] ?? '') : $item));
            if ($description === '') {
                continue;
            }

            $entry = [
                'description' => mb_substr($description, 0, 200),
                'quantity' => max(1, (int) (is_array($item) ? ($item['quantity'] ?? 1) : 1)),
            ];

            if (is_array($item)) {
                $sku = trim((string) ($item['sku'] ?? ''));
                $supplier = trim((string) ($item['supplier'] ?? ''));
                $url = trim((string) ($item['product_url'] ?? ''));
                $unitPrice = $item['unit_price'] ?? null;

                if ($sku !== '') {
                    $entry['sku'] = mb_substr($sku, 0, 60);
                }
                if ($supplier !== '') {
                    $entry['supplier'] = mb_substr($supplier, 0, 120);
                }
                if ($url !== '') {
                    $entry['product_url'] = mb_substr($url, 0, 400);
                }
                if ($unitPrice !== null && $unitPrice !== '') {
                    $entry['unit_price'] = (float) $unitPrice;
                }
            }

            $items[] = $entry;
        }

        if (!$items) {
            return ['ok' => false, 'summary' => 'Informe ao menos um item para o pedido.'];
        }

        $payload = [
            'service_order_id' => $orderId,
            'items' => $items,
            'notes' => mb_substr(trim((string) ($args['notes'] ?? '')), 0, 2000),
        ];

        $warnings = [];
        $openStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM part_orders
              WHERE service_order_id = :o AND status NOT IN ('received','cancelled')"
        );
        $openStmt->execute([':o' => $orderId]);
        $open = (int) $openStmt->fetchColumn();
        if ($open > 0) {
            $warnings[] = 'Já existe ' . $open . ' pedido de peças em aberto para esta OS.';
        }

        $preview = [
            'kind' => 'part_order_draft',
            'title' => 'Solicitar peças',
            'subtitle' => 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT)
                . ' — ' . ($order['client_name'] ?: 'sem cliente'),
            'risk' => 'low',
            'approve_label' => 'Criar pedido',
            'fields' => [
                ['label' => 'OS', 'value' => '#' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT)],
                ['label' => 'Cliente', 'value' => (string) ($order['client_name'] ?? '—')],
                ['label' => 'Veículo', 'value' => (string) ($order['plate'] ?? '—')],
                ['label' => 'Observações', 'value' => (string) ($payload['notes'] ?: '—')],
            ],
            'items' => $items,
            'warnings' => $warnings,
            'tasks' => [],
        ];

        $proposal = $this->proposals->create(
            (int) ($args['__session_id'] ?? 0),
            (int) $this->auth->id(),
            'propor_pedido_pecas',
            $payload,
            $preview,
            'partorder:os:' . $orderId,
            true
        );

        return [
            'ok' => true,
            'summary' => 'Proposta criada: pedido de ' . count($items) . ' item(ns) para a OS #' . $order['number']
                . '. Nada foi gravado; aguarda aprovação.',
            'renders' => [[
                'type' => 'proposal',
                'proposal' => $this->proposalCard($proposal),
            ]],
            'proposal_id' => (int) $proposal['id'],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runProporStatusPedidoPeca(array $args): array
    {
        $partOrderId = (int) ($args['pedido_id'] ?? 0);
        $osRef = trim((string) ($args['os'] ?? ''));
        $to = (string) ($args['status'] ?? '');

        if ($partOrderId <= 0) {
            return ['ok' => false, 'summary' => 'Informe o id do pedido de peças retornado pela consulta.'];
        }
        if (!isset(PartOrderStatus::STATUSES[$to])) {
            return ['ok' => false, 'summary' => 'Status de pedido inválido.'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT po.*, so.number AS order_number, so.created_by AS os_created_by,
                    so.lead_mechanic_id, c.name AS client_name, v.plate
               FROM part_orders po
               JOIN service_orders so ON so.id = po.service_order_id
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
              WHERE po.id = :id'
        );
        $stmt->execute([':id' => $partOrderId]);
        $partOrder = $stmt->fetch();

        if (!$partOrder) {
            return ['ok' => false, 'summary' => 'Pedido de peças não encontrado.'];
        }

        // O vínculo do pedido com a OS é feito pelo próprio pedido. A OS
        // informada serve como conferência; se apontar para outra OS existente,
        // a ação é recusada. Se não existir, vale o vínculo do pedido.
        $orderId = (int) $partOrder['service_order_id'];
        if ($osRef !== '') {
            $informedOrder = $this->resolveOrder($osRef);
            if ($informedOrder && (int) $informedOrder['id'] !== $orderId) {
                return [
                    'ok' => false,
                    'summary' => 'O pedido #' . $partOrderId . ' pertence à OS #'
                        . str_pad((string) $partOrder['order_number'], 4, '0', STR_PAD_LEFT)
                        . ', não à OS informada. Confirme o pedido correto.',
                ];
            }
        }

        if (!OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            return ['ok' => false, 'summary' => 'Você não tem acesso a este pedido.'];
        }

        $from = (string) $partOrder['status'];
        if ($from === $to) {
            return ['ok' => false, 'summary' => 'Este pedido já está como "' . PartOrderStatus::label($from) . '".'];
        }

        if (!PartOrderStatus::canTransition($from, $to)) {
            $next = PartOrderStatus::next($from);
            return [
                'ok' => false,
                'summary' => 'O pedido está como "' . PartOrderStatus::label($from) . '". '
                    . ($next
                        ? 'O próximo passo permitido é: ' . implode(', ', array_map([PartOrderStatus::class, 'label'], $next)) . '.'
                        : 'Este pedido está em estado final.'),
            ];
        }

        $osLabel = '#' . str_pad((string) $partOrder['order_number'], 4, '0', STR_PAD_LEFT);
        $payload = [
            'part_order_id' => $partOrderId,
            'from' => $from,
            'to' => $to,
        ];
        $preview = [
            'kind' => 'part_status_change',
            'title' => 'Alterar status do pedido de peças',
            'subtitle' => 'Pedido #' . $partOrderId . ' · OS ' . $osLabel,
            'risk' => $to === 'cancelled' ? 'high' : 'low',
            'approve_label' => 'Confirmar mudança',
            'fields' => [
                ['label' => 'Pedido', 'value' => '#' . $partOrderId],
                ['label' => 'OS', 'value' => $osLabel],
                ['label' => 'Cliente', 'value' => (string) ($partOrder['client_name'] ?? '—')],
                ['label' => 'Fornecedor', 'value' => (string) ($partOrder['supplier'] ?: '—')],
                ['label' => 'De', 'value' => PartOrderStatus::label($from)],
                ['label' => 'Para', 'value' => PartOrderStatus::label($to)],
            ],
            'items' => [],
            'warnings' => $to === 'cancelled' ? ['Cancelar o pedido é uma ação de impacto.'] : [],
            'tasks' => [],
        ];

        $proposal = $this->proposals->create(
            (int) ($args['__session_id'] ?? 0),
            (int) $this->auth->id(),
            'propor_status_pedido_peca',
            $payload,
            $preview,
            'partstatus:' . $partOrderId,
            false
        );

        return [
            'ok' => true,
            'summary' => 'Proposta criada: mudar o pedido #' . $partOrderId . ' (OS ' . $osLabel . ') de "'
                . PartOrderStatus::label($from) . '" para "' . PartOrderStatus::label($to)
                . '". Aguarda aprovação.',
            'renders' => [[
                'type' => 'proposal',
                'proposal' => $this->proposalCard($proposal),
            ]],
            'proposal_id' => (int) $proposal['id'],
        ];
    }

    /** @param array<string, mixed> $args */
    private function runProporStatusOs(array $args): array
    {
        $order = $this->resolveOrder((string) ($args['os'] ?? ''));
        if (!$order) {
            return ['ok' => false, 'summary' => 'OS não encontrada. Use buscar_os primeiro.'];
        }

        $orderId = (int) $order['id'];
        if (!OrderAccess::canAccessOrderId($this->pdo, $this->auth, $orderId)) {
            return ['ok' => false, 'summary' => 'Você não tem acesso a esta OS.'];
        }

        $from = (string) $order['status'];
        $to = (string) ($args['to'] ?? '');
        if (!isset(ServiceOrderStatus::STATUSES[$to])) {
            return ['ok' => false, 'summary' => 'Status inválido.'];
        }

        $allowed = ServiceOrderStatus::next($from);
        if (!in_array($to, $allowed, true)) {
            return [
                'ok' => false,
                'summary' => 'Transição não permitida de "' . ServiceOrderStatus::label($from) . '". '
                    . ($allowed ? 'Permitidos: ' . implode(', ', array_map([ServiceOrderStatus::class, 'label'], $allowed)) : 'Esta OS está em estado final.'),
            ];
        }

        $payload = [
            'service_order_id' => $orderId,
            'from' => $from,
            'to' => $to,
        ];

        $preview = [
            'kind' => 'status_change',
            'title' => 'Alterar status da OS',
            'subtitle' => 'OS #' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT),
            'risk' => $to === 'cancelled' ? 'high' : 'medium',
            'approve_label' => 'Confirmar mudança',
            'fields' => [
                ['label' => 'OS', 'value' => '#' . str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT)],
                ['label' => 'De', 'value' => ServiceOrderStatus::label($from)],
                ['label' => 'Para', 'value' => ServiceOrderStatus::label($to)],
            ],
            'items' => [],
            'warnings' => $to === 'cancelled' ? ['Cancelar a OS é uma ação de impacto.'] : [],
            'tasks' => [],
        ];

        $proposal = $this->proposals->create(
            (int) ($args['__session_id'] ?? 0),
            (int) $this->auth->id(),
            'propor_status_os',
            $payload,
            $preview,
            'status:os:' . $orderId,
            false
        );

        return [
            'ok' => true,
            'summary' => 'Proposta criada: mudar a OS #' . $order['number'] . ' de "'
                . ServiceOrderStatus::label($from) . '" para "' . ServiceOrderStatus::label($to)
                . '". Aguarda aprovação.',
            'renders' => [[
                'type' => 'proposal',
                'proposal' => $this->proposalCard($proposal),
            ]],
            'proposal_id' => (int) $proposal['id'],
        ];
    }

    // =========================================================
    // INTERNOS
    // =========================================================

    /** @return array<string, mixed>|null */
    private function resolveOrder(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = ltrim($value, '#OSos ');

        if (!ctype_digit($value)) {
            return null;
        }

        $number = (int) $value;

        $stmt = $this->pdo->prepare(
            'SELECT so.*, c.name AS client_name, c.phone AS client_phone,
                    v.plate, v.model, v.brand, v.year, v.current_km
               FROM service_orders so
               LEFT JOIN clients c ON c.id = so.client_id
               LEFT JOIN vehicles v ON v.id = so.vehicle_id
              WHERE so.number = :n
              LIMIT 1'
        );
        $stmt->execute([':n' => $number]);
        $row = $stmt->fetch();

        if (!$row) {
            $stmt = $this->pdo->prepare(
                'SELECT so.*, c.name AS client_name, c.phone AS client_phone,
                        v.plate, v.model, v.brand, v.year, v.current_km
                   FROM service_orders so
                   LEFT JOIN clients c ON c.id = so.client_id
                   LEFT JOIN vehicles v ON v.id = so.vehicle_id
                  WHERE so.id = :id
                  LIMIT 1'
            );
            $stmt->execute([':id' => $number]);
            $row = $stmt->fetch();
        }

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    private function proposalCard(array $proposal): array
    {
        return ProposalService::card($proposal);
    }
}
