<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use App\Models\Category;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CategoryRepository
{
    /**
     * @return Collection<int, Category>
     */
    public function workspaceForModel(string $modelType, ?string $projectId): Collection
    {
        return Category::query()
            ->where('is_workspace', true)
            ->whereExists(static function (QueryBuilder $q) use ($modelType, $projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', $modelType);
                if ($projectId !== null) {
                    $q->where('model_has_categories.project_id', $projectId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function attachToModelType(Category $category, string $modelType, ?string $projectId): void
    {
        DB::table('model_has_categories')->insertOrIgnore([
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => $modelType,
            'project_id' => $projectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function demoteOtherWorkspaces(Category $category, string $modelType, ?string $projectId): void
    {
        Category::query()
            ->where('categories.id', '!=', $category->id)
            ->where('is_workspace', true)
            ->whereExists(static function (QueryBuilder $q) use ($modelType, $projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', $modelType);

                $projectId === null
                    ? $q->whereNull('model_has_categories.project_id')
                    : $q->where('model_has_categories.project_id', $projectId);
            })
            ->update(['is_workspace' => false]);
    }

    /** @return Collection<int, Category> */
    public function orderedForCatalog(): Collection
    {
        return Category::query()
            ->with(['createdBy', 'updatedBy', 'parent'])
            ->withCount('scenarios')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<string, Category> */
    public function idParentPairs(): Collection
    {
        return Category::query()
            ->get(['id', 'parent_id'])
            ->keyBy('id');
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes, ?int $actorId): Category
    {
        $category = new Category($attributes);
        $category->created_by = $actorId;
        $category->updated_by = $actorId;
        $category->save();

        return $category;
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(Category $category, array $attributes, ?int $actorId): Category
    {
        $category->fill($attributes);
        $category->updated_by = $actorId;
        $category->save();

        return $category;
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    /** @return list<string> */
    public function childIds(string $parentId): array
    {
        return array_values(
            Category::query()
                ->where('parent_id', $parentId)
                ->pluck('id')
                ->map(static fn(mixed $id): string => is_scalar($id) ? (string)$id : '')
                ->all()
        );
    }

    public function find(string $id): ?Category
    {
        return Category::query()->find($id);
    }

    public function loadPayloadRelations(Category $category): Category
    {
        return $category->load(['createdBy', 'updatedBy', 'parent'])->loadCount('scenarios');
    }
}
