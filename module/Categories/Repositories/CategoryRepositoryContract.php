<?php

declare(strict_types=1);

namespace Module\Categories\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryContract
{
    /** @return Collection<int, Category> */
    public function all(): Collection;

    /** @return Collection<int, Category> */
    public function withRelations(): Collection;

    /**
     * @param  class-string              $modelClass
     * @return Collection<int, Category>
     */
    public function forModel(string $modelClass, ?string $projectId = null): Collection;

    /**
     * @param  class-string              $modelClass
     * @return Collection<int, Category>
     */
    public function forModelByParent(string $modelClass, ?string $parentId, ?string $projectId = null): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, ?int $actorId): Category;

    /** @param array<string, mixed> $attributes */
    public function update(Category $category, array $attributes, ?int $actorId): Category;

    public function delete(Category $category): void;

    /** @return array<int, string> */
    public function childIds(string $parentId): array;

    public function find(string $id): ?Category;

    public function loadRelations(Category $category): Category;
}
