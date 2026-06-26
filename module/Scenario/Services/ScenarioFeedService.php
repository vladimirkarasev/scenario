<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Module\Scenario\DTO\ScenarioFeedData;
use Module\Scenario\Models\Scenario;

final readonly class ScenarioFeedService
{
    /**
     * Возвращает смешанный поток "папки сверху + сценарии" с единой пагинацией.
     *
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, per_page: int, total: int, folders_total: int, items_total: int},
     *     counts_by_status: array{all: int, active: int, draft: int, archived: int}
     * }
     */
    public function feed(?string $projectId, ScenarioFeedData $data): array
    {
        $map = $data->rootId !== null ? $this->categoryMap($projectId) : null;
        $subtreeIds = $map !== null ? $this->subtreeIds($map, $data->rootId) : null;

        $foldersTotal = $this->foldersQuery($projectId, $data, $subtreeIds)->count();
        $itemsTotal = $this->itemsQuery($projectId, $data, $subtreeIds)->count();
        $countsByStatus = $this->countsByStatus($projectId, $data, $subtreeIds);

        $total = $foldersTotal + $itemsTotal;
        $lastPage = max(1, (int)ceil($total / $data->perPage));
        $offset = ($data->page - 1) * $data->perPage;

        $folderOffset = min($offset, $foldersTotal);
        $folderTake = max(0, min($data->perPage, $foldersTotal - $folderOffset));
        $itemOffset = max(0, $offset - $foldersTotal);
        $itemTake = $data->perPage - $folderTake;

        $folderRows = $folderTake > 0
            ? $this->loadFolders($projectId, $data, $subtreeIds, $map, $folderOffset, $folderTake)
            : [];

        $itemRows = $itemTake > 0
            ? $this->loadItems($projectId, $data, $subtreeIds, $map, $itemOffset, $itemTake)
            : [];

        return [
            'data' => [...$folderRows, ...$itemRows],
            'pagination' => [
                'current_page' => $data->page,
                'last_page' => $lastPage,
                'per_page' => $data->perPage,
                'total' => $total,
                'folders_total' => $foldersTotal,
                'items_total' => $itemsTotal,
            ],
            'counts_by_status' => $countsByStatus,
        ];
    }

    /**
     * Счётчики сценариев по статусам в текущей категории/поиске (без учёта status фильтра).
     *
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
            ->whereExists(static function (\Illuminate\Database\Query\Builder $q) use ($projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', Scenario::class);
                if ($projectId !== null) {
                    $q->where('model_has_categories.project_id', $projectId);
                }
            })
            ->when(
                $data->search !== null,
                static fn(Builder $q) => $q->whereRaw(
                    'LOWER(categories.name) like ?',
                    ['%'.mb_strtolower((string)$data->search).'%']
                ),
            )
            ->orderBy('name');
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
            ->when(
                $data->search !== null,
                static function (Builder $q) use ($data): void {
                    $like = '%'.mb_strtolower((string)$data->search).'%';
                    $q->where(static function (Builder $w) use ($like): void {
                        $w->whereRaw('LOWER(scenarios.name) like ?', [$like])
                            ->orWhereRaw('LOWER(scenarios.description) like ?', [$like])
                            ->orWhereRaw('LOWER(scenarios.alias) like ?', [$like]);
                    });
                },
            )
            ->orderBy('name');
    }

    /**
     * @param  list<string>|null  $subtreeIds
     * @param  array<string, array{name: string, parent_id: string|null}>|null  $map
     * @return array<int, array<string, mixed>>
     */
    private function loadFolders(?string $projectId, ScenarioFeedData $data, ?array $subtreeIds, ?array $map, int $offset, int $take): array
    {
        $rows = $this->foldersQuery($projectId, $data, $subtreeIds)
            ->withCount([
                'children' => static function (Builder $q) use ($projectId): void {
                    $q->whereExists(static function (\Illuminate\Database\Query\Builder $sub) use ($projectId): void {
                        $sub->from('model_has_categories')
                            ->whereColumn('model_has_categories.category_id', 'categories.id')
                            ->where('model_has_categories.model_type', Scenario::class);
                        if ($projectId !== null) {
                            $sub->where('model_has_categories.project_id', $projectId);
                        }
                    });
                }
            ])
            ->offset($offset)
            ->limit($take)
            ->get();

        return $rows->map(fn(Category $c): array => [
            'type' => 'folder',
            'id' => $c->id,
            'name' => $c->name,
            'parent_id' => $c->parent_id,
            'parent_path' => $map !== null ? $this->pathFor($map, $c->parent_id) : null,
            'path_ids' => $map !== null ? $this->pathIdsFor($map, $c->id) : null,
            'children_count' => $c->children_count ?? 0,
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    /**
     * @param  list<string>|null  $subtreeIds
     * @param  array<string, array{name: string, parent_id: string|null}>|null  $map
     * @return array<int, array<string, mixed>>
     */
    private function loadItems(?string $projectId, ScenarioFeedData $data, ?array $subtreeIds, ?array $map, int $offset, int $take): array
    {
        $rows = $this->itemsQuery($projectId, $data, $subtreeIds)
            ->with(['createdBy', 'updatedBy', 'categories'])
            ->withCount('versions')
            ->offset($offset)
            ->limit($take)
            ->get();

        return $rows->map(function (Scenario $s) use ($map, $subtreeIds): array {
            $inSubtree = $subtreeIds !== null
                ? $s->categories->first(static fn(Category $c): bool => in_array($c->id, $subtreeIds, true))
                : null;
            $folder = $inSubtree ?? $s->categories->first();
            $folderId = $folder?->id;

            return [
                'type' => 'scenario',
                'id' => $s->id,
                'name' => $s->name,
                'alias' => $s->alias,
                'description' => $s->description,
                'status' => $s->status->value,
                'folder_id' => $folderId,
                'folder_path' => $map !== null ? $this->pathFor($map, $folderId) : null,
                'tags' => $s->tags ?? [],
                'versions_count' => $s->versions_count ?? 0,
                'active_version_id' => $s->active_version_id,
                'created_at' => $s->created_at?->toIso8601String(),
                'updated_at' => $s->updated_at?->toIso8601String(),
                'created_by' => $s->createdBy !== null ? [
                    'id' => $s->createdBy->id,
                    'name' => $s->createdBy->name,
                    'fio' => $s->createdBy->fio,
                    'login' => $s->createdBy->login,
                ] : null,
                'updated_by' => $s->updatedBy !== null ? [
                    'id' => $s->updatedBy->id,
                    'name' => $s->updatedBy->name,
                    'fio' => $s->updatedBy->fio,
                    'login' => $s->updatedBy->login,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * Карта scenario-категорий проекта: id => {name, parent_id}. Для построения путей.
     *
     * @return array<string, array{name: string, parent_id: string|null}>
     */
    private function categoryMap(?string $projectId): array
    {
        return Category::query()
            ->whereExists(static function (\Illuminate\Database\Query\Builder $q) use ($projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', Scenario::class);
                if ($projectId !== null) {
                    $q->where('model_has_categories.project_id', $projectId);
                }
            })
            ->get(['id', 'name', 'parent_id'])
            ->mapWithKeys(static fn(Category $c): array => [
                $c->id => ['name' => $c->name, 'parent_id' => $c->parent_id],
            ])
            ->all();
    }

    /**
     * id корня и всех его потомков.
     *
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
     * Путь категории строкой «A / B / C» по карте.
     *
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
     * Цепочка id предков от корня до категории (включительно) — для разворота дерева.
     *
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
