<?php
// =============================================
// PEÇAS — CRM completo do catálogo
// Listagem no padrão e-commerce do Vuexy,
// cadastro/edição com estoque, preço, imagem,
// aplicação veicular, categorias e fornecedores.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Mechanic\Resources\PartResource;
use Modules\Mechanic\Services\PartsCatalog;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

class PartController extends CrudController
{
    private const STATUSES = [
        'published' => 'Publicado',
        'draft' => 'Rascunho',
        'inactive' => 'Inativo',
    ];

    protected function resource(): Resource
    {
        return new PartResource();
    }

    public function index(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        return $this->render('mechanic::parts/index', [
            'title' => 'Catálogo de peças',
            'resource' => $resource,
            'stats' => $this->stats(),
            'categories' => $this->categoryOptions(),
            'datatable' => [
                'url' => '/parts/datatable',
                'basePath' => '/parts',
                'csrf' => $this->session->csrfToken(),
                'exportUrl' => '/parts/export',
                'bulkDeleteUrl' => '/parts/bulk-delete',
                'labels' => $resource->labels(),
            ],
            'pageStyles' => [
                '/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css',
                '/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css',
                '/assets/vendor/libs/datatables-select-bs5/select.bootstrap5.css',
                '/assets/vendor/libs/select2/select2.css',
            ],
            'pageScripts' => [
                '/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
                '/js/parts-datatable.js',
            ],
        ]);
    }

