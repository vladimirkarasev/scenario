<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Scenario\DTO\ScenarioFieldPresetData;
use Module\Scenario\Http\Requests\ScenarioFieldPresetRequest;
use Module\Scenario\Http\Resources\ScenarioFieldPresetResource;
use Module\Scenario\Models\ScenarioFieldPreset;
use Module\Scenario\Services\ScenarioFieldPresetService;

final class ScenarioFieldPresetController extends Controller
{
    public function __construct(private readonly ScenarioFieldPresetService $service)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return ScenarioFieldPresetResource::collection($this->service->all());
    }

    public function store(ScenarioFieldPresetRequest $request): JsonResponse
    {
        $preset = $this->service->create(ScenarioFieldPresetData::fromRequest($request), $request->user());

        return new JsonResponse(new ScenarioFieldPresetResource($preset), 201);
    }

    public function update(
        ScenarioFieldPresetRequest $request,
        ScenarioFieldPreset $fieldPreset,
    ): ScenarioFieldPresetResource {
        return new ScenarioFieldPresetResource(
            $this->service->update(
                ScenarioFieldPresetData::fromRequest($request),
                $fieldPreset,
                $request->user(),
            ),
        );
    }

    public function destroy(ScenarioFieldPreset $fieldPreset): JsonResponse
    {
        $this->service->delete($fieldPreset);

        return new JsonResponse(status: 204);
    }
}
