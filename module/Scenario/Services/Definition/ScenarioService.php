<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Definition;

use App\Services\Scenario\CatalogService;
use Module\Projects\CurrentProject;
use Module\Scenario\DTO\ScenarioActionData;
use Module\Scenario\DTO\ScenarioData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Repositories\ScenarioRepository;

final readonly class ScenarioService
{
    public function __construct(
        private CatalogService $catalogService,
        private ScenarioRepository $scenarios,
        private CurrentProject $currentProject,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function items(): array
    {
        return $this->catalogService->scenarios()->values()->all();
    }

    /** @return array<string, mixed> */
    public function create(ScenarioData $data): array
    {
        $attributes = array_merge($data->toAttributes(), [
            'project_id' => $this->currentProject->id(),
        ]);

        $scenario = $this->scenarios->create($attributes, $data->actorId, $data->categoryIds, $data->groupIds);
        $this->scenarios->loadPayloadRelations($scenario);

        return $this->catalogService->scenarioPayload($scenario);
    }

    /** @return array<string, mixed> */
    public function update(ScenarioData $data, Scenario $scenario): array
    {
        $scenario = $this->scenarios->update(
            $scenario,
            $data->toAttributes(),
            $data->actorId,
            $data->categoryIds,
            $data->groupIds
        );
        $this->scenarios->loadPayloadRelations($scenario);

        return $this->catalogService->scenarioPayload($scenario);
    }

    /** @return array<string, mixed> */
    public function duplicate(ScenarioActionData $data, Scenario $scenario): array
    {
        $copy = $this->scenarios->duplicate($scenario, $data->actorId);
        $this->scenarios->loadPayloadRelations($copy);

        return $this->catalogService->scenarioPayload($copy);
    }

    public function delete(ScenarioActionData $data, Scenario $scenario): void
    {
        $this->scenarios->delete($scenario);
    }
}
