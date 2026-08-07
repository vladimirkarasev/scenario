<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Module\Scenario\Models\ScenarioFieldPreset;

final class ScenarioFieldPresetRepository
{
    /** @return Collection<int, ScenarioFieldPreset> */
    public function allForProject(string $projectId): Collection
    {
        return ScenarioFieldPreset::query()
            ->where('project_id', $projectId)
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ScenarioFieldPreset
    {
        return ScenarioFieldPreset::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(ScenarioFieldPreset $preset, array $attributes): ScenarioFieldPreset
    {
        $preset->update($attributes);

        return $preset->refresh();
    }

    public function delete(ScenarioFieldPreset $preset): void
    {
        $preset->delete();
    }
}
