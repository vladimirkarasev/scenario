<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Module\Scenario\Http\Requests\ScenarioTypeRequest;
use Module\Scenario\Services\Variables\System\ScenarioSystemVariableCatalog;

final class ScenarioSystemVariablesController extends Controller
{
    public function __construct(private readonly ScenarioSystemVariableCatalog $catalog)
    {
    }

    public function __invoke(ScenarioTypeRequest $request): ApiResponse
    {
        return new ApiResponse($this->catalog->for($request->scenarioType()));
    }
}
