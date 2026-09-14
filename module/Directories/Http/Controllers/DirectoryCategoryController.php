<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Categories\DTO\CategoryActionData;
use Module\Categories\DTO\CategoryData;
use Module\Categories\Http\Controllers\CategoryController;
use Module\Categories\Http\Requests\CategoryRequest;
use Module\Categories\Http\Resources\JsonApi\CategoryResource;
use Module\Categories\Repositories\CategoryModelBindingRepository;
use Module\Categories\Services\CategoryService;
use Module\Directories\Models\Directory;
use Module\Projects\CurrentProject;

final class DirectoryCategoryController extends CategoryController
{
    public function __construct(
        CategoryService $categories,
        CurrentProject $currentProject,
        private readonly CategoryModelBindingRepository $bindings,
    ) {
        parent::__construct($categories, $currentProject);
    }

    #[\Override]
    public function index(Request $request): AnonymousResourceCollection
    {
        if ($request->has('filter.parent_id')) {
            $raw = $request->input('filter.parent_id');
            $parentId = ($raw === 'null' || $raw === '' || $raw === null)
                ? null
                : (is_string($raw) ? $raw : null);

            return CategoryResource::collection(
                $this->categories->forModelByParent($this->modelClass(), $parentId, $this->currentProjectId()),
            );
        }

        return CategoryResource::collection(
            $this->categories->forModel($this->modelClass(), $this->currentProjectId()),
        );
    }

    #[\Override]
    public function store(CategoryRequest $request): CategoryResource
    {
        $category = $this->categories->create(CategoryData::fromRequest($request, canManageCatalog: true));

        $this->bindings->attach($category, $this->modelClass(), $this->currentProjectId());

        return new CategoryResource($category);
    }

    #[\Override]
    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource(
            $this->categories->update(CategoryData::fromRequest($request, canManageCatalog: true), $category),
        );
    }

    #[\Override]
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->categories->delete(CategoryActionData::fromRequest($request, canManageCatalog: true), $category);

        return new JsonResponse(status: 204);
    }

    protected function modelClass(): string
    {
        return Directory::class;
    }
}
