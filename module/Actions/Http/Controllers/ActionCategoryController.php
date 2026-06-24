<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Module\Actions\Models\Action;
use Module\Categories\DTO\CategoryData;
use Module\Categories\Http\Controllers\CategoryController;
use Module\Categories\Http\Requests\CategoryRequest;
use Module\Categories\Http\Resources\JsonApi\CategoryResource;

final class ActionCategoryController extends CategoryController
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
        $category = $this->categories->create(CategoryData::fromRequest($request));

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
    public function destroy(Request $request, Category $category): JsonResponse
    {
        // Системный раздел (напр. «Синхронизация справочников») удалять нельзя.
        abort_if($category->is_system, 403, 'Системный раздел нельзя удалить.');

        return parent::destroy($request, $category);
    }

    protected function modelClass(): string
    {
        return Action::class;
    }
}
