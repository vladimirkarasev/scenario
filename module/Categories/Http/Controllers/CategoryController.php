<?php

declare(strict_types=1);

namespace Module\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Categories\DTO\CategoryActionData;
use Module\Categories\DTO\CategoryData;
use Module\Categories\Http\Requests\CategoryRequest;
use Module\Categories\Http\Resources\JsonApi\CategoryResource;
use Module\Categories\Services\CategoryService;
use Module\Projects\CurrentProject;

abstract class CategoryController extends Controller
{
    public function __construct(
        protected readonly CategoryService $categories,
        protected readonly CurrentProject $currentProject,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            $this->categories->forModel($this->modelClass()),
        );
    }

    public function show(Category $category): CategoryResource
    {
        $this->categories->loadRelations($category);

        return new CategoryResource($category);
    }

    public function store(CategoryRequest $request): CategoryResource
    {
        return new CategoryResource(
            $this->categories->create(CategoryData::fromRequest($request)),
        );
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource(
            $this->categories->update(CategoryData::fromRequest($request), $category),
        );
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->categories->delete(CategoryActionData::fromRequest($request), $category);

        return new JsonResponse(status: 204);
    }

    protected function currentProjectId(): ?string
    {
        return $this->currentProject->id();
    }

    /** @return class-string */
    abstract protected function modelClass(): string;
}
