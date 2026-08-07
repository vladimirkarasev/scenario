<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use App\Services\Scenario\CatalogService;
use Module\Scenario\DTO\ScenarioVersionActionData;
use Module\Scenario\DTO\ScenarioVersionData;
use Module\Scenario\DTO\ScenarioVersionSettingsData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Repositories\ScenarioVersionRepository;

final readonly class ScenarioVersionService
{
    public function __construct(
        private CatalogService $catalogService,
        private ScenarioVersionRepository $versions,
    ) {
    }

    /** @return array<string, mixed> */
    public function create(ScenarioVersionData $data, Scenario $scenario): array
    {
        $version = $this->versions->create($scenario, $data->toAttributes(), $data->toRevisionAttributes());

        return $this->catalogService->versionPayload($version);
    }

    /** @return array<string, mixed> */
    public function update(ScenarioVersionData $data, ScenarioVersion $version): array
    {
        $version = $this->versions->update($version, $data->toAttributes(), $data->toRevisionAttributes());

        return $this->catalogService->versionPayload($version);
    }

    /** @return array<string, mixed> */
    public function updateSettings(ScenarioVersionSettingsData $data, ScenarioVersion $version): array
    {
        $version = $this->versions->updateAttributes($version, $data->toAttributes());

        return $this->catalogService->versionPayload($version);
    }

    /** @return array<string, mixed> */
    public function duplicate(ScenarioVersionActionData $data, ScenarioVersion $version): array
    {
        $copy = $this->versions->duplicate($version);

        return $this->catalogService->versionPayload($copy);
    }

    public function delete(ScenarioVersionActionData $data, ScenarioVersion $version): void
    {
        $this->versions->delete($version);
    }
}
