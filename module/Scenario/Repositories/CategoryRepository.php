<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use App\Models\Category;
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
            ->workspaceOnly()
            ->boundToModelType($modelType, $projectId)
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
            ->excluding($category->id)
            ->workspaceOnly()
            ->boundToModelType($modelType, $projectId, matchNullProject: true)
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
