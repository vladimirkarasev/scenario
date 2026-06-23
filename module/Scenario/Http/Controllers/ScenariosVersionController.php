<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Scenario\DTO\ScenarioVersionActionData;
use Module\Scenario\DTO\ScenarioVersionData;
use Module\Scenario\Http\Requests\ScenarioVersionRequest;
use Module\Scenario\Http\Resources\JsonApi\ScenariosVersionResource;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ScenarioVersionService;

final class ScenariosVersionController extends Controller
{
    public function __construct(
        private readonly ScenarioVersionService $scenarioVersionService,
    ) {
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

    public function store(ScenarioVersionRequest $request, Scenario $scenario): JsonResponse
    {
        return new JsonResponse(
            $this->scenarioVersionService->create(ScenarioVersionData::fromRequest($request), $scenario),
            201,
        );
    }

    public function update(ScenarioVersionRequest $request, Scenario $scenario, ScenarioVersion $version): JsonResponse
    {
        return new JsonResponse(
            $this->scenarioVersionService->update(ScenarioVersionData::fromRequest($request), $version),
        );
    }

    public function duplicate(Request $request, Scenario $scenario, ScenarioVersion $version): JsonResponse
    {
        return new JsonResponse(
            $this->scenarioVersionService->duplicate(ScenarioVersionActionData::fromRequest($request), $version),
            201,
        );
    }

    public function destroy(Request $request, Scenario $scenario, ScenarioVersion $version): JsonResponse
    {
        return new JsonResponse(
            $this->scenarioVersionService->delete(ScenarioVersionActionData::fromRequest($request), $version),
        );
    }
}
