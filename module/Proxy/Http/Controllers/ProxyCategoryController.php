<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Module\Categories\DTO\CategoryActionData;
use Module\Categories\DTO\CategoryData;
use Module\Categories\Http\Controllers\CategoryController;
use Module\Categories\Http\Requests\CategoryRequest;
use Module\Categories\Http\Resources\JsonApi\CategoryResource;
use Module\Proxy\Enums\ProxyErrorCode;
use Module\Proxy\Models\ProxyEndpoint;

final class ProxyCategoryController extends CategoryController
{
    #[\Override]
    public function index(): AnonymousResourceCollection
    {
        $request = request();

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

        DB::table('model_has_categories')->insertOrIgnore([
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => $this->modelClass(),
            'project_id' => $this->currentProjectId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
        $belongsToProject = DB::table('model_has_categories')
            ->where('category_id', $category->id)
            ->where('model_type', $this->modelClass())
            ->where('project_id', $this->currentProjectId())
            ->exists();

        if (!$belongsToProject) {
            throw NotFoundException::from(ProxyErrorCode::CategoryNotFound);
        }
    }
}
