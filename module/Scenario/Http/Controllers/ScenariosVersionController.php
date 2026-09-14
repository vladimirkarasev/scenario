<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Scenario\DTO\ScenarioVersionActionData;
use Module\Scenario\DTO\ScenarioVersionData;
use Module\Scenario\DTO\ScenarioVersionHistoryData;
use Module\Scenario\DTO\ScenarioVersionSettingsData;
use Module\Scenario\Http\Requests\ScenarioVersionRequest;
use Module\Scenario\Http\Requests\ScenarioVersionSettingsRequest;
use Module\Scenario\Http\Resources\JsonApi\ScenariosVersionResource;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Definition\ScenarioVersionService;
use Module\Scenario\Services\Definition\ScenarioVersionViewService;

final class ScenariosVersionController extends Controller
{
    public function __construct(
        private readonly ScenarioVersionService $scenarioVersionService,
        private readonly ScenarioVersionViewService $scenarioVersionViewService,
    ) {
    }

    public function editor(Scenario $scenario, ScenarioVersion $version): ApiResponse
    {
        return new ApiResponse($this->scenarioVersionViewService->editor($version));
    }

    public function settings(Scenario $scenario, ScenarioVersion $version): ApiResponse
    {
        return new ApiResponse($this->scenarioVersionViewService->settings($version));
    }

    public function history(Request $request, Scenario $scenario, ScenarioVersion $version): ApiResponse
    {
        $result = $this->scenarioVersionViewService->history(
            $version,
            ScenarioVersionHistoryData::fromRequest($request),
        );

        return new ApiResponse($result['revisions'], meta: $result['pagination']);
    }

    public function index(Scenario $scenario): AnonymousResourceCollection
    {
        $scenario->loadMissing(['versions.latestRevision', 'versions.revisions']);

        return ScenariosVersionResource::collection($scenario->versions);
    }

    public function show(Scenario $scenario, ScenarioVersion $version): ScenariosVersionResource
    {
        $version->loadMissing(['latestRevision', 'revisions']);

        return new ScenariosVersionResource($version);
    }

    public function store(ScenarioVersionRequest $request, Scenario $scenario): ApiResponse
    {
        return new ApiResponse(
            $this->scenarioVersionService->create(ScenarioVersionData::fromRequest($request, $scenario->type), $scenario),
            201,
        );
    }

    public function update(ScenarioVersionRequest $request, Scenario $scenario, ScenarioVersion $version): ApiResponse
    {
        return new ApiResponse(
            $this->scenarioVersionService->update(ScenarioVersionData::fromRequest($request, $scenario->type), $version),
        );
    }

    public function updateSettings(
        ScenarioVersionSettingsRequest $request,
        Scenario $scenario,
        ScenarioVersion $version,
    ): ApiResponse {
        return new ApiResponse(
            $this->scenarioVersionService->updateSettings(
                ScenarioVersionSettingsData::fromRequest($request),
                $version,
            ),
        );
    }

    public function duplicate(Request $request, Scenario $scenario, ScenarioVersion $version): ApiResponse
    {
        return new ApiResponse(
            $this->scenarioVersionService->duplicate(ScenarioVersionActionData::fromRequest($request), $version),
            201,
        );
    }

    public function destroy(Request $request, Scenario $scenario, ScenarioVersion $version): JsonResponse
    {
        $this->scenarioVersionService->delete(ScenarioVersionActionData::fromRequest($request), $version);

        return new JsonResponse(status: 204);
    }
}
