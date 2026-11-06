<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Session;
use Kernel\View\ViewFactory;
use Modules\Core\Repositories\ClientRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ClientController
{
    private Auth $auth;
    private Session $session;
    private ViewFactory $view;
    private ClientRepository $clients;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, ClientRepository $clients)
    {
        $this->auth = $auth;
        $this->session = $session;
        $this->view = $view;
        $this->clients = $clients;
    }

    public function index(ServerRequestInterface $request)
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $query = $request->getQueryParams();
        $page = (int) ($query['page'] ?? 1);
        $search = clean($query['search'] ?? '');

        $data = $this->clients->paginate($page, 15, $search);

        return $this->view->render('core::clients/index', array_merge($data, [
            'title' => 'Clientes',
            'user' => $this->auth->user(),
            'csrf' => $this->session->csrfToken(),
            'search' => $search,
        ]));
    }

    public function create(ServerRequestInterface $request)
    {
        if ($response = $this->guard()) {
            return $response;
        }
        return $this->view->render('core::clients/form', $this->formData());
    }

    public function store(ServerRequestInterface $request)
    {
        if ($response = $this->guard()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request)) {
            return $response;
        }

        [$data, $errors] = $this->validate($request);

        if ($errors) {
            return $this->view->render('core::clients/form', $this->formData(null, $errors, $data));
        }

        $this->clients->create($data);
        $this->session->flash('success', 'Cliente cadastrado com sucesso.');
        return redirect('/clients');
    }

    public function edit(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $client = $this->clients->find((int) $id);
        if (!$client) {
            $this->session->flash('error', 'Cliente não encontrado.');
            return redirect('/clients');
        }

        return $this->view->render('core::clients/form', $this->formData($client));
    }

    public function update(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guard()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request)) {
            return $response;
        }

        $client = $this->clients->find((int) $id);
        if (!$client) {
            $this->session->flash('error', 'Cliente não encontrado.');
            return redirect('/clients');
        }

        [$data, $errors] = $this->validate($request);

        if ($errors) {
            return $this->view->render('core::clients/form', $this->formData(array_merge($client, $data), $errors, $data));
        }

        $this->clients->update((int) $id, $data);
        $this->session->flash('success', 'Cliente atualizado com sucesso.');
        return redirect('/clients');
    }

    public function destroy(ServerRequestInterface $request, $id)
    {
        if ($response = $this->guard()) {
            return $response;
        }
        if ($response = $this->guardCsrf($request)) {
            return $response;
        }

        $this->clients->delete((int) $id);
        $this->session->flash('success', 'Cliente excluído com sucesso.');
        return redirect('/clients');
    }

    /** Redireciona para o login se não autenticado. */
    private function guard(): ?ResponseInterface
    {
        if (!$this->auth->check()) {
            return redirect('/login');
        }
        return null;
    }

    private function guardCsrf(ServerRequestInterface $request): ?ResponseInterface
    {
        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            $this->session->flash('error', 'Sessão expirada. Tente novamente.');
            return redirect('/clients');
        }
        return null;
    }

    /** @return array{0: array, 1: array} [data, errors] */
    private function validate(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        $data = [
            'name' => clean($body['name'] ?? ''),
            'cpf' => clean($body['cpf'] ?? ''),
            'phone' => clean($body['phone'] ?? ''),
            'email' => strtolower(clean($body['email'] ?? '')),
            'address' => clean($body['address'] ?? ''),
            'city' => clean($body['city'] ?? ''),
            'notes' => clean($body['notes'] ?? ''),
            'is_active' => isset($body['is_active']) ? 1 : 0,
        ];

        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Informe o nome.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        }
        if ($data['cpf'] !== '' && !isValidCpf($data['cpf'])) {
            $errors['cpf'] = 'CPF inválido.';
        }

        return [$data, $errors];
    }

    /** @return array<string, mixed> */
    private function formData(?array $client = null, array $errors = [], array $old = []): array
    {
        return [
            'title' => $client ? 'Editar cliente' : 'Novo cliente',
            'user' => $this->auth->user(),
            'csrf' => $this->session->csrfToken(),
            'client' => $client,
            'errors' => $errors,
            'old' => $old ?: ($client ?? []),
        ];
    }
}
