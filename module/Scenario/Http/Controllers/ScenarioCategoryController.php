<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Module\Categories\DTO\CategoryData;
use Module\Categories\Http\Controllers\CategoryController;
use Module\Categories\Http\Requests\CategoryRequest;
use Module\Categories\Http\Resources\JsonApi\CategoryResource;
use Module\Scenario\Models\Scenario;

final class ScenarioCategoryController extends CategoryController
{
    protected function modelClass(): string
    {
        return Scenario::class;
    }

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
}
