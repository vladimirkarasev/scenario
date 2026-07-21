<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Exceptions\ForbiddenException;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Categories\DTO\CategoryData;
use Module\Categories\Http\Controllers\CategoryController;
use Module\Categories\Http\Requests\CategoryRequest;
use Module\Categories\Http\Resources\JsonApi\CategoryResource;
use Module\Categories\Services\CategoryService;
use Module\Projects\CurrentProject;
use Module\Scenario\Enums\ScenarioErrorCode;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Repositories\CategoryRepository;

final class ScenarioCategoryController extends CategoryController
{
    public function __construct(
        CategoryService $categories,
        CurrentProject $currentProject,
        private readonly CategoryRepository $scenarioCategories,
    ) {
        parent::__construct($categories, $currentProject);
    }

    protected function modelClass(): string
    {
        return Scenario::class;
    }

    #[\Override]
    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($category->is_system) {
            throw ForbiddenException::from(ScenarioErrorCode::SystemCategoryDeleteForbidden);
        }

        return parent::destroy($request, $category);
    }

    #[\Override]
    public function index(): AnonymousResourceCollection
    {
        $request = request();

        if ($request->boolean('filter.is_workspace')) {
            return CategoryResource::collection(
                $this->scenarioCategories->workspaceForModel($this->modelClass(), $this->currentProjectId()),
            );
        }

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
        $category = $this->categories->create(CategoryData::fromRequest($request));

        $this->scenarioCategories->attachToModelType($category, $this->modelClass(), $this->currentProjectId());
        $this->ensureSingleWorkspace($category);

        return new CategoryResource($category);
    }

    #[\Override]
    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $resource = parent::update($request, $category);

        $this->ensureSingleWorkspace($category->refresh());

        return $resource;
    }

    private function ensureSingleWorkspace(Category $category): void
    {
        if (!$category->is_workspace) {
            return;
        }

        $this->scenarioCategories->demoteOtherWorkspaces($category, $this->modelClass(), $this->currentProjectId());
    }
}
