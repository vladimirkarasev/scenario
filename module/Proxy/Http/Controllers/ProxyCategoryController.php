<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
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
use Module\Projects\CurrentProject;
use Module\Proxy\Enums\ProxyErrorCode;
use Module\Proxy\Models\ProxyEndpoint;

final class ProxyCategoryController extends CategoryController
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
    public function show(Category $category): CategoryResource
    {
        $this->assertInCurrentProject($category);

        return new CategoryResource($this->categories->loadRelations($category));
    }

    #[\Override]
    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $this->assertInCurrentProject($category);

        return new CategoryResource(
            $this->categories->update(CategoryData::fromRequest($request, canManageCatalog: true), $category),
        );
    }

    #[\Override]
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->assertInCurrentProject($category);
        if ($category->is_system) {
            throw ForbiddenException::from(ProxyErrorCode::SystemCategoryDeleteForbidden);
        }

        $this->categories->delete(CategoryActionData::fromRequest($request, canManageCatalog: true), $category);

        return new JsonResponse(status: 204);
    }

    protected function modelClass(): string
    {
        return ProxyEndpoint::class;
    }

    private function assertInCurrentProject(Category $category): void
    {
        $belongsToProject = $this->bindings->exists(
            $category,
            $this->modelClass(),
            $this->currentProjectId(),
        );

        if (!$belongsToProject) {
            throw NotFoundException::from(ProxyErrorCode::CategoryNotFound);
        }
    }
}
