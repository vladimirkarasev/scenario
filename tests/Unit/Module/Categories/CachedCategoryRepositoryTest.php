<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Categories;

use App\Models\Category;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Module\Categories\Repositories\CachedCategoryRepository;
use Tests\TestCase;

final class CachedCategoryRepositoryTest extends TestCase
{
    public function test_for_model_result_is_cached(): void
    {
        $inner = $this->fakeRepository();
        $cached = new CachedCategoryRepository($inner, $this->arrayCache());

        $first = $cached->forModel(Category::class);
        $second = $cached->forModel(Category::class);

        $this->assertSame(1, $inner->forModelCalls);
        $this->assertCount(2, $first);
        $this->assertCount(2, $second);
    }

    public function test_different_arguments_use_separate_cache_entries(): void
    {
        $inner = $this->fakeRepository();
        $cached = new CachedCategoryRepository($inner, $this->arrayCache());

        $cached->forModel(Category::class, 'project-1');
        $cached->forModel(Category::class, 'project-2');
        $cached->forModel(Category::class, 'project-1');

        $this->assertSame(2, $inner->forModelCalls);
    }

    public function test_for_model_by_parent_is_cached(): void
    {
        $inner = $this->fakeRepository();
        $cached = new CachedCategoryRepository($inner, $this->arrayCache());

        $cached->forModelByParent(Category::class, null);
        $cached->forModelByParent(Category::class, null);

        $this->assertSame(1, $inner->forModelByParentCalls);
    }

    public function test_cached_models_preserve_attributes(): void
    {
        $inner = $this->fakeRepository();
        $cached = new CachedCategoryRepository($inner, $this->arrayCache());

        $cached->forModel(Category::class);
        $result = $cached->forModel(Category::class);

        $first = $result->first();
        $this->assertNotNull($first);
        $this->assertSame('Alpha', $first->name);
        $this->assertSame(3, $first->children_count);
    }

    public function test_create_invalidates_cache(): void
    {
        $inner = $this->fakeRepository();
        $cached = new CachedCategoryRepository($inner, $this->arrayCache());

        $cached->forModel(Category::class);
        $cached->create(['name' => 'New'], null);
        $cached->forModel(Category::class);

        $this->assertSame(2, $inner->forModelCalls);
    }

    public function test_update_invalidates_cache(): void
    {
        $inner = $this->fakeRepository();
        $cached = new CachedCategoryRepository($inner, $this->arrayCache());

        $cached->forModel(Category::class);
        $cached->update(new Category, ['name' => 'Upd'], null);
        $cached->forModel(Category::class);

        $this->assertSame(2, $inner->forModelCalls);
    }

    public function test_delete_invalidates_cache(): void
    {
        $inner = $this->fakeRepository();
        $cached = new CachedCategoryRepository($inner, $this->arrayCache());

        $cached->forModel(Category::class);
        $cached->delete(new Category);
        $cached->forModel(Category::class);

        $this->assertSame(2, $inner->forModelCalls);
    }

    private function arrayCache(): CacheRepository
    {
        return new CacheRepository(new ArrayStore);
    }

    private function fakeRepository(): FakeCategoryRepository
    {
        return new FakeCategoryRepository;
    }
}
