<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Catalog;

use App\Models\Category;
use App\Support\PaginationMeta;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Scenario\DTO\ScenarioFeedData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\QueryBuilders\ScenarioFeedQueryBuilder;

final readonly class ScenarioFeedService
{
    public function __construct(private ScenarioFeedQueryBuilder $query)
    {
    }

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null, folders_total: int, items_total: int},
     *     counts_by_status: array{all: int, active: int, draft: int, archived: int}
     * }
     */
    public function feed(?string $projectId, ScenarioFeedData $data): array
    {
        $map = $data->rootId !== null ? $this->query->categoryMap($projectId) : null;
        $subtreeIds = $map !== null ? $this->subtreeIds($map, $data->rootId) : null;

        $page = $this->query->paginate($projectId, $data, $subtreeIds);

        return [
            'rows' => $this->rows($page, $projectId, $map, $subtreeIds),
            'pagination' => [
                ...PaginationMeta::fromPaginator($page),
                'folders_total' => $this->query->folders($projectId, $data, $subtreeIds)->count(),
                'items_total' => $this->query->scenarios($projectId, $data, $subtreeIds)->count(),
            ],
            'counts_by_status' => $this->countsByStatus($projectId, $data, $subtreeIds),
        ];
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

        $folders = $this->query->foldersByIds($ids['folder'], $projectId);
        $scenarios = $this->query->scenariosByIds($ids['scenario']);

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

        $all = $this->query->scenarios($projectId, $base(null), $subtreeIds)->count();
        $active = $this->query->scenarios($projectId, $base('active'), $subtreeIds)->count();
        $draft = $this->query->scenarios($projectId, $base('draft'), $subtreeIds)->count();
        $archived = $this->query->scenarios($projectId, $base('archived'), $subtreeIds)->count();

        return [
            'all' => $all,
            'active' => $active,
            'draft' => $draft,
            'archived' => $archived,
        ];
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
            'scenario_type' => $scenario->type->value,
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
