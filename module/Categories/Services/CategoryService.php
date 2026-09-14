<?php

declare(strict_types=1);

namespace Module\Categories\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Module\Categories\DTO\CategoryActionData;
use Module\Categories\DTO\CategoryData;
use Module\Categories\Enums\CategoryErrorCode;
use Module\Categories\Repositories\CategoryRepositoryContract;

final readonly class CategoryService
{
    public function __construct(
        private CategoryRepositoryContract $categories,
        private CategoryGroupsCascadeService $cascade,
    ) {}

    /** @return Collection<int, Category> */
    public function all(): Collection
    {
        return $this->categories->withRelations();
    }

    /**
     * @param  class-string              $modelClass
     * @return Collection<int, Category>
     */
    public function forModel(string $modelClass, ?string $projectId = null): Collection
    {
        return $this->categories->forModel($modelClass, $projectId);
    }

    /**
     * @param  class-string              $modelClass
     * @return Collection<int, Category>
     */
    public function forModelByParent(string $modelClass, ?string $parentId, ?string $projectId = null): Collection
    {
        return $this->categories->forModelByParent($modelClass, $parentId, $projectId);
    }

    public function loadRelations(Category $category): Category
    {
        return $this->categories->loadRelations($category);
    }

    public function create(CategoryData $data): Category
    {
        $this->ensureManageAccess($data->canManageCatalog);

        $category = $this->categories->create($data->toAttributes(), $data->actorId);
        $this->syncGroups($category, $data);

        return $this->categories->loadRelations($category);
    }

    public function update(CategoryData $data, Category $category): Category
    {
        $this->ensureManageAccess($data->canManageCatalog);
        $this->ensureNoCycle($data, $category);

        $category = $this->categories->update($category, $data->toAttributes(), $data->actorId);
        $this->syncGroups($category, $data);

        return $this->categories->loadRelations($category);
    }

    public function delete(CategoryActionData $data, Category $category): void
    {
        $this->ensureManageAccess($data->canManageCatalog);

        $this->categories->delete($category);
    }

    private function syncGroups(Category $category, CategoryData $data): void
    {
        $category->groups()->sync($data->groupIds);

        if ($data->inheritToDescendants) {
            $this->cascade->applyToDescendants($category, $data->groupIds);
        }
    }

    private function ensureNoCycle(CategoryData $data, Category $category): void
    {
        $parentId = $data->parentId;

        if ($parentId === null) {
            return;
        }

        if (in_array($parentId, $this->descendantIds($category), true)) {
            throw ConflictException::from(CategoryErrorCode::ParentCycle);
        }
    }

    /** @return array<int, string> */
    private function descendantIds(Category $category): array
    {
        $children = $this->categories->childIds($category->id);
        $ids = $children;

        foreach ($children as $childId) {
            $child = $this->categories->find($childId);
            if ($child !== null) {
                $ids = array_merge($ids, $this->descendantIds($child));
            }
        }

        return array_values(array_unique($ids));
    }

    private function ensureManageAccess(bool $canManageCatalog): void
    {
        if (! $canManageCatalog) {
            throw ForbiddenException::from(CategoryErrorCode::ManageForbidden);
        }
    }
}
