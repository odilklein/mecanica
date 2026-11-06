<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

class SettingsController extends Controller
{
    private PDO $pdo;

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu, PDO $pdo)
    {
        parent::__construct($auth, $session, $view, $menu);
        $this->pdo = $pdo;
    }

    public function index(ServerRequestInterface $request)
    {
        if ($response = $this->guardRoles(['admin'])) {
            return $response;
        }

        return $this->render('core::settings/index', ['title' => 'Configurações']);
    }

    public function update(ServerRequestInterface $request)
    {
        if ($response = $this->guardRoles(['admin'])) {
            return $response;
        }
        if ($response = $this->guardCsrf($request, '/settings')) {
            return $response;
        }

        $body = $request->getParsedBody();
        $fields = ['theme_color', 'seo_title', 'seo_description', 'seo_keywords'];

        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        foreach ($fields as $key) {
            $value = clean($body[$key] ?? '');
            if ($key === 'theme_color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                continue;
            }
            $stmt->execute([':k' => $key, ':v' => $value]);
        }

        record_audit($this->pdo, $this->auth->id(), 'settings', null, 'update');
        $this->session->flash('success', 'Configurações salvas com sucesso.');
        return redirect('/settings');
    }
}
