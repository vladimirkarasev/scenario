<?php

declare(strict_types=1);

namespace Module\Scenario\QueryBuilders;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Module\Scenario\DTO\CatalogItemRow;
use Module\Scenario\Models\Scenario;

final class CategoryBuilder
{
    private bool $activeOnly = false;

    private ?string $parentId = null;

    private ?string $search = null;

    /** @var Collection<int, string>|null */
    private ?Collection $activeCategoryIds = null;

    /** @var Collection<int, string>|null */
    private ?Collection $parentCategoryIds = null;

    private ?string $parentTreeId = null;

    private bool $hasParentFilter = false;

    /**
     * Список id групп для фильтрации workspace.
     * null = фильтр не применяется (admin / bypass);
     * [] = ничего не видно;
     * [...] = видны только сценарии, у которых пересечение групп с этим списком.
     *
     * @var list<string>|null
     */
    private ?array $visibleByGroupIds = null;

    private function __construct()
    {
    }

    public static function query(): self
    {
        return new self;
    }

    public function activeOnly(bool $activeOnly = true): self
    {
        $this->activeOnly = $activeOnly;

        return $this;
    }

    /** @param  Collection<int, string>|null  $categoryIds */
    public function activeCategoryIds(?Collection $categoryIds): self
    {
        $this->activeCategoryIds = $categoryIds;

        return $this;
    }

    public function filterByParentId(?string $parentId): self
    {
        $this->parentId = $parentId;
        $this->hasParentFilter = true;

        return $this;
    }

    /** @param  Collection<int, string>  $parentCategoryIds */
    public function filterByParentCategoryIds(Collection $parentCategoryIds): self
    {
        $this->parentCategoryIds = $parentCategoryIds->values();

        return $this;
    }

    public function filterByParentTree(string $parentId): self
    {
        $this->parentTreeId = $parentId;

        return $this;
    }

    public function search(?string $search): self
    {
        $this->search = $search;

        return $this;
    }

    /**
     * Применить фильтр workspace: видны только сценарии в одной из этих групп.
     * Передать null — фильтр выключен (admin); пустой массив — пользователь без групп, ничего не видит.
     *
     * @param  list<string>|null  $groupIds
     */
    public function visibleByGroups(?array $groupIds): self
    {
        $this->visibleByGroupIds = $groupIds;

        return $this;
    }

    /** @return Collection<int, CatalogItemRow> */
    public function get(): Collection
    {
        /** @var Collection<int, object> $rows */
        $rows = $this->orderedQuery()->get();

        return $rows
            ->map(static fn(object $row): CatalogItemRow => CatalogItemRow::fromRow($row))
            ->values();
    }

    /** @return LengthAwarePaginator<int, CatalogItemRow> */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, CatalogItemRow> $paginator */
        $paginator = $this->orderedQuery()->paginate($perPage);
        $paginator->getCollection()->transform(
            static fn(object $row): CatalogItemRow => CatalogItemRow::fromRow($row),
        );

