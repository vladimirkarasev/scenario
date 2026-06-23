<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;

final class ScenarioVersionRepository
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $content
     */
    public function create(Scenario $scenario, array $metadata, array $content): ScenarioVersion
    {
        if (($metadata['name'] ?? null) === null) {
            $metadata['name'] = 'v'.($scenario->versions()->count() + 1);
        }

        $metadata['project_id'] = $scenario->project_id;

        $version = $scenario->versions()->create($metadata);
        $revision = $this->storeRevision($version, $content);
        $version->setRelation('latestRevision', $revision);

        return $version;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $content
     */
    public function update(ScenarioVersion $version, array $metadata, array $content): ScenarioVersion
    {
        if ($metadata !== []) {
            $version->fill($metadata)->save();
        }

        $revision = $this->storeRevision($version, $content);
        $version->setRelation('latestRevision', $revision);

        return $version;
    }

    public function duplicate(ScenarioVersion $version): ScenarioVersion
    {
        $scenario = $version->scenario()->firstOrFail();
        $baseName = $version->name ?? ('v'.($scenario->versions()->count() + 1));

        $copy = ScenarioVersion::query()->create([
            'id' => (string)Str::uuid(),
            'scenario_id' => $version->scenario_id,
            'project_id' => $version->project_id,
            'name' => $baseName.' (копия)',
            'status' => 'draft',
        ]);

        $source = $version->relationLoaded('latestRevision')
            ? $version->latestRevision
            : $version->revisions()->first();

        if ($source !== null) {
            $revision = $this->storeRevision($copy, [
                'schema_json' => $source->schema_json,
                'nodes_json' => $source->nodes_json,
                'edges_json' => $source->edges_json,
                'schema_version' => $source->schema_version,
            ]);
            $copy->setRelation('latestRevision', $revision);
        }

        return $copy;
    }

    public function delete(ScenarioVersion $version): void
    {
        $version->delete();
    }

    /** @param  array<string, mixed>  $content */
    public function storeRevision(ScenarioVersion $version, array $content): ScenarioVersionRevision
    {
        $latestCreatedAt = $version->revisions()
            ->latest('created_at')
            ->value('created_at');

        $createdAt = $content['created_at']
            ?? (is_string($latestCreatedAt) ? Carbon::parse($latestCreatedAt)->addSecond() : now());

        return $version->revisions()->create([
            'schema_json' => $content['schema_json'] ?? null,
            'nodes_json' => $content['nodes_json'] ?? null,
            'edges_json' => $content['edges_json'] ?? null,
            'schema_version' => $content['schema_version'] ?? 1,
            'created_at' => $createdAt,
        ]);
    }

    public function resolveForScenario(Scenario $scenario, ?string $scenarioVersionId): ScenarioVersion
    {
        if ($scenarioVersionId === null) {
            $active = $scenario->active_version_id !== null
                ? $scenario->activeVersion()->first()
                : null;
            return $active ?? $scenario->versions()->firstOrFail();
        }

        return ScenarioVersion::query()
            ->whereKey($scenarioVersionId)
            ->where('scenario_id', $scenario->id)
            ->firstOr(fn() => throw ValidationException::withMessages([
                'scenario_version_id' => ['Scenario version does not belong to the selected scenario.'],
            ]));
    }

    public function activeOrLatestForScenario(string $scenarioId): ?ScenarioVersion
    {
        return ScenarioVersion::query()
            ->where('scenario_id', $scenarioId)
            ->where('status', 'active')
            ->first()
            ?? ScenarioVersion::query()
                ->where('scenario_id', $scenarioId)
                ->latest('created_at')
                ->first();
    }
}
