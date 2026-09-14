<?php

declare(strict_types=1);

namespace Module\Scenario\QueryBuilders;

use App\Models\Category;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Module\Scenario\DTO\ScenarioFeedData;
use Module\Scenario\Models\Scenario;

final readonly class ScenarioFeedQueryBuilder
{
    /**
     * @param  list<string>|null  $subtreeIds
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function paginate(?string $projectId, ScenarioFeedData $data, ?array $subtreeIds): LengthAwarePaginator
    {
        $folders = $this->folders($projectId, $data, $subtreeIds)
            ->toBase()
            ->select(['categories.id', 'categories.name'])
            ->selectRaw("'folder' as item_type");

        $scenarios = $this->scenarios($projectId, $data, $subtreeIds)
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
     * @param  list<string>|null  $subtreeIds
     * @return Builder<Category>
     */
    public function folders(?string $projectId, ScenarioFeedData $data, ?array $subtreeIds): Builder
    {
        $descendantIds = $subtreeIds !== null
            ? array_values(array_filter($subtreeIds, static fn(string $id): bool => $id !== $data->rootId))
            : null;

        return Category::query()
            ->when($data->parentSet && $data->parentId === null, static fn(Builder $query) => $query->whereNull('parent_id'))
            ->when(
                $data->parentSet && $data->parentId !== null,
                static fn(Builder $query) => $query->where('parent_id', $data->parentId),
            )
            ->when(
                $descendantIds !== null,
                static fn(Builder $query) => $query->whereIn('categories.id', $descendantIds ?? []),
            )
            ->whereExists($this->boundToScenarios($projectId))
            ->when(
                $data->search !== null,
                static fn(Builder $query) => $query->whereRaw(
                    'LOWER(categories.name) like ?',
                    ['%'.mb_strtolower((string) $data->search).'%'],
                ),
            )
            ->orderBy('name');
    }

    /**
     * @param  list<string>|null  $subtreeIds
     * @return ScenarioBuilder
     */
    public function scenarios(?string $projectId, ScenarioFeedData $data, ?array $subtreeIds): ScenarioBuilder
    {
        return Scenario::query()
            ->when($projectId !== null, static fn(ScenarioBuilder $query) => $query->where('project_id', $projectId))
            ->when(
                $subtreeIds !== null,
                static fn(ScenarioBuilder $query) => $query->whereHas(
                    'categories',
                    static fn(Builder $subquery) => $subquery->whereIn('categories.id', $subtreeIds ?? []),
                ),
            )
            ->when(
                $data->parentSet && $data->parentId !== null,
                static fn(ScenarioBuilder $query) => $query->whereHas(
                    'categories',
                    static fn(Builder $subquery) => $subquery->where('categories.id', $data->parentId),
                ),
            )
            ->when(
                $data->parentSet && $data->parentId === null,
                static fn(ScenarioBuilder $query) => $query->whereDoesntHave('categories'),
            )
            ->when(
                $data->status !== null,
                static fn(ScenarioBuilder $query) => $query->where('scenarios.status', $data->status),
            )
            ->when(
                $data->excludeScenarioId !== null,
                static fn(ScenarioBuilder $query) => $query->where('scenarios.id', '!=', $data->excludeScenarioId),
            )
            ->search($data->search)
            ->orderBy('name');
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, Category>
     */
    public function foldersByIds(array $ids, ?string $projectId): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<string, Category> */
        return Category::query()
            ->withCount([
                'children' => fn(Builder $query) => $query->whereExists($this->boundToScenarios($projectId)),
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
    public function scenariosByIds(array $ids): array
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

    /** @return array<string, array{name: string, parent_id: string|null}> */
    public function categoryMap(?string $projectId): array
    {
        return Category::query()
            ->whereExists($this->boundToScenarios($projectId))
            ->get(['id', 'name', 'parent_id'])
            ->mapWithKeys(static fn(Category $category): array => [
                $category->id => ['name' => $category->name, 'parent_id' => $category->parent_id],
            ])
            ->all();
    }

    private function boundToScenarios(?string $projectId): Closure
    {
        return static function (QueryBuilder $query) use ($projectId): void {
            $query->from('model_has_categories')
                ->whereColumn('model_has_categories.category_id', 'categories.id')
                ->where('model_has_categories.model_type', Scenario::class);

            if ($projectId !== null) {
                $query->where('model_has_categories.project_id', $projectId);
            }
        };
    }
}
