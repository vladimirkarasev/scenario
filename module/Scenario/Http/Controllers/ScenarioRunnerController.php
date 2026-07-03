<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioStartData;
use Module\Scenario\Http\Requests\ContinueScenarioRunRequest;
use Module\Scenario\Http\Requests\StartScenarioRunnerRequest;
use Module\Scenario\Repositories\ScenarioRunRepository;
use Module\Scenario\Services\ScenarioPlayerService;

final class ScenarioRunnerController extends Controller
{
    public function __construct(
        private readonly ScenarioPlayerService $scenarioPlayerService,
        private readonly ScenarioRunRepository $runs,
    ) {
    }

    public function start(StartScenarioRunnerRequest $request): ApiResponse
    {
        return new ApiResponse([
            'id' => $this->scenarioPlayerService->start(ScenarioStartData::fromRequest($request)),
        ], 201);
    }

    public function jump(ContinueScenarioRunRequest $request, string $runId): ApiResponse
    {
        $run = $this->scenarioPlayerService->continueRun(
            $this->runs->getById($runId),
            ScenarioRunContinueData::fromRequest($request),
        );

        return new ApiResponse($this->scenarioPlayerService->payload($run));
    }
}
