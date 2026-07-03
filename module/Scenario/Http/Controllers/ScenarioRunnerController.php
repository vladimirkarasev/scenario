<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioStartData;
use Module\Scenario\Http\Requests\ContinueScenarioRunRequest;
use Module\Scenario\Http\Requests\StartScenarioRunnerRequest;
use Module\Scenario\Services\ScenarioRunsService;

final class ScenarioRunnerController extends Controller
{
    public function __construct(
        private readonly ScenarioRunsService $runs,
    ) {
    }

    public function start(StartScenarioRunnerRequest $request): ApiResponse
    {
        return new ApiResponse([
            'id' => $this->runs->start(ScenarioStartData::fromRequest($request)),
        ], 201);
    }

    public function jump(ContinueScenarioRunRequest $request, string $runId): ApiResponse
    {
        return new ApiResponse($this->runs->continue(
            $runId,
            ScenarioRunContinueData::fromRequest($request),
            $request->user()?->id,
        ));
    }
}