        return $paginator;
    }

    public function toBase(): QueryBuilder
    {
        return DB::query()->fromSub($this->unionQuery(), 'catalog_items');
    }

    private function orderedQuery(): QueryBuilder
    {
        return $this->toBase()
            ->orderByRaw("case when item_type = 'category' then 0 else 1 end")
            ->orderBy('name');
    }

    private function unionQuery(): QueryBuilder
    {
        return $this->categoryItemsQuery()->union($this->scenarioItemsQuery());
    }

    private function categoryItemsQuery(): QueryBuilder
    {
        $query = Category::query()
            ->from('categories as catalog_categories')
            ->selectRaw("'category' as item_type")
            ->selectRaw('catalog_categories.id as category_id')
            ->selectRaw('null as scenario_id')
            ->selectRaw('catalog_categories.name')
            ->selectRaw('catalog_categories.is_active')
            ->selectRaw('null as active_version_id')
            ->selectRaw('catalog_categories.parent_id')
            ->selectRaw($this->categoryPathSql('catalog_categories.parent_id').' as path')
            ->selectRaw('0 as child_count')
            ->selectRaw('0 as scenario_count')
            ->selectRaw('0 as version_count');

        if ($this->activeOnly) {
            $query->where('catalog_categories.is_active', true);
        }

        if ($this->activeCategoryIds !== null) {
            $this->activeCategoryIds->isEmpty()
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('catalog_categories.id', $this->activeCategoryIds->all());
        }

        $this->applyCategoryParentFilter($query);
        $this->applySearch($query, 'catalog_categories.name', null);

        return $query->toBase();
    }

    private function scenarioItemsQuery(): QueryBuilder
    {
        $query = Scenario::query()
            ->selectRaw("'scenario' as item_type")
            ->selectRaw('null as category_id')
            ->selectRaw('scenarios.id as scenario_id')
            ->selectRaw('scenarios.name')
            ->selectRaw('scenarios.is_active')
            ->selectRaw('scenarios.active_version_id');

        $this->applyScenarioParentFilter($query);
        $this->applySearch($query, 'scenarios.name', 'scenarios.description');
        $this->applyScenarioVisibilityFilter($query);

        $query
            ->selectRaw('0 as child_count')
            ->selectRaw('0 as scenario_count')
            ->selectRaw('0 as version_count');

        return $query->toBase();
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     */
    private function applyScenarioVisibilityFilter(EloquentBuilder $query): void
    {
        if ($this->visibleByGroupIds === null) {
            return;
        }

        if ($this->visibleByGroupIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $groupIds = $this->visibleByGroupIds;
        $query->whereExists(static function (\Illuminate\Database\Query\Builder $b) use ($groupIds): void {
            $b->selectRaw('1')
                ->from('model_has_groups')
                ->whereColumn('model_has_groups.model_id', 'scenarios.id')
                ->whereIn('model_has_groups.group_id', $groupIds)
                ->where('model_has_groups.model_type', Scenario::class);
        });
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     */
    private function applyCategoryParentFilter(EloquentBuilder $query): void
    {
        if ($this->parentTreeId !== null) {
            $this->whereInParentTree($query, 'catalog_categories.parent_id', $this->parentTreeId);

            return;
        }

        if ($this->parentCategoryIds !== null) {
            $this->parentCategoryIds->isEmpty()
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('catalog_categories.parent_id', $this->parentCategoryIds->all());

            return;
        }

        if (!$this->hasParentFilter) {
            return;
        }

        $this->parentId === null
            ? $query->whereNull('catalog_categories.parent_id')
            : $query->where('catalog_categories.parent_id', $this->parentId);
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     */
    private function applyScenarioParentFilter(EloquentBuilder $query): void
    {
        if ($this->parentTreeId !== null) {
            $this->onlyScenariosInParentTree($query, $this->parentTreeId);

            return;
        }

        if ($this->parentCategoryIds !== null) {
            $this->onlyScenariosInCategories($query, $this->parentCategoryIds);

            return;
        }

        if (!$this->hasParentFilter) {
            $this->withOptionalScenarioCategory($query);

            return;
        }

        if ($this->parentId === null) {
            $this->onlyRootScenarios($query);

            return;
        }

        $this->onlyScenariosInCategory($query, $this->parentId);
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     */
    private function withOptionalScenarioCategory(EloquentBuilder $query): void
    {
        $query
            ->selectRaw('model_has_categories.category_id as parent_id')
            ->selectRaw($this->scenarioPathSql().' as path')
            ->leftJoin('model_has_categories', static function (JoinClause $join): void {
                $join
                    ->on('model_has_categories.model_id', '=', 'scenarios.id')
                    ->where('model_has_categories.model_type', Scenario::class);
            });
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     */
    private function onlyRootScenarios(EloquentBuilder $query): void
    {
        $query
            ->selectRaw('null as parent_id')
            ->selectRaw("'[]'::jsonb as path")
            ->whereNotExists(static function (QueryBuilder $builder): void {
                $builder
                    ->selectRaw('1')
                    ->from('model_has_categories')
                    ->whereColumn('model_has_categories.model_id', 'scenarios.id')
                    ->where('model_has_categories.model_type', Scenario::class);
            });
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     */
    private function onlyScenariosInCategory(EloquentBuilder $query, string $parentId): void
    {
        $this->onlyScenariosInCategories($query, collect([$parentId]));
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     * @param  Collection<int, string>  $parentCategoryIds
     */
    private function onlyScenariosInCategories(EloquentBuilder $query, Collection $parentCategoryIds): void
    {
        $query
            ->selectRaw('model_has_categories.category_id as parent_id')
            ->selectRaw($this->scenarioPathSql().' as path')
            ->join('model_has_categories', static function (JoinClause $join): void {
                $join
                    ->on('model_has_categories.model_id', '=', 'scenarios.id')
                    ->where('model_has_categories.model_type', Scenario::class);
            })
            ->when(
                $parentCategoryIds->isEmpty(),
                static fn(EloquentBuilder $builder): EloquentBuilder => $builder->whereRaw('1 = 0'),
                static fn(EloquentBuilder $builder): EloquentBuilder => $builder->whereIn(
                    'model_has_categories.category_id',
                    $parentCategoryIds->all(),
                ),
            );
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     */
    private function onlyScenariosInParentTree(EloquentBuilder $query, string $parentId): void
    {
        $query
            ->selectRaw('model_has_categories.category_id as parent_id')
            ->selectRaw($this->scenarioPathSql().' as path')
            ->join('model_has_categories', static function (JoinClause $join): void {
                $join
                    ->on('model_has_categories.model_id', '=', 'scenarios.id')
                    ->where('model_has_categories.model_type', Scenario::class);
            });

        $this->whereInParentTree($query, 'model_has_categories.category_id', $parentId);
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     * @param  literal-string  $column
     */
    private function whereInParentTree(EloquentBuilder $query, string $column, string $parentId): void
    {
        $query->whereRaw(
            "{$column} in (
                with recursive category_tree(id) as (
                    select id from categories where id = ?
                    union all
                    select categories.id
                    from categories
                    inner join category_tree on categories.parent_id = category_tree.id
                )
                select id from category_tree
            )",
            [$parentId],
        );
    }

    /**
     * @param  literal-string  $categoryIdColumn
     * @return literal-string
     */
    private function categoryPathSql(string $categoryIdColumn): string
    {
        return "coalesce((
            with recursive category_path(id, parent_id, ids, names) as (
                select categories.id, categories.parent_id, array[categories.id::text], array[categories.name::text]
                from categories
                where categories.id = {$categoryIdColumn}
                union all
                select
                    parent.id,
                    parent.parent_id,
                    array_prepend(parent.id::text, category_path.ids),
                    array_prepend(parent.name::text, category_path.names)
                from categories as parent
                inner join category_path on category_path.parent_id = parent.id
            )
            select jsonb_agg(jsonb_build_object('id', path_items.id, 'name', path_items.name))
            from unnest(
                coalesce((
                    select category_path.ids
                    from category_path
                    where category_path.parent_id is null
                    limit 1
                ), array[]::text[]),
                coalesce((
                    select category_path.names
                    from category_path
                    where category_path.parent_id is null
                    limit 1
                ), array[]::text[])
            ) as path_items(id, name)
        ), '[]'::jsonb)";
    }

    /** @return literal-string */
    private function scenarioPathSql(): string
    {
        return "case
            when model_has_categories.category_id is null then '[]'::jsonb
            else {$this->categoryPathSql('model_has_categories.category_id')}
        end";
    }

    /**
     * @template T of Model
     * @param  EloquentBuilder<T>  $query
     * @param  literal-string  $nameColumn
     * @param  literal-string|null  $descriptionColumn
     */
    private function applySearch(EloquentBuilder $query, string $nameColumn, ?string $descriptionColumn): void
    {
        if ($this->search === null || $this->search === '') {
            return;
        }

        $like = '%'.mb_strtolower($this->search).'%';

        $query->where(static function (EloquentBuilder $builder) use ($like, $nameColumn, $descriptionColumn): void {
            $builder->whereRaw("LOWER({$nameColumn}) like ?", [$like]);

            if ($descriptionColumn !== null) {
                $builder->orWhereRaw("LOWER({$descriptionColumn}) like ?", [$like]);
            }
        });
    }
}
