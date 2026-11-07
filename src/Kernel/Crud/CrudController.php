<?php
// =============================================
// CRUD CONTROLLER — implementação genérica de
// listar/criar/editar/excluir a partir de um Resource.
// Um módulo só precisa estender e retornar o Resource.
// =============================================

declare(strict_types=1);

namespace Kernel\Crud;

use Kernel\Auth\Auth;
use Kernel\Database\Repository;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

abstract class CrudController extends Controller
{
    protected PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    /** O módulo define qual Resource este controller gerencia. */
    abstract protected function resource(): Resource;

    public function index(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        return $this->render($resource->indexView(), [
            'title' => $resource->labels()['plural'],
            'resource' => $resource,
            'datatable' => [
                'url' => $resource->path() . '/datatable',
                'basePath' => $resource->path(),
                'csrf' => $this->session->csrfToken(),
                'title' => $resource->labels()['plural'],
                'subtitle' => $resource->datatableSubtitle(),
                'cardTitle' => $resource->labels()['plural'],
                'newLabel' => $resource->datatableNewLabel(),
                'columns' => $resource->datatableColumns(),
                'order' => $resource->datatableOrder(),
                'filters' => $resource->datatableFilters(),
                'labels' => $resource->labels(),
            ],
            'pageStyles' => [
                '/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css',
                '/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css',
                '/assets/vendor/libs/datatables-select-bs5/select.bootstrap5.css',
                '/assets/vendor/libs/datatables-fixedheader-bs5/fixedheader.bootstrap5.css',
            ],
            'pageScripts' => [
                '/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
                '/js/crud-datatable.js',
            ],
        ]);
    }

    /** JSON da listagem (DataTables client-side: todas as linhas). */
    public function datatable(ServerRequestInterface $request)
    {
        $resource = $this->resource();

        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }
        if ($resource->roles() && !$this->auth->hasRole($resource->roles())) {
            return json_response(['error' => 'forbidden'], 403);
        }

        $data = [];
        foreach ($resource->datatableRows($this->pdo) as $row) {
            $row = $resource->datatableRow($row);
            // Nunca expõe credenciais no JSON da listagem.
            unset($row['password'], $row['remember_token']);
            $data[] = $row;
        }

