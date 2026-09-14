<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Scenario\DTO\ScenarioActionData;
use Module\Scenario\DTO\ScenarioData;
use Module\Scenario\DTO\ScenarioIndexData;
use Module\Scenario\Enums\ScenarioErrorCode;
use Module\Scenario\Http\Requests\ScenarioRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Scenario\Http\Resources\JsonApi\ScenariosResource;
use Module\Scenario\Models\Scenario;
use Module\Projects\CurrentProject;
use Module\Scenario\Services\Definition\ScenarioService;
use Module\Scenario\Services\Definition\ScenariosService;

final class ScenariosController extends Controller
{
    public function __construct(
        private readonly ScenarioService $scenarioService,
        private readonly CurrentProject $currentProject,
    ) {
    }

    public function index(Request $request, ScenariosService $service): AnonymousResourceCollection
    {
        return ScenariosResource::collection($service->paginate(ScenarioIndexData::fromRequest($request)));
    }

    public function show(Scenario $scenario): ScenariosResource
    {
        $this->authorizeProject($scenario);

        return new ScenariosResource($scenario);
    }

    public function store(ScenarioRequest $request): ApiResponse
    {
        return new ApiResponse(
            $this->scenarioService->create(ScenarioData::fromRequest($request)),
            201,
        );
    }

    public function update(ScenarioRequest $request, Scenario $scenario): ApiResponse
    {
        $this->authorizeProject($scenario);

        return new ApiResponse(
            $this->scenarioService->update(ScenarioData::fromRequest($request), $scenario),
        );
    }

    public function duplicate(Request $request, Scenario $scenario): ApiResponse
    {
        $this->authorizeProject($scenario);

        return new ApiResponse(
            $this->scenarioService->duplicate(ScenarioActionData::fromRequest($request), $scenario),
            201,
        );
    }

    public function destroy(Request $request, Scenario $scenario): JsonResponse
    {
        $this->authorizeProject($scenario);

        $this->scenarioService->delete(ScenarioActionData::fromRequest($request), $scenario);

        return new JsonResponse(status: 204);
    }

    private function authorizeProject(Scenario $scenario): void
    {
        if ($scenario->project_id !== $this->currentProject->id()) {
            throw NotFoundException::from(ScenarioErrorCode::ScenarioNotFound);
        }
    }
}
