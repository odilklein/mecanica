<?php
// =============================================
// CATEGORIAS DE PEÇAS — listagem e offcanvas
// no padrão e-commerce do sistema.
// =============================================

declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

class PartCategoryController extends Controller
{
    private const ICONS = [
        'package', 'filter', 'engine', 'circle-dot', 'arrows-vertical', 'bolt',
        'temperature', 'settings', 'circle', 'car', 'bulb', 'star', 'droplet',
        'tools', 'packages', 'tool',
    ];

    private const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark'];

    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    private function roles(): array
    {
        return ['admin', 'secretary', 'lead_mechanic'];
    }

    public function index(ServerRequestInterface $request)
    {
        if ($response = $this->guardRoles($this->roles())) {
            return $response;
        }

        $stats = $this->pdo->query(
            "SELECT (SELECT COUNT(*) FROM part_categories) AS total,
                    (SELECT COUNT(*) FROM part_categories WHERE is_active = 1) AS active,
                    (SELECT COUNT(*) FROM parts WHERE category_id IS NOT NULL) AS categorized,
                    (SELECT COUNT(*) FROM parts WHERE category_id IS NULL) AS uncategorized"
        )->fetch() ?: [];

        $categories = $this->pdo->query(
            "SELECT c.*,
                    COUNT(p.id) AS total_products,
                    COALESCE(SUM(p.stock * p.price), 0) AS total_value,
                    COALESCE(SUM(p.price), 0) AS reference_value,
                    COALESCE(SUM(p.stock > 0 AND p.stock <= p.min_stock), 0) AS low_stock
               FROM part_categories c
               LEFT JOIN parts p ON p.category_id = c.id
              GROUP BY c.id
              ORDER BY c.name ASC"
        )->fetchAll() ?: [];

        return $this->render('mechanic::parts/categories', [
            'title' => 'Categorias de peças',
            'stats' => [
                'total' => (int) ($stats['total'] ?? 0),
                'active' => (int) ($stats['active'] ?? 0),
                'categorized' => (int) ($stats['categorized'] ?? 0),
                'uncategorized' => (int) ($stats['uncategorized'] ?? 0),
            ],
            'categories' => $categories,
            'icons' => self::ICONS,
            'colors' => self::COLORS,
            'pageStyles' => [
                '/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css',
                '/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css',
                '/assets/vendor/libs/select2/select2.css',
            ],
            'pageScripts' => [
                '/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
                '/js/part-categories.js',
            ],
        ]);
    }

    public function store(ServerRequestInterface $request)
    {
        if ($response = $this->guardRoles($this->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, '/parts/categories')) {
            return $response;
        }

        [$data, $error] = $this->validate($request->getParsedBody() ?? [], null);
        if ($error !== null) {
            $this->session->flash('error', $error);
            return redirect('/parts/categories');
        }

        $this->pdo->prepare(
            'INSERT INTO part_categories (name, slug, description, icon, color, is_active)
             VALUES (:name, :slug, :description, :icon, :color, :active)'
        )->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':description' => $data['description'],
            ':icon' => $data['icon'],
            ':color' => $data['color'],
            ':active' => $data['is_active'],
        ]);

        $id = (int) $this->pdo->lastInsertId();
        record_audit($this->pdo, $this->auth->id(), 'part_categories', $id, 'create', $data);
        $this->session->flash('success', 'Categoria criada com sucesso.');

        return redirect('/parts/categories');
    }

    public function update(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guardRoles($this->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, '/parts/categories')) {
            return $response;
        }

        [$data, $error] = $this->validate($request->getParsedBody() ?? [], (int) $id);
        if ($error !== null) {
            $this->session->flash('error', $error);
            return redirect('/parts/categories');
        }

        $this->pdo->prepare(
            'UPDATE part_categories
                SET name = :name, slug = :slug, description = :description,
                    icon = :icon, color = :color, is_active = :active
              WHERE id = :id'
        )->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':description' => $data['description'],
            ':icon' => $data['icon'],
            ':color' => $data['color'],
            ':active' => $data['is_active'],
            ':id' => (int) $id,
        ]);

        record_audit($this->pdo, $this->auth->id(), 'part_categories', (int) $id, 'update', $data);
        $this->session->flash('success', 'Categoria atualizada com sucesso.');

        return redirect('/parts/categories');
    }

    public function destroy(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guardRoles($this->roles())) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, '/parts/categories')) {
            return $response;
        }

        $this->pdo->prepare('DELETE FROM part_categories WHERE id = :id')->execute([':id' => (int) $id]);
        record_audit($this->pdo, $this->auth->id(), 'part_categories', (int) $id, 'delete');
        $this->session->flash('success', 'Categoria excluída. As peças ficaram sem categoria.');

        return redirect('/parts/categories');
    }

    /**
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function validate(array $body, ?int $ignoreId): array
    {
        $name = mb_substr(clean($body['name'] ?? ''), 0, 120);
        if ($name === '') {
            return [[], 'Informe o nome da categoria.'];
        }

        $slug = mb_substr(clean($body['slug'] ?? ''), 0, 120);
        if ($slug === '') {
            $slug = $this->slugify($name);
        }
        if ($slug === '') {
            return [[], 'Não foi possível gerar o slug da categoria.'];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM part_categories WHERE slug = :slug AND id != :id LIMIT 1');
        $stmt->execute([':slug' => $slug, ':id' => $ignoreId ?? 0]);
        if ($stmt->fetchColumn()) {
            return [[], 'Já existe uma categoria com este slug.'];
        }

        $icon = (string) ($body['icon'] ?? 'package');
        if (!in_array($icon, self::ICONS, true)) {
            $icon = 'package';
        }

        $color = (string) ($body['color'] ?? 'primary');
        if (!in_array($color, self::COLORS, true)) {
            $color = 'primary';
        }

        return [[
            'name' => $name,
            'slug' => $slug,
            'description' => clean($body['description'] ?? '') ?: null,
            'icon' => $icon,
            'color' => $color,
            'is_active' => !empty($body['is_active']) ? 1 : 0,
        ], null];
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
