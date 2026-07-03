<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Module\Actions\Enums\ActionErrorCode;
use Module\Actions\Models\Action;
use Module\Categories\DTO\CategoryActionData;
use Module\Categories\DTO\CategoryData;
use Module\Categories\Services\CategoryService;

final readonly class ActionCategoryService
{
    public function __construct(
        private CategoryService $categories,
    ) {}

    /** @return Collection<int, Category> */
    public function list(?string $projectId, bool $filterByParent, ?string $parentId): Collection
    {
        if ($filterByParent) {
            return $this->categories->forModelByParent(Action::class, $parentId, $projectId);
        }

        return $this->categories->forModel(Action::class, $projectId);
    }

    public function create(CategoryData $data, ?string $projectId): Category
    {
        return DB::transaction(function () use ($data, $projectId): Category {
            $category = $this->categories->create($data);

            DB::table('model_has_categories')->insertOrIgnore([
                'category_id' => $category->id,
                'model_id' => $category->id,
                'model_type' => Action::class,
                'project_id' => $projectId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $category;
        });
    }

    public function show(Category $category, ?string $projectId): Category
    {
        $this->ensureBelongsToProject($category, $projectId);

        return $this->categories->loadRelations($category);
    }

    public function update(CategoryData $data, Category $category, ?string $projectId): Category
    {
        $this->ensureBelongsToProject($category, $projectId);

        return $this->categories->update($data, $category);
    }

    public function delete(CategoryActionData $data, Category $category, ?string $projectId): void
    {
        $this->ensureBelongsToProject($category, $projectId);

        if ($category->is_system) {
            throw ForbiddenException::from(ActionErrorCode::SystemCategoryDeleteForbidden);
        }

        $this->categories->delete($data, $category);
    }

    private function ensureBelongsToProject(Category $category, ?string $projectId): void
    {
        $belongsToProject = DB::table('model_has_categories')
            ->where('category_id', $category->id)
            ->where('model_type', Action::class)
            ->where('project_id', $projectId)
            ->exists();

        if (! $belongsToProject) {
            throw NotFoundException::from(ActionErrorCode::CategoryNotFound);
        }
    }
}