    public function datatable(ServerRequestInterface $request)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }
        if (!$this->auth->hasRole($this->resource()->roles())) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $rows = $this->pdo->query(
            "SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
                    (SELECT COUNT(*) FROM cdp_offers o WHERE o.sku_id = p.cdp_sku) AS offers_count,
                    (SELECT MIN(o.price) FROM cdp_offers o WHERE o.sku_id = p.cdp_sku AND o.price > 0) AS best_offer
               FROM parts p
               LEFT JOIN part_categories c ON c.id = p.category_id
              ORDER BY p.name ASC"
        )->fetchAll();

        $data = [];
        foreach ($rows as $row) {
            $row = $this->decorateRow($row);
            unset($row['raw_json']);
            $data[] = $row;
        }

        return json_response(['data' => $data]);
    }

    public function export(ServerRequestInterface $request)
    {
        if ($response = $this->guardRoles($this->resource()->roles())) {
            return $response;
        }

        $rows = $this->pdo->query(
            "SELECT p.*, c.name AS category_name
               FROM parts p
               LEFT JOIN part_categories c ON c.id = p.category_id
              ORDER BY p.name ASC"
        )->fetchAll();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['ID', 'Peça', 'Marca', 'Código', 'SKU', 'Categoria', 'Preço', 'Custo', 'Estoque', 'Mínimo', 'Status', 'Fornecedor', 'Atualizado em'], ';');

        foreach ($rows as $row) {
            fputcsv($handle, [
                (int) $row['id'],
                (string) $row['name'],
                (string) ($row['brand'] ?? ''),
                (string) ($row['part_code'] ?? ''),
                (string) ($row['sku'] ?? $row['cdp_sku'] ?? ''),
                (string) ($row['category_name'] ?? $row['category'] ?? ''),
                $row['price'] !== null ? number_format((float) $row['price'], 2, ',', '') : '',
                $row['cost_price'] !== null ? number_format((float) $row['cost_price'], 2, ',', '') : '',
                (int) $row['stock'],
                (int) $row['min_stock'],
                self::STATUSES[$row['status']] ?? (string) $row['status'],
                (string) ($row['supplier'] ?? ''),
                (string) ($row['updated_at'] ?? ''),
            ], ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return new Response(200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pecas-' . date('Y-m-d') . '.csv"',
        ], $csv);
    }

    public function create(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $query = $request->getQueryParams();
        $old = [];
        if (!empty($query['duplicate'])) {
            $record = $this->find((int) $query['duplicate']);
            if ($record) {
                $old = $record;
                $old['name'] = mb_substr((string) $record['name'] . ' (cópia)', 0, 200);
                $old['sku'] = '';
                $old['cdp_sku'] = null;
                $old['image'] = $record['image'] ?? '';
            }
        }

        return $this->renderForm(null, $old, []);
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

        [$data, $errors] = $this->validateProduct($request->getParsedBody() ?? [], null);
        if ($errors) {
            return $this->renderForm(null, $data, $errors, 422);
        }

        $data['is_active'] = ($data['status'] ?? 'published') === 'published' ? 1 : 0;
        $data['created_by'] = $this->auth->id();
        $data['source'] = 'manual';

        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $col): string => ':' . $col, $columns);
        $sql = 'INSERT INTO parts (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->bindings($data));
        } catch (\PDOException $e) {
            error_log('Part store: ' . $e->getMessage());
            $errors = ['_form' => 'Não foi possível salvar a peça. Verifique os dados.'];
            return $this->renderForm(null, $data, $errors, 422);
        }

        $id = (int) $this->pdo->lastInsertId();
        if ((int) ($data['stock'] ?? 0) > 0) {
            $this->recordMovement($id, (int) $data['stock'], (int) $data['stock'], 'Estoque inicial', $data['cost_price'] ?? null);
        }

        record_audit($this->pdo, $this->auth->id(), 'parts', $id, 'create', $data);
        $this->session->flash('success', 'Peça cadastrada com sucesso.');

        return redirect('/parts/' . $id);
    }

    public function show(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $part = $this->find((int) $id);
        if (!$part) {
            $this->session->flash('error', 'Peça não encontrada.');
            return redirect('/parts');
        }

        return $this->render('mechanic::parts/show', [
            'title' => $part['name'],
            'resource' => $resource,
            'part' => $this->decorateRow($part),
            'offers' => $this->offers((int) $part['cdp_sku']),
            'movements' => $this->movements((int) $id),
            'pageStyles' => [],
            'pageScripts' => [],
        ]);
    }

    public function edit(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $record = $this->find((int) $id);
        if (!$record) {
            $this->session->flash('error', 'Peça não encontrada.');
            return redirect('/parts');
        }

        return $this->renderForm($record, $record, []);
    }

    public function update(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path())) {
            return $response;
        }

        $record = $this->find((int) $id);
        if (!$record) {
            $this->session->flash('error', 'Peça não encontrada.');
            return redirect('/parts');
        }

        [$data, $errors] = $this->validateProduct($request->getParsedBody() ?? [], (int) $id);
        if ($errors) {
            return $this->renderForm($record, array_merge($record, $data), $errors, 422);
        }

        $data['is_active'] = ($data['status'] ?? 'published') === 'published' ? 1 : 0;

        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = $column . ' = :' . $column;
        }
        $sql = 'UPDATE parts SET ' . implode(', ', $sets) . ' WHERE id = :id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->bindings($data) + [':id' => (int) $id]);
        } catch (\PDOException $e) {
            error_log('Part update: ' . $e->getMessage());
            $errors = ['_form' => 'Não foi possível atualizar a peça.'];
            return $this->renderForm($record, array_merge($record, $data), $errors, 422);
        }

        $previousStock = (int) $record['stock'];
        $newStock = (int) $data['stock'];
        if ($newStock !== $previousStock) {
            $this->recordMovement((int) $id, $newStock - $previousStock, $newStock, 'Ajuste manual', $data['cost_price'] ?? null);
        }

        record_audit($this->pdo, $this->auth->id(), 'parts', (int) $id, 'update', $data);
        $this->session->flash('success', 'Peça atualizada com sucesso.');

        return redirect('/parts/' . (int) $id);
    }

    public function destroy(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, $resource->path())) {
            return $response;
        }

        $this->pdo->prepare('DELETE FROM parts WHERE id = :id')->execute([':id' => (int) $id]);
        record_audit($this->pdo, $this->auth->id(), 'parts', (int) $id, 'delete');
        $this->session->flash('success', 'Peça excluída com sucesso.');

        return redirect('/parts');
    }

    public function bulkDelete(ServerRequestInterface $request)
    {
        if ($response = $this->guardRoles($this->resource()->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, '/parts')) {
            return $response;
        }

        $body = $request->getParsedBody() ?? [];
        $ids = $body['ids'] ?? [];
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : explode(',', $ids);
        }
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));
        if (!$ids) {
            return json_response(['deleted' => 0]);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare('DELETE FROM parts WHERE id IN (' . $placeholders . ')');
        $stmt->execute($ids);

        foreach ($ids as $id) {
            record_audit($this->pdo, $this->auth->id(), 'parts', $id, 'delete');
        }

        return json_response(['deleted' => $stmt->rowCount()]);
    }

    /** Reimporta/atualiza as peças a partir do catálogo Canal da Peça. */
    public function sync(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, '/parts')) {
            return $response;
        }

        try {
            $catalog = new PartsCatalog($this->pdo);
            $result = $catalog->syncFromCatalog();
        } catch (\Throwable $e) {
            error_log('Parts sync: ' . $e->getMessage());
            $this->session->flash('error', 'Não foi possível sincronizar o catálogo.');
            return redirect('/parts');
        }

        $this->session->flash('success', sprintf(
            'Catálogo sincronizado: %d peça(s) atualizada(s), %d nova(s), %d classificada(s).',
            $result['updated'],
            $result['inserted'],
            $result['classified']
        ));

        return redirect('/parts');
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $row = $this->pdo->query(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'published') AS published,
                    SUM(stock > 0 AND stock <= min_stock) AS low_stock,
                    SUM(source = 'canaldapeca') AS imported,
                    COALESCE(SUM(stock * price), 0) AS stock_value
               FROM parts"
        )->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
            'low_stock' => (int) ($row['low_stock'] ?? 0),
            'imported' => (int) ($row['imported'] ?? 0),
            'stock_value' => (float) ($row['stock_value'] ?? 0),
        ];
    }

    /** @return array<int, array{id:int, name:string}> */
    private function categoryOptions(): array
    {
        return $this->pdo->query(
            'SELECT id, name FROM part_categories WHERE is_active = 1 ORDER BY name'
        )->fetchAll() ?: [];
    }

    private function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
               FROM parts p
               LEFT JOIN part_categories c ON c.id = p.category_id
              WHERE p.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    private function offers(int $cdpSku): array
    {
        if ($cdpSku <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM cdp_offers WHERE sku_id = :sku ORDER BY price ASC LIMIT 50'
        );
        $stmt->execute([':sku' => $cdpSku]);

        return $stmt->fetchAll() ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    private function movements(int $partId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM part_stock_movements WHERE part_id = :id ORDER BY id DESC LIMIT 50'
        );
        $stmt->execute([':id' => $partId]);

        return $stmt->fetchAll() ?: [];
    }

    private function recordMovement(int $partId, int $delta, int $balance, string $reason, mixed $unitCost = null): void
    {
        if ($delta === 0) {
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO part_stock_movements (part_id, type, quantity, balance_after, unit_cost, reason, user_id)
             VALUES (:part, :type, :qty, :balance, :cost, :reason, :user)'
        )->execute([
            ':part' => $partId,
            ':type' => $delta > 0 ? 'in' : 'out',
            ':qty' => $delta,
            ':balance' => $balance,
            ':cost' => $unitCost !== null && $unitCost !== '' ? (float) $unitCost : null,
            ':reason' => mb_substr($reason, 0, 200),
            ':user' => $this->auth->id(),
        ]);
    }

    /**
     * Valida e normaliza os campos do produto.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateProduct(array $body, ?int $ignoreId): array
    {
        $errors = [];
        $data = [];

        $text = static function (string $key, int $max) use ($body): string {
            return mb_substr(clean($body[$key] ?? ''), 0, $max);
        };

        $name = $text('name', 200);
        if ($name === '') {
            $errors['name'] = 'Informe o nome da peça.';
        }
        $data['name'] = $name;
        $data['brand'] = $text('brand', 120) ?: null;
        $data['part_code'] = $text('part_code', 80) ?: null;
        $data['sku'] = $text('sku', 80) ?: null;
        $data['barcode'] = $text('barcode', 80) ?: null;
        $data['description'] = clean($body['description'] ?? '') ?: null;
        $data['image'] = mb_substr(clean($body['image'] ?? ''), 0, 500) ?: null;
        $data['tags'] = $text('tags', 255) ?: null;
        $data['applications'] = clean($body['applications'] ?? '') ?: null;
        $data['notes'] = clean($body['notes'] ?? '') ?: null;

        $categoryId = (int) ($body['category_id'] ?? 0);
        $data['category_id'] = $categoryId > 0 ? $categoryId : null;

        $status = (string) ($body['status'] ?? 'published');
        if (!isset(self::STATUSES[$status])) {
            $status = 'published';
        }
        $data['status'] = $status;

        $data['price'] = $this->money($body['price'] ?? null);
        $data['cost_price'] = $this->money($body['cost_price'] ?? null);
        if ($data['price'] !== null && $data['price'] < 0) {
            $errors['price'] = 'O preço não pode ser negativo.';
        }
        if ($data['cost_price'] !== null && $data['cost_price'] < 0) {
            $errors['cost_price'] = 'O custo não pode ser negativo.';
        }

        $data['stock'] = max(0, (int) ($body['stock'] ?? 0));
        $data['min_stock'] = max(0, (int) ($body['min_stock'] ?? 0));
        $data['location'] = $text('location', 80) ?: null;
        $data['supplier'] = $text('supplier', 160) ?: null;
        $data['supplier_url'] = mb_substr(clean($body['supplier_url'] ?? ''), 0, 500) ?: null;
        $data['product_url'] = mb_substr(clean($body['product_url'] ?? ''), 0, 500) ?: null;
        $data['universal'] = !empty($body['universal']) ? 1 : 0;
        $data['by_request'] = !empty($body['by_request']) ? 1 : 0;
        $data['by_budget'] = !empty($body['by_budget']) ? 1 : 0;
        $data['weight'] = $this->money($body['weight'] ?? null);
        $data['warranty_months'] = ($body['warranty_months'] ?? '') !== ''
            ? max(0, (int) $body['warranty_months'])
            : null;

        $oemLines = preg_split('/[\r\n,;]+/', (string) ($body['oem_codes'] ?? '')) ?: [];
        $oem = array_values(array_filter(array_map('trim', $oemLines), static fn (string $v): bool => $v !== ''));
        $data['oem_codes'] = $oem ? json_encode($oem, JSON_UNESCAPED_UNICODE) : null;

        // Mantém o texto legado de categoria coerente com a categoria escolhida.
        $data['category'] = null;
        if ($data['category_id'] !== null) {
            $stmt = $this->pdo->prepare('SELECT name FROM part_categories WHERE id = :id');
            $stmt->execute([':id' => $data['category_id']]);
            $categoryName = $stmt->fetchColumn();
            if ($categoryName) {
                $data['category'] = mb_substr((string) $categoryName, 0, 120);
            } else {
                $errors['category_id'] = 'Categoria inválida.';
            }
        }

        return [$data, $errors];
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', 'R$'], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /** @return array<string, mixed> */
    private function bindings(array $data): array
    {
        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings[':' . $key] = $value;
        }
        return $bindings;
    }

    private function renderForm(?array $record, array $old, array $errors, int $status = 200)
    {
        $resource = $this->resource();

        $part = $record ? array_merge($record, $old) : $old;
        $partId = (int) ($record['id'] ?? 0);

        $offers = $partId > 0 ? $this->offers((int) ($record['cdp_sku'] ?? 0)) : [];
        $movements = $partId > 0 ? $this->movements($partId) : [];

        if (!empty($part['oem_codes']) && is_string($part['oem_codes'])) {
            $decoded = json_decode($part['oem_codes'], true);
            $part['oem_codes_text'] = is_array($decoded) ? implode(PHP_EOL, $decoded) : (string) $part['oem_codes'];
        }

        $html = $this->render('mechanic::parts/form', [
            'title' => $record ? 'Editar peça' : 'Nova peça',
            'resource' => $resource,
            'record' => $record,
            'old' => $part,
            'errors' => $errors,
            'categories' => $this->categoryOptions(),
            'offers' => $offers,
            'movements' => $movements,
            'statuses' => self::STATUSES,
            'pageStyles' => ['/assets/vendor/libs/select2/select2.css'],
            'pageScripts' => ['/js/part-product-form.js'],
        ]);

        return new Response($status, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    /** Campos calculados usados pela listagem e pela tela de detalhes. */
    private function decorateRow(array $row): array
    {
        $stock = (int) ($row['stock'] ?? 0);
        $minStock = (int) ($row['min_stock'] ?? 0);

        $row['subtitle'] = trim(implode(' · ', array_filter([
            (string) ($row['brand'] ?? ''),
            (string) ($row['part_code'] ?? ''),
        ])));

        $row['sku_display'] = (string) ($row['sku'] ?? '') !== ''
            ? (string) $row['sku']
            : ((string) ($row['cdp_sku'] ?? '') !== '' ? (string) $row['cdp_sku'] : (string) ($row['part_code'] ?? ''));

        $row['category_name'] = (string) ($row['category_name'] ?? '') !== ''
            ? (string) $row['category_name']
            : (string) (explode(' > ', (string) ($row['category'] ?? ''))[0] ?? '');

        $row['category_icon'] = (string) ($row['category_icon'] ?? '') !== '' ? (string) $row['category_icon'] : 'package';
        $row['category_color'] = (string) ($row['category_color'] ?? '') !== '' ? (string) $row['category_color'] : 'secondary';

        if ($stock <= 0) {
            $row['stock_label'] = 'Esgotado';
            $row['stock_class'] = 'bg-label-danger';
            $row['stock_state'] = 'out';
        } elseif ($stock <= $minStock) {
            $row['stock_label'] = 'Estoque baixo';
            $row['stock_class'] = 'bg-label-warning';
            $row['stock_state'] = 'low';
        } else {
            $row['stock_label'] = 'Em estoque';
            $row['stock_class'] = 'bg-label-success';
            $row['stock_state'] = 'in';
        }

        $row['status_label'] = self::STATUSES[$row['status'] ?? ''] ?? (string) ($row['status'] ?? '');
        $row['status_class'] = match ($row['status'] ?? '') {
            'published' => 'bg-label-success',
            'draft' => 'bg-label-warning',
            default => 'bg-label-secondary',
        };

        $price = $row['price'] !== null ? (float) $row['price'] : 0.0;
        $cost = $row['cost_price'] !== null ? (float) $row['cost_price'] : 0.0;
        $row['margin'] = $price > 0 ? (($price - $cost) / $price) * 100 : 0.0;
        $row['has_image'] = (string) ($row['image'] ?? '') !== '';

        return $row;
    }
}
