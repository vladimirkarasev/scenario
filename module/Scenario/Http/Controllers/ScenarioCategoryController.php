<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Системную папку (Workspace) удалять нельзя.
     */
    #[\Override]
    public function destroy(Request $request, Category $category): JsonResponse
    {
        abort_if($category->is_system, 403, 'Системную папку нельзя удалить.');

        return parent::destroy($request, $category);
    }

    #[\Override]
    public function index(): AnonymousResourceCollection
    {
        $request = request();

        if ($request->boolean('filter.is_workspace')) {
            $projectId = $this->currentProjectId();

            $workspace = Category::query()
                ->where('is_workspace', true)
                ->whereExists(function (\Illuminate\Database\Query\Builder $q) use ($projectId): void {
                    $q->from('model_has_categories')
                        ->whereColumn('model_has_categories.category_id', 'categories.id')
                        ->where('model_has_categories.model_type', $this->modelClass());
                    if ($projectId !== null) {
                        $q->where('model_has_categories.project_id', $projectId);
                    }
                })
                ->orderBy('name')
                ->get();

            return CategoryResource::collection($workspace);
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

        DB::table('model_has_categories')->insertOrIgnore([
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => $this->modelClass(),
            'project_id' => $this->currentProjectId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

    /**
     * Рабочая папка в проекте одна: при включении флага снимаем его с остальных
     * разделов сценариев того же проекта.
     */
    private function ensureSingleWorkspace(Category $category): void
    {
        if (!$category->is_workspace) {
            return;
        }

        $projectId = $this->currentProjectId();

        Category::query()
            ->where('categories.id', '!=', $category->id)
            ->where('is_workspace', true)
            ->whereExists(function (\Illuminate\Database\Query\Builder $q) use ($projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', $this->modelClass());

                $projectId === null
                    ? $q->whereNull('model_has_categories.project_id')
                    : $q->where('model_has_categories.project_id', $projectId);
            })
            ->update(['is_workspace' => false]);
    }
}
