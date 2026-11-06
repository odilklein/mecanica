<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Session;
use Kernel\View\ViewFactory;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController
{
    private Auth $auth;
    private Session $session;
    private ViewFactory $view;

    public function __construct(Auth $auth, Session $session, ViewFactory $view)
    {
        $this->auth = $auth;
        $this->session = $session;
        $this->view = $view;
    }

    public function index(ServerRequestInterface $request)
    {
        if (!$this->auth->check()) {
            return redirect('/login');
        }

        return $this->view->render('core::dashboard', [
            'title' => 'Dashboard',
            'user' => $this->auth->user(),
            'csrf' => $this->session->csrfToken(),
        ]);
    }
}
