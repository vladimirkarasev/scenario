<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use App\Models\Category;
use App\Support\PaginationMeta;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Module\Scenario\DTO\ScenarioFeedData;
use Module\Scenario\Models\Scenario;

final readonly class ScenarioFeedService
{
    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null, folders_total: int, items_total: int},
     *     counts_by_status: array{all: int, active: int, draft: int, archived: int}
     * }
     */
    public function feed(?string $projectId, ScenarioFeedData $data): array
    {
        $map = $data->rootId !== null ? $this->categoryMap($projectId) : null;
        $subtreeIds = $map !== null ? $this->subtreeIds($map, $data->rootId) : null;

        $page = $this->paginate($projectId, $data, $subtreeIds);

        return [
            'rows' => $this->rows($page, $projectId, $map, $subtreeIds),
            'pagination' => [
                ...PaginationMeta::fromPaginator($page),
                'folders_total' => $this->foldersQuery($projectId, $data, $subtreeIds)->count(),
                'items_total' => $this->itemsQuery($projectId, $data, $subtreeIds)->count(),
            ],
            'counts_by_status' => $this->countsByStatus($projectId, $data, $subtreeIds),
        ];
    }

    /**
     * @param  list<string>|null  $subtreeIds
     * @return LengthAwarePaginator<int, \stdClass>
     */
    private function paginate(?string $projectId, ScenarioFeedData $data, ?array $subtreeIds): LengthAwarePaginator
    {
        $folders = $this->foldersQuery($projectId, $data, $subtreeIds)
            ->toBase()
            ->select(['categories.id', 'categories.name'])
            ->selectRaw("'folder' as item_type");

        $scenarios = $this->itemsQuery($projectId, $data, $subtreeIds)
            ->toBase()
            ->select(['scenarios.id', 'scenarios.name'])
            ->selectRaw("'scenario' as item_type");

        /** @var LengthAwarePaginator<int, \stdClass> */
        return DB::query()
            ->fromSub($folders->unionAll($scenarios), 'feed_items')
            ->orderByRaw("case when item_type = 'folder' then 0 else 1 end")
            ->orderBy('name')
            ->paginate($data->perPage, ['*'], 'page', $data->page);
    }

    /**
     * @param  LengthAwarePaginator<int, \stdClass>  $page
     * @param  array<string, array{name: string, parent_id: string|null}>|null  $map
     * @param  list<string>|null  $subtreeIds
     * @return array<int, array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $page, ?string $projectId, ?array $map, ?array $subtreeIds): array
    {
        $ids = ['folder' => [], 'scenario' => []];
        foreach ($page->items() as $item) {
            $type = is_string($item->item_type ?? null) ? $item->item_type : '';
            $id = is_string($item->id ?? null) ? $item->id : '';
            if ($id !== '' && isset($ids[$type])) {
                $ids[$type][] = $id;
            }
        }

        $folders = $this->foldersByIds($ids['folder'], $projectId);
        $scenarios = $this->scenariosByIds($ids['scenario']);

        $rows = [];
        foreach ($page->items() as $item) {
            $type = is_string($item->item_type ?? null) ? $item->item_type : '';
            $id = is_string($item->id ?? null) ? $item->id : '';

            if ($type === 'folder' && isset($folders[$id])) {
                $rows[] = $this->folderRow($folders[$id], $map);
            } elseif ($type === 'scenario' && isset($scenarios[$id])) {
                $rows[] = $this->scenarioRow($scenarios[$id], $map, $subtreeIds);
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, Category>
     */
    private function foldersByIds(array $ids, ?string $projectId): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<string, Category> */
        return Category::query()
            ->withCount([
                'children' => fn(Builder $q) => $q->whereExists($this->boundToScenarios($projectId)),
            ])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, Scenario>
     */
    private function scenariosByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<string, Scenario> */
        return Scenario::query()
            ->with(['createdBy', 'updatedBy', 'categories'])
            ->withCount('versions')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * @param  list<string>|null  $subtreeIds
     * @return array{all: int, active: int, draft: int, archived: int}
     */
    private function countsByStatus(?string $projectId, ScenarioFeedData $data, ?array $subtreeIds): array
    {
        $base = fn(?string $status): ScenarioFeedData => new ScenarioFeedData(
            parentSet: $data->parentSet,
            parentId: $data->parentId,
            search: $data->search,
            status: $status,
            excludeScenarioId: $data->excludeScenarioId,
            page: 1,
            perPage: 1,
            rootId: $data->rootId,
        );

        $all = $this->itemsQuery($projectId, $base(null), $subtreeIds)->count();
        $active = $this->itemsQuery($projectId, $base('active'), $subtreeIds)->count();
        $draft = $this->itemsQuery($projectId, $base('draft'), $subtreeIds)->count();
        $archived = $this->itemsQuery($projectId, $base('archived'), $subtreeIds)->count();

        return [
            'all' => $all,
            'active' => $active,
            'draft' => $draft,
            'archived' => $archived,
        ];
    }

    /**
     * @param  list<string>|null  $subtreeIds
     * @return Builder<Category>
     */
    private function foldersQuery(?string $projectId, ScenarioFeedData $data, ?array $subtreeIds): Builder
    {
        $descendantIds = $subtreeIds !== null
            ? array_values(array_filter($subtreeIds, static fn(string $id): bool => $id !== $data->rootId))
            : null;

        return Category::query()
            ->when($data->parentSet && $data->parentId === null, static fn(Builder $q) => $q->whereNull('parent_id'))
            ->when(
                $data->parentSet && $data->parentId !== null,
                static fn(Builder $q) => $q->where('parent_id', $data->parentId)
            )
            ->when(
                $descendantIds !== null,
                static fn(Builder $q) => $q->whereIn('categories.id', $descendantIds ?? [])
            )
            ->whereExists($this->boundToScenarios($projectId))
            ->when(
                $data->search !== null,
                static fn(Builder $q) => $q->whereRaw(
                    'LOWER(categories.name) like ?',
                    ['%'.mb_strtolower((string)$data->search).'%']
                ),
            )
            ->orderBy('name');
    }

    private function boundToScenarios(?string $projectId): Closure
    {
        return static function (QueryBuilder $q) use ($projectId): void {
            $q->from('model_has_categories')
                ->whereColumn('model_has_categories.category_id', 'categories.id')
                ->where('model_has_categories.model_type', Scenario::class);
            if ($projectId !== null) {
                $q->where('model_has_categories.project_id', $projectId);
            }
        };
    }

    /**
     * @param  list<string>|null  $subtreeIds
     * @return Builder<Scenario>
     */
    private function itemsQuery(?string $projectId, ScenarioFeedData $data, ?array $subtreeIds): Builder
    {
        return Scenario::query()
            ->when($projectId !== null, static fn(Builder $q) => $q->where('project_id', $projectId))
            ->when(
                $subtreeIds !== null,
                static fn(Builder $q) => $q->whereHas(
                    'categories',
                    static fn(Builder $sub) => $sub->whereIn('categories.id', $subtreeIds ?? []),
                ),
            )
            ->when(
                $data->parentSet && $data->parentId !== null,
                static fn(Builder $q) => $q->whereHas(
                    'categories',
                    static fn(Builder $sub) => $sub->where('categories.id', $data->parentId),
                ),
            )
            ->when(
                $data->parentSet && $data->parentId === null,
                static fn(Builder $q) => $q->whereDoesntHave('categories'),
            )
            ->when(
                $data->status !== null,
                static fn(Builder $q) => $q->where('scenarios.status', $data->status),
            )
            ->when(
                $data->excludeScenarioId !== null,
                static fn(Builder $q) => $q->where('scenarios.id', '!=', $data->excludeScenarioId),
            )
            ->search($data->search)
            ->orderBy('name');
    }

    /**
     * @param  array<string, array{name: string, parent_id: string|null}>|null  $map
     * @return array<string, mixed>
     */
    private function folderRow(Category $folder, ?array $map): array
    {
        return [
            'type' => 'folder',
            'id' => $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parent_id,
            'parent_path' => $map !== null ? $this->pathFor($map, $folder->parent_id) : null,
            'path_ids' => $map !== null ? $this->pathIdsFor($map, $folder->id) : null,
            'children_count' => $folder->children_count ?? 0,
            'created_at' => $folder->created_at?->toIso8601String(),
            'updated_at' => $folder->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, array{name: string, parent_id: string|null}>|null  $map
     * @param  list<string>|null  $subtreeIds
     * @return array<string, mixed>
     */
    private function scenarioRow(Scenario $scenario, ?array $map, ?array $subtreeIds): array
    {
        $inSubtree = $subtreeIds !== null
            ? $scenario->categories->first(static fn(Category $c): bool => in_array($c->id, $subtreeIds, true))
            : null;
        $folder = $inSubtree ?? $scenario->categories->first();
        $folderId = $folder?->id;

        return [
            'type' => 'scenario',
            'id' => $scenario->id,
            'name' => $scenario->name,
            'alias' => $scenario->alias,
            'description' => $scenario->description,
            'status' => $scenario->status->value,
            'folder_id' => $folderId,
            'folder_path' => $map !== null ? $this->pathFor($map, $folderId) : null,
            'tags' => $scenario->tags ?? [],
            'versions_count' => $scenario->versions_count ?? 0,
            'active_version_id' => $scenario->active_version_id,
            'created_at' => $scenario->created_at?->toIso8601String(),
            'updated_at' => $scenario->updated_at?->toIso8601String(),
            'created_by' => $scenario->createdBy !== null ? [
                'id' => $scenario->createdBy->id,
                'name' => $scenario->createdBy->name,
                'fio' => $scenario->createdBy->fio,
                'login' => $scenario->createdBy->login,
            ] : null,
            'updated_by' => $scenario->updatedBy !== null ? [
                'id' => $scenario->updatedBy->id,
                'name' => $scenario->updatedBy->name,
                'fio' => $scenario->updatedBy->fio,
                'login' => $scenario->updatedBy->login,
            ] : null,
        ];
    }

    /**
     * @return array<string, array{name: string, parent_id: string|null}>
     */
    private function categoryMap(?string $projectId): array
    {
        return Category::query()
            ->whereExists($this->boundToScenarios($projectId))
            ->get(['id', 'name', 'parent_id'])
            ->mapWithKeys(static fn(Category $c): array => [
                $c->id => ['name' => $c->name, 'parent_id' => $c->parent_id],
            ])
            ->all();
    }

    /**
     * @param  array<string, array{name: string, parent_id: string|null}>  $map
     * @return list<string>
     */
    private function subtreeIds(array $map, string $rootId): array
    {
        $childrenByParent = [];
        foreach ($map as $id => $info) {
            $childrenByParent[$info['parent_id'] ?? ''][] = $id;
        }

        $ids = [];
        $stack = [$rootId];
        while ($stack !== []) {
            $id = array_pop($stack);
            $ids[] = $id;
            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, array{name: string, parent_id: string|null}>  $map
     */
    private function pathFor(array $map, ?string $catId): string
    {
        $parts = [];
        $guard = [];
        $current = $catId;
        while ($current !== null && isset($map[$current]) && !isset($guard[$current])) {
            $guard[$current] = true;
            array_unshift($parts, $map[$current]['name']);
            $current = $map[$current]['parent_id'];
        }

        return implode(' / ', $parts);
    }

    /**
     * @param  array<string, array{name: string, parent_id: string|null}>  $map
     * @return list<string>
     */
    private function pathIdsFor(array $map, ?string $catId): array
    {
        $ids = [];
        $guard = [];
        $current = $catId;
        while ($current !== null && isset($map[$current]) && !isset($guard[$current])) {
            $guard[$current] = true;
            array_unshift($ids, $current);
            $current = $map[$current]['parent_id'];
        }

        return $ids;
    }
}
