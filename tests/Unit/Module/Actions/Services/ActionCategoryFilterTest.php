<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Actions\DTO\ActionIndexData;
use Module\Actions\Models\Action;
use Module\Actions\Repositories\ActionRepository;
use Tests\TestCase;

/**
 * Фильтрация списка экшенов по разделу (категории) и сохранение связи через categories()->sync.
 */
final class ActionCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private ActionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ActionRepository::class);
    }

    public function test_paginate_filters_actions_by_category(): void
    {
        $category = $this->makeCategory('CRM');
        $inSection = $this->makeAction('in-section');
        $this->makeAction('out-of-section');

        $inSection->categories()->sync([$category->id]);

        $page = $this->repository->paginate($this->filters([$category->id]));

        $this->assertCount(1, $page->items());
        $this->assertSame($inSection->id, $page->items()[0]->id);
    }

    public function test_paginate_without_category_filter_returns_all(): void
    {
        $this->makeAction('a');
        $this->makeAction('b');

        $page = $this->repository->paginate($this->filters([]));

        $this->assertCount(2, $page->items());
    }

    public function test_category_filter_isolated_by_model_type(): void
    {
        // Категория того же id, но привязанная к другому model_type (как у справочника),
        // не должна "цеплять" экшен — sync пишет model_type = Action::class.
        $category = $this->makeCategory('Shared');
        $action = $this->makeAction('act');
        $action->categories()->sync([$category->id]);

        $rows = DB::table('model_has_categories')
            ->where('category_id', $category->id)
            ->where('model_id', $action->id)
            ->where('model_type', Action::class)
            ->count();

        $this->assertSame(1, $rows);
    }

    /** @param  list<string>  $categoryIds */
    private function filters(array $categoryIds): ActionIndexData
    {
        return new ActionIndexData(
            search: null,
            type: null,
            isActive: null,
            categoryIds: $categoryIds,
            perPage: 20,
        );
    }

    private function makeCategory(string $name): Category
    {
        return Category::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'parent_id' => null,
            'is_active' => true,
        ]);
    }

    private function makeAction(string $slug): Action
    {
        return Action::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'code' => $slug,
            'type' => 'template_file',
            'is_active' => true,
            'config' => [],
        ]);
    }
}
