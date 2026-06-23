<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use App\Services\Scenario\CatalogService;
use Module\Scenario\DTO\ScenarioVersionActionData;
use Module\Scenario\DTO\ScenarioVersionData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Repositories\ScenarioVersionRepository;

final readonly class ScenarioVersionService
{
    public function __construct(
        private CatalogService $catalogService,
        private ScenarioVersionRepository $versions,
    ) {}

    /** @return array<string, mixed> */
    public function create(ScenarioVersionData $data, Scenario $scenario): array
    {
        $version = $this->versions->create($scenario, $data->toAttributes(), $data->toRevisionAttributes());

        return [
            'item' => $this->catalogService->versionPayload($version),
            'scenario_id' => $scenario->id,
        ];
    }

    /** @return array<string, mixed> */
    public function update(ScenarioVersionData $data, ScenarioVersion $version): array
    {
        $version = $this->versions->update($version, $data->toAttributes(), $data->toRevisionAttributes());

        return [
            'item' => $this->catalogService->versionPayload($version),
            'scenario_id' => $version->scenario_id,
        ];
    }

    /** @return array<string, mixed> */
    public function duplicate(ScenarioVersionActionData $data, ScenarioVersion $version): array
    {
        $copy = $this->versions->duplicate($version);

        return [
            'item' => $this->catalogService->versionPayload($copy),
            'scenario_id' => $copy->scenario_id,
        ];
    }

    /** @return array<string, mixed> */
    public function delete(ScenarioVersionActionData $data, ScenarioVersion $version): array
    {
        $scenarioId = $version->scenario_id;
        $this->versions->delete($version);

        return [
            'scenario_id' => $scenarioId,
        ];
    }
}
