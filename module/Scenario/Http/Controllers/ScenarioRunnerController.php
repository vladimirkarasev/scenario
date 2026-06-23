<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioStartData;
use Module\Scenario\Http\Requests\ContinueScenarioRunRequest;
use Module\Scenario\Http\Requests\StartScenarioRunnerRequest;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Services\ScenarioPlayerService;

final class ScenarioRunnerController extends Controller
{
    public function __construct(
        private readonly ScenarioPlayerService $scenarioPlayerService,
    ) {}

    public function start(StartScenarioRunnerRequest $request): JsonResponse
    {
        return new JsonResponse([
            'id' => $this->scenarioPlayerService->start(ScenarioStartData::fromRequest($request)),
        ], 201);
    }

    public function jump(ContinueScenarioRunRequest $request, string $runId): JsonResponse
    {
        $run = $this->scenarioPlayerService->continueRun(
            ScenarioRun::query()->findOrFail($runId),
            ScenarioRunContinueData::fromRequest($request),
        );

        return new JsonResponse($this->scenarioPlayerService->payload($run));
    }
}
