<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Module\Scenario\Http\Requests\ScenarioTypeRequest;
use Module\Scenario\Services\Definition\ScenarioNodeCatalog;

final class ScenarioNodeTypesController extends Controller
{
    public function __construct(private readonly ScenarioNodeCatalog $catalog)
    {
    }

    public function __invoke(ScenarioTypeRequest $request): ApiResponse
    {
        return new ApiResponse($this->catalog->for($request->scenarioType()));
    }
}
