<?php
declare(strict_types=1);

namespace Modules\Mechanic\Controllers;

use Modules\Mechanic\Services\FipeService;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

class FipeController
{
    private FipeService $fipe;

    public function __construct(PDO $pdo)
    {
        $this->fipe = new FipeService($pdo);
    }

    public function models(ServerRequestInterface $request)
    {
        $brand = (string) ($request->getQueryParams()['brand'] ?? '');
        return json_response($this->fipe->modelsForBrand($brand));
    }

    public function years(ServerRequestInterface $request)
    {
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
