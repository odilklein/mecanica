<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Crud\CrudController;
use Kernel\Crud\Resource;
use Modules\Core\Resources\UserResource;
use Psr\Http\Message\ServerRequestInterface;

class UserController extends CrudController
{
    protected function resource(): Resource
    {
        return new UserResource();
    }

    /** Listagem no padrão DataTables "Fixed Header" do Vuexy. */
    public function index(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        return $this->render('core::users/index', [
            'title' => $resource->labels()['plural'],
            'resource' => $resource,
            'pageStyles' => [
                '/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css',
                '/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css',
                '/assets/vendor/libs/datatables-select-bs5/select.bootstrap5.css',
                '/assets/vendor/libs/datatables-fixedheader-bs5/fixedheader.bootstrap5.css',
            ],
            'pageScripts' => [
                '/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
                '/js/users-datatable.js',
            ],
        ]);
    }

    /**
     * Endpoint server-side do DataTables (protocolo draw/start/length/order/search).
     */
    public function datatable(ServerRequestInterface $request)
    {
        $resource = $this->resource();

        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }
        if ($resource->roles() && !$this->auth->hasRole($resource->roles())) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $query = $request->getQueryParams();

        $draw = (int) ($query['draw'] ?? 1);
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = (int) ($query['length'] ?? 10);
        if ($length <= 0) {
            $length = 100;
        }
        $length = min($length, 500);

        $searchParam = $query['search'] ?? [];
        $search = clean(is_array($searchParam) ? ($searchParam['value'] ?? '') : '');

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE (name LIKE :s1 OR email LIKE :s2 OR job_title LIKE :s3'
                . ' OR cpf LIKE :s4 OR phone LIKE :s5)';
            $like = '%' . $search . '%';
            $params = [':s1' => $like, ':s2' => $like, ':s3' => $like, ':s4' => $like, ':s5' => $like];
        }

        $recordsTotal = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        $filtered = $this->pdo->prepare('SELECT COUNT(*) FROM users ' . $where);
        $filtered->execute($params);
        $recordsFiltered = (int) $filtered->fetchColumn();

        // Índices das colunas da tabela (ver a view) => colunas do banco.
        $orderMap = [
            2 => 'id',
            3 => 'name',
            4 => 'email',
            5 => 'hire_date',
            6 => 'salary',
            7 => 'is_active',
        ];
        $order = $query['order'] ?? [];
        $firstOrder = is_array($order) ? ($order[0] ?? []) : [];
        $orderColumn = (int) (is_array($firstOrder) ? ($firstOrder['column'] ?? 2) : 2);
        $orderDir = strtolower((string) (is_array($firstOrder) ? ($firstOrder['dir'] ?? 'desc') : 'desc')) === 'asc'
            ? 'ASC'
            : 'DESC';
        $orderBy = $orderMap[$orderColumn] ?? 'id';

        $sql = 'SELECT id, name, email, role, cpf, phone, salary, hire_date, job_title, is_active
                  FROM users ' . $where . '
                 ORDER BY ' . $orderBy . ' ' . $orderDir . '
                 LIMIT ' . $length . ' OFFSET ' . $start;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $data = [];
        foreach ($stmt->fetchAll() as $row) {
            $data[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'post' => $row['job_title'] ?: roleLabel($row['role']),
                'role' => $row['role'],
                'role_label' => roleLabel($row['role']),
                'cpf' => $row['cpf'],
                'phone' => $row['phone'],
                'hire_date' => $row['hire_date']
                    ? date('d/m/Y', strtotime((string) $row['hire_date']))
                    : '—',
                'salary' => $row['salary'] !== null
                    ? 'R$ ' . number_format((float) $row['salary'], 2, ',', '.')
                    : '—',
                'status' => (int) $row['is_active'],
            ];
        }

        return json_response([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }
}
