<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Http\Controller;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController extends Controller
{
    public function index(ServerRequestInterface $request)
    {
        if ($response = $this->guardAuth()) {
            return $response;
        }

        return $this->render('core::dashboard', ['title' => 'Painel']);
    }
}
