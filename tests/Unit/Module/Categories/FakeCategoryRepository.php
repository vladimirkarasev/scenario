<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Categories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Module\Categories\Repositories\CategoryRepositoryContract;

/**
 * Фейковый внутренний репозиторий со счётчиками вызовов — для тестов кеширующего декоратора.
 */
final class FakeCategoryRepository implements CategoryRepositoryContract
{
    public int $forModelCalls = 0;

    public int $forModelByParentCalls = 0;

    /** @return Collection<int, Category> */
    public function forModel(string $modelClass, ?string $projectId = null): Collection
    {
        $this->forModelCalls++;

        return Category::hydrate([
            ['id' => 'a', 'name' => 'Alpha', 'parent_id' => null, 'children_count' => 3],
            ['id' => 'b', 'name' => 'Beta', 'parent_id' => 'a', 'children_count' => 0],
        ]);
    }

    /** @return Collection<int, Category> */
    public function forModelByParent(string $modelClass, ?string $parentId, ?string $projectId = null): Collection
    {
        $this->forModelByParentCalls++;

        return Category::hydrate([
            ['id' => 'a', 'name' => 'Alpha', 'parent_id' => null, 'children_count' => 3],
        ]);
    }

    /** @return Collection<int, Category> */
    public function all(): Collection
    {
        return Category::hydrate([]);
    }

    /** @return Collection<int, Category> */
    public function withRelations(): Collection
    {
        return Category::hydrate([]);
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes, ?int $actorId): Category
    {
        return (new Category)->forceFill($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(Category $category, array $attributes, ?int $actorId): Category
    {
        return $category->forceFill($attributes);
    }

    public function delete(Category $category): void
    {
    }

    /** @return array<int, string> */
    public function childIds(string $parentId): array
    {
        return [];
    }

    public function find(string $id): ?Category
    {
        return null;
    }

    public function loadRelations(Category $category): Category
    {
        return $category;
    }
}