        return json_response(['data' => $data]);
    }

    public function create(ServerRequestInterface $request)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        return $this->render($resource->formView(), $this->formData($resource, null, [], []));
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

        [$data, $errors] = $this->validate($resource, $request);

        if ($errors) {
            return $this->render($resource->formView(), $this->formData($resource, null, $data, $errors));
        }

        $data = $resource->prepareData($data, null, $this->pdo);

        try {
            $newId = $this->repository($resource)->create($data);
        } catch (\PDOException $e) {
            return $this->render($resource->formView(), $this->formData($resource, null, $data, ['_form' => $this->dbErrorMessage($e)]));
        }

        record_audit($this->pdo, $this->auth->id(), $resource->key(), $newId, 'create', $data);
        $this->session->flash('success', $resource->labels()['singular'] . ' cadastrado(a) com sucesso.');
        return redirect($resource->path());
    }

    public function edit(ServerRequestInterface $request, $id)
    {
        $resource = $this->resource();
        if ($response = $this->guardRoles($resource->roles())) {
            return $response;
        }

        $record = $this->repository($resource)->find((int) $id);
        if (!$record) {
            $this->session->flash('error', 'Registro não encontrado.');
            return redirect($resource->path());
        }

        return $this->render($resource->formView(), $this->formData($resource, $record, $record, []));
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

        $record = $this->repository($resource)->find((int) $id);
        if (!$record) {
            $this->session->flash('error', 'Registro não encontrado.');
            return redirect($resource->path());
        }

        [$data, $errors] = $this->validate($resource, $request, (int) $id);

        if ($errors) {
            return $this->render($resource->formView(), $this->formData($resource, array_merge($record, $data), $data, $errors));
        }

        $data = $resource->prepareData($data, (int) $id, $this->pdo);

        try {
            $this->repository($resource)->update((int) $id, $data);
        } catch (\PDOException $e) {
            return $this->render($resource->formView(), $this->formData($resource, array_merge($record, $data), $data, ['_form' => $this->dbErrorMessage($e)]));
        }

        record_audit($this->pdo, $this->auth->id(), $resource->key(), (int) $id, 'update', $data);
        $this->session->flash('success', $resource->labels()['singular'] . ' atualizado(a) com sucesso.');
        return redirect($resource->path());
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

        $this->repository($resource)->delete((int) $id);
        record_audit($this->pdo, $this->auth->id(), $resource->key(), (int) $id, 'delete');
        $this->session->flash('success', $resource->labels()['singular'] . ' excluído(a) com sucesso.');
        return redirect($resource->path());
    }

    protected function repository(Resource $resource): Repository
    {
        return new Repository(
            $this->pdo,
            $resource->table(),
            $resource->fillable(),
            $resource->searchable()
        );
    }

    /** @return array<string, mixed> Dados comuns do formulário. */
    private function formData(Resource $resource, ?array $record, array $old, array $errors): array
    {
        return [
            'title' => ($record ? 'Editar ' : 'Novo(a) ') . $resource->labels()['singular'],
            'resource' => $resource,
            'record' => $record,
            'old' => $old,
            'errors' => $errors,
            // Usa os dados submetidos/atuais para carregar selects dependentes
            // (ex.: modelos/anos FIPE), preservando a cascata em caso de erro.
            'selectOptions' => $resource->selectOptions($this->pdo, $old ?: ($record ?? [])),
            'pageStyles' => $resource->formStyles(),
            'pageScripts' => $resource->formScripts(),
        ];
    }

    /**
     * Valida e normaliza os dados conforme os campos do Resource.
     *
     * @return array{0: array, 1: array} [data, errors]
     */
    private function validate(Resource $resource, ServerRequestInterface $request, ?int $ignoreId = null): array
    {
        $body = $request->getParsedBody() ?? [];
        $data = [];
        $errors = [];
        $repo = $this->repository($resource);

        foreach ($resource->fields() as $field) {
            $name = $field['name'];
            $type = $field['type'] ?? 'text';

            if ($type === 'boolean') {
                $data[$name] = isset($body[$name]) ? 1 : 0;
                continue;
            }

            $value = clean($body[$name] ?? '');

            $isRequired = !empty($field['required'])
                || (!empty($field['required_on_create']) && $ignoreId === null);

            if ($isRequired && $value === '') {
                $errors[$name] = 'Campo obrigatório.';
            }

            if ($value !== '' && !empty($field['validate'])) {
                if ($field['validate'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$name] = 'Informe um e-mail válido.';
                }
                if ($field['validate'] === 'cpf' && !isValidCpf($value)) {
                    $errors[$name] = 'CPF inválido.';
                }
                if ($field['validate'] === 'plate' && !isValidPlate($value)) {
                    $errors[$name] = 'Placa inválida. Use ABC-1234 ou ABC1D23.';
                }
            }

            // Unicidade (ex.: CPF/placa).
            if ($value !== '' && !empty($field['unique']) && !isset($errors[$name])) {
                $qb = $repo->query()->where($name, '=', $value);
                if ($ignoreId !== null) {
                    $qb->where('id', '!=', $ignoreId);
                }
                if ($qb->first()) {
                    $errors[$name] = 'Já existe um registro com este valor.';
                }
            }

            if ($type === 'email') {
                $value = strtolower($value);
            }

            // Campos numéricos/select/data vazios viram NULL (evita erro em colunas tipadas).
            if (in_array($type, ['number', 'select', 'date'], true) && $value === '') {
                $data[$name] = null;
                continue;
            }

            $data[$name] = $value;
        }

        return [$data, $errors];
    }

    /** Mensagem amigável para violações de banco (ex.: duplicidade/fk). */
    private function dbErrorMessage(\PDOException $e): string
    {
        if ((string) $e->getCode() === '23000') {
            return 'Não foi possível salvar: valor duplicado ou referência inválida (verifique CPF/placa/cliente).';
        }
        error_log('CRUD DB error: ' . $e->getMessage());
        return 'Não foi possível salvar os dados.';
    }
}
