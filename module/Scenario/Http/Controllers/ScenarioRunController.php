<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\DTO\ScenarioRunIndexData;
use Module\Scenario\DTO\ScenarioRunJumpData;
use Module\Scenario\Http\Requests\ContinueScenarioRunRequest;
use Module\Scenario\Http\Requests\JumpScenarioRunRequest;
use Module\Scenario\Http\Requests\StoreScenarioRunRequest;
use Module\Scenario\Services\Runtime\ScenarioRunsService;

final class ScenarioRunController extends Controller
{
    public function __construct(
        private readonly ScenarioRunsService $service,
    ) {
    }

    public function index(Request $request): ApiResponse
    {
        $result = $this->service->list(ScenarioRunIndexData::fromRequest($request));

        return new ApiResponse(
            $result['runs'],
            meta: [...$result['pagination'], 'stats' => $result['stats']],
        );
    }

    public function users(Request $request): ApiResponse
    {
        $filter = is_array($request->input('filter')) ? $request->array('filter') : [];
        $search = is_string($filter['search'] ?? null) ? trim($filter['search']) : '';
        $rawIds = $filter['ids'] ?? null;
        $ids = array_values(array_map(
            intval(...),
            array_filter(is_array($rawIds) ? $rawIds : [], is_numeric(...)),
        ));

        return new ApiResponse($this->service->lookupUsers($ids, $search));
    }

    public function store(StoreScenarioRunRequest $request): ApiResponse
    {
        return new ApiResponse($this->service->store(ScenarioRunData::fromRequest($request)), 201);
    }

    public function show(string $runId): ApiResponse
    {
        return new ApiResponse($this->service->show($runId));
    }

    public function continue(ContinueScenarioRunRequest $request, string $runId): ApiResponse
    {
        return new ApiResponse($this->service->continue(
            $runId,
            ScenarioRunContinueData::fromRequest($request),
            $request->user()?->id,
        ));
    }

    public function retryAction(Request $request, string $runId): ApiResponse
    {
        return new ApiResponse($this->service->retryAction($runId));
    }

    public function jump(JumpScenarioRunRequest $request, string $runId): ApiResponse
    {
        return new ApiResponse($this->service->jump(
            $runId,
            ScenarioRunJumpData::fromRequest($request),
            $request->user()?->id,
        ));
    }

    public function history(string $runId): ApiResponse
    {
        return new ApiResponse($this->service->historyFor($runId));
    }
}
