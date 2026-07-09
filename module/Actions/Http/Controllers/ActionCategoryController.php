<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Actions\Http\Requests\ActionCategoryIndexRequest;
use Module\Actions\Services\ActionCategoryService;
use Module\Categories\DTO\CategoryActionData;
use Module\Categories\DTO\CategoryData;
use Module\Categories\Http\Requests\CategoryRequest;
use Module\Categories\Http\Resources\JsonApi\CategoryResource;
use Module\Projects\CurrentProject;

final class ActionCategoryController extends Controller
{
    public function __construct(
        private readonly ActionCategoryService $actionCategories,
        private readonly CurrentProject $currentProject,
    ) {}

    public function index(ActionCategoryIndexRequest $request): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            $this->actionCategories->list(
                projectId: $this->currentProject->id(),
                filterByParent: $request->hasParentFilter(),
                parentId: $request->parentId(),
            ),
        );
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource(
            $this->actionCategories->show($category, $this->currentProject->id()),
        );
    }

    public function store(CategoryRequest $request): CategoryResource
    {
        return new CategoryResource(
            $this->actionCategories->create(
                data: CategoryData::fromRequest($request),
                projectId: $this->currentProject->id(),
            ),
        );
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource(
            $this->actionCategories->update(
                data: CategoryData::fromRequest($request),
                category: $category,
                projectId: $this->currentProject->id(),
            ),
        );
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->actionCategories->delete(
            data: CategoryActionData::fromRequest($request),
            category: $category,
            projectId: $this->currentProject->id(),
        );

        return new JsonResponse(status: 204);
    }
}
