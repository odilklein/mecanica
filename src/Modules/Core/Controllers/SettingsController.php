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
        $fields = [
            'theme_color', 'seo_title', 'seo_description', 'seo_keywords',
            'company_name', 'company_document', 'company_phone', 'company_email',
            'company_address', 'company_city', 'company_state',
            'budget_validity_days', 'budget_terms',
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        foreach ($fields as $key) {
            $value = clean($body[$key] ?? '');

            if ($key === 'theme_color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                continue;
            }
            if (in_array($key, ['company_name', 'seo_title'], true) && $value === '') {
                $value = 'Mecânica';
            }
            if ($key === 'company_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->session->flash('error', 'Informe um e-mail válido para a oficina.');
                return redirect('/settings');
            }
            if ($key === 'company_state') {
                $value = strtoupper(mb_substr($value, 0, 2));
            }
            if ($key === 'budget_validity_days') {
                $days = (int) $value;
                $value = (string) ($days >= 1 && $days <= 365 ? $days : 15);
            }

            $stmt->execute([':k' => $key, ':v' => $value]);
        }

        record_audit($this->pdo, $this->auth->id(), 'settings', null, 'update');
        $this->session->flash('success', 'Configurações salvas com sucesso.');
        return redirect('/settings');
    }
}
