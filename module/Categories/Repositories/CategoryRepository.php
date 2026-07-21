<?php

declare(strict_types=1);

namespace Module\Categories\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class CategoryRepository implements CategoryRepositoryContract
{
    /** @return Collection<int, Category> */
    public function all(): Collection
    {
        return Category::query()
            ->with(['parent'])
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Category> */
    public function withRelations(): Collection
    {
        return Category::query()
            ->with(['createdBy', 'updatedBy', 'parent'])
            ->withCount('scenarios')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  class-string              $modelClass
     * @return Collection<int, Category>
     */
    public function forModel(string $modelClass, ?string $projectId = null): Collection
    {
        return Category::query()
            ->with(['parent'])
            ->withCount([
                'children' => static function (Builder $q) use ($modelClass, $projectId): void {
                    $q->whereExists(
                        static function (\Illuminate\Database\Query\Builder $sub) use ($modelClass, $projectId): void {
                            $sub->from('model_has_categories')
                                ->whereColumn('model_has_categories.category_id', 'categories.id')
                                ->where('model_has_categories.model_type', $modelClass);
                            if ($projectId !== null) {
                                $sub->where('model_has_categories.project_id', $projectId);
                            }
                        }
                    );
                },
            ])
            ->whereExists(static function (\Illuminate\Database\Query\Builder $q) use ($modelClass, $projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', $modelClass);
                if ($projectId !== null) {
                    $q->where('model_has_categories.project_id', $projectId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  class-string              $modelClass
     * @return Collection<int, Category>
     */
    public function forModelByParent(string $modelClass, ?string $parentId, ?string $projectId = null): Collection
    {
        return Category::query()
            ->withCount([
                'children' => static function (Builder $q) use ($modelClass, $projectId): void {
                    $q->whereExists(
                        static function (\Illuminate\Database\Query\Builder $sub) use ($modelClass, $projectId): void {
                            $sub->from('model_has_categories')
                                ->whereColumn('model_has_categories.category_id', 'categories.id')
                                ->where('model_has_categories.model_type', $modelClass);
                            if ($projectId !== null) {
                                $sub->where('model_has_categories.project_id', $projectId);
                            }
                        }
                    );
                },
            ])
            ->whereExists(static function (\Illuminate\Database\Query\Builder $q) use ($modelClass, $projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', $modelClass);
                if ($projectId !== null) {
                    $q->where('model_has_categories.project_id', $projectId);
                }
            })
            ->when($parentId === null, static fn ($q) => $q->whereNull('parent_id'))
            ->when($parentId !== null, static fn ($q) => $q->where('parent_id', $parentId))
            ->orderBy('name')
            ->get();
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

    /** @return array<int, string> */
    public function childIds(string $parentId): array
    {
        return Category::query()
            ->where('parent_id', $parentId)
            ->pluck('id')
            ->map(static fn (mixed $id): string => is_string($id) ? $id : '')
            ->values()
            ->all();
    }

    public function find(string $id): ?Category
    {
        return Category::query()->find($id);
    }

    public function loadRelations(Category $category): Category
    {
        return $category->load(['createdBy', 'updatedBy', 'parent', 'groups'])->loadCount('scenarios');
    }
}
