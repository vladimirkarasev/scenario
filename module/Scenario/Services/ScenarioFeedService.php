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
        $foldersTotal = $this->foldersQuery($projectId, $data)->count();
        $itemsTotal = $this->itemsQuery($projectId, $data)->count();
        $countsByStatus = $this->countsByStatus($projectId, $data);

        $total = $foldersTotal + $itemsTotal;
        $lastPage = max(1, (int) ceil($total / $data->perPage));
        $offset = ($data->page - 1) * $data->perPage;

        $folderOffset = min($offset, $foldersTotal);
        $folderTake = max(0, min($data->perPage, $foldersTotal - $folderOffset));
        $itemOffset = max(0, $offset - $foldersTotal);
        $itemTake = $data->perPage - $folderTake;

        $folderRows = $folderTake > 0
            ? $this->loadFolders($projectId, $data, $folderOffset, $folderTake)
            : [];

        $itemRows = $itemTake > 0
            ? $this->loadItems($projectId, $data, $itemOffset, $itemTake)
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
     * @return array{all: int, active: int, draft: int, archived: int}
     */
    private function countsByStatus(?string $projectId, ScenarioFeedData $data): array
    {
        $base = fn (?string $status): ScenarioFeedData => new ScenarioFeedData(
            parentSet: $data->parentSet,
            parentId: $data->parentId,
            search: $data->search,
            status: $status,
            excludeScenarioId: $data->excludeScenarioId,
            page: 1,
            perPage: 1,
        );

        $all = $this->itemsQuery($projectId, $base(null))->count();
        $active = $this->itemsQuery($projectId, $base('active'))->count();
        $draft = $this->itemsQuery($projectId, $base('draft'))->count();
        $archived = $this->itemsQuery($projectId, $base('archived'))->count();

        return [
            'all' => $all,
            'active' => $active,
            'draft' => $draft,
            'archived' => $archived,
        ];
    }

    /** @return Builder<Category> */
    private function foldersQuery(?string $projectId, ScenarioFeedData $data): Builder
    {
        return Category::query()
            ->when($data->parentSet && $data->parentId === null, static fn (Builder $q) => $q->whereNull('parent_id'))
            ->when($data->parentSet && $data->parentId !== null, static fn (Builder $q) => $q->where('parent_id', $data->parentId))
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
                static fn (Builder $q) => $q->whereRaw('LOWER(categories.name) like ?', ['%'.mb_strtolower((string) $data->search).'%']),
            )
            ->orderBy('name');
    }

    /** @return Builder<Scenario> */
    private function itemsQuery(?string $projectId, ScenarioFeedData $data): Builder
    {
        return Scenario::query()
            ->when($projectId !== null, static fn (Builder $q) => $q->where('project_id', $projectId))
            ->when(
                $data->parentSet && $data->parentId !== null,
                static fn (Builder $q) => $q->whereHas(
                    'categories',
                    static fn (Builder $sub) => $sub->where('categories.id', $data->parentId),
                ),
            )
            ->when(
                $data->parentSet && $data->parentId === null,
                static fn (Builder $q) => $q->whereDoesntHave('categories'),
            )
            ->when(
                $data->status !== null,
                static fn (Builder $q) => $q->where('scenarios.status', $data->status),
            )
            ->when(
                $data->excludeScenarioId !== null,
                static fn (Builder $q) => $q->where('scenarios.id', '!=', $data->excludeScenarioId),
            )
            ->when(
                $data->search !== null,
                static function (Builder $q) use ($data): void {
                    $like = '%'.mb_strtolower((string) $data->search).'%';
                    $q->where(static function (Builder $w) use ($like): void {
                        $w->whereRaw('LOWER(scenarios.name) like ?', [$like])
                            ->orWhereRaw('LOWER(scenarios.description) like ?', [$like])
                            ->orWhereRaw('LOWER(scenarios.alias) like ?', [$like]);
                    });
                },
            )
            ->orderBy('name');
    }

    /** @return array<int, array<string, mixed>> */
    private function loadFolders(?string $projectId, ScenarioFeedData $data, int $offset, int $take): array
    {
        $rows = $this->foldersQuery($projectId, $data)
            ->withCount(['children' => static function (Builder $q) use ($projectId): void {
                $q->whereExists(static function (\Illuminate\Database\Query\Builder $sub) use ($projectId): void {
                    $sub->from('model_has_categories')
                        ->whereColumn('model_has_categories.category_id', 'categories.id')
                        ->where('model_has_categories.model_type', Scenario::class);
                    if ($projectId !== null) {
                        $sub->where('model_has_categories.project_id', $projectId);
                    }
                });
            }])
            ->offset($offset)
            ->limit($take)
            ->get();

        return $rows->map(fn (Category $c): array => [
            'type' => 'folder',
            'id' => $c->id,
            'name' => $c->name,
            'parent_id' => $c->parent_id,
            'children_count' => $c->children_count ?? 0,
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function loadItems(?string $projectId, ScenarioFeedData $data, int $offset, int $take): array
    {
        $rows = $this->itemsQuery($projectId, $data)
            ->with(['createdBy', 'updatedBy'])
            ->withCount('versions')
            ->offset($offset)
            ->limit($take)
            ->get();

        return $rows->map(fn (Scenario $s): array => [
            'type' => 'scenario',
            'id' => $s->id,
            'name' => $s->name,
            'alias' => $s->alias,
            'description' => $s->description,
            'status' => $s->status->value,
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
        ])->values()->all();
    }
}
