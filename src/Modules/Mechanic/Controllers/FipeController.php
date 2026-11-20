<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Kernel\Auth\Auth;
use Modules\Mechanic\Services\FipeService;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

class FipeController
{
    private FipeService $fipe;
    private Auth $auth;

    public function __construct(PDO $pdo, Auth $auth)
    {
        $this->fipe = new FipeService($pdo);
        $this->auth = $auth;
    }

    /** As consultas FIPE disparam HTTP externo: exigem sessão. */
    private function guard()
    {
        return $this->auth->check() ? null : json_response(['error' => 'unauthorized'], 401);
    }

    public function models(ServerRequestInterface $request)
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $brand = (string) ($request->getQueryParams()['brand'] ?? '');
        return json_response($this->fipe->modelsForBrand($brand));
    }

    public function years(ServerRequestInterface $request)
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $query = $request->getQueryParams();
        $modelId = $this->fipe->findModelId(
            (string) ($query['brand'] ?? ''),
            (string) ($query['model'] ?? '')
        );
        if (!$modelId) {
            return json_response([]);
        }
        return json_response($this->fipe->yearsForModel($modelId));
    }

    public function price(ServerRequestInterface $request)
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $query = $request->getQueryParams();
        $modelId = $this->fipe->findModelId(
            (string) ($query['brand'] ?? ''),
            (string) ($query['model'] ?? '')
        );
        if (!$modelId) {
            return json_response(['value' => null]);
        }
        return json_response($this->fipe->priceFor($modelId, (string) ($query['year'] ?? '')) ?? ['value' => null]);
    }
}
