<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;

final class ScenarioRepository
{
    /** @return Collection<int, Scenario> */
    public function orderedForCatalog(): Collection
    {
        return Scenario::query()
            ->with([
                'categories',
                'groups',
                'versions.revisions',
                'versions.latestRevision',
                'createdBy',
                'updatedBy',
            ])
            ->orderBy('name')
            ->get();
    }

    public function findOrFail(string $id): Scenario
    {
        return Scenario::query()->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<string>         $categoryIds
     * @param list<string>         $groupIds
     */
    public function create(array $attributes, ?int $actorId, array $categoryIds, array $groupIds = []): Scenario
    {
        $scenario = new Scenario($attributes);
        $scenario->created_by = $actorId;
        $scenario->updated_by = $actorId;
        $scenario->save();
        $scenario->categories()->sync($this->categoryPivot($categoryIds, $scenario->project_id));
        $scenario->groups()->sync($groupIds);

        return $scenario;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<string>         $categoryIds
     * @param list<string>         $groupIds
     */
    public function update(Scenario $scenario, array $attributes, ?int $actorId, array $categoryIds, array $groupIds = []): Scenario
    {
        $scenario->fill($attributes);
        $scenario->updated_by = $actorId;
        $scenario->save();
        $scenario->categories()->sync($this->categoryPivot($categoryIds, $scenario->project_id));
        $scenario->groups()->sync($groupIds);

        return $scenario;
    }

    public function duplicate(Scenario $scenario, ?int $actorId): Scenario
    {
        return DB::transaction(function () use ($scenario, $actorId): Scenario {
            $scenario->load('versions.revisions');

            $copy = new Scenario($scenario->only([
                'project_id', 'description', 'tags',
            ]));
            $copy->name = $scenario->name.' (копия)';
            $copy->alias = null;
            $copy->is_active = false;
            $copy->created_by = $actorId;
            $copy->updated_by = $actorId;
            $copy->save();

            /** @var list<string> $categoryIds */
            $categoryIds = $scenario->categories->pluck('id')
                ->map(static fn (mixed $id): string => is_string($id) ? $id : '')
                ->filter(static fn (string $id): bool => $id !== '')
                ->values()
                ->all();
            $copy->categories()->sync($this->categoryPivot($categoryIds, $copy->project_id));

            $versionIdMap = [];

            foreach ($scenario->versions as $version) {
                $newId = (string) Str::uuid();
                $versionIdMap[$version->id] = $newId;

                $newVersion = ScenarioVersion::query()->create([
                    'id' => $newId,
                    'scenario_id' => $copy->id,
                    'project_id' => $copy->project_id,
                    'name' => $version->name,
                    'status' => $version->status,
                    'created_at' => $version->created_at,
                ]);

                foreach ($version->revisions as $revision) {
                    ScenarioVersionRevision::query()->create([
                        'scenario_version_id' => $newVersion->id,
                        'schema_json' => $revision->schema_json,
                        'nodes_json' => $revision->nodes_json,
                        'edges_json' => $revision->edges_json,
                        'schema_version' => $revision->schema_version,
                        'created_at' => $revision->created_at,
                    ]);
                }
            }

            if ($scenario->active_version_id !== null && isset($versionIdMap[$scenario->active_version_id])) {
                $copy->active_version_id = $versionIdMap[$scenario->active_version_id];
                $copy->save();
            }

            return $copy;
        });
    }

    public function delete(Scenario $scenario): void
    {
        $scenario->delete();
    }

    /**
     * @param  list<string>              $categoryIds
     * @return array<string, array<string, string|null>>
     */
    private function categoryPivot(array $categoryIds, ?string $projectId): array
    {
        $pivot = [];
        foreach ($categoryIds as $id) {
            $pivot[$id] = ['project_id' => $projectId];
        }

        return $pivot;
    }

    public function loadPayloadRelations(Scenario $scenario): Scenario
    {
        return $scenario->load(['categories', 'groups', 'versions', 'createdBy', 'updatedBy']);
    }
}
