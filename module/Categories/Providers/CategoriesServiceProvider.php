<?php

declare(strict_types=1);

namespace Module\Categories\Providers;

use App\Support\PermissionRegistry;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Module\Categories\Enums\CategoryPermission;
use Module\Categories\Repositories\CachedCategoryRepository;
use Module\Categories\Repositories\CategoryRepository;
use Module\Categories\Repositories\CategoryRepositoryContract;

final class CategoriesServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // Разделы читаются часто и почти не меняются — отдаём кеширующий декоратор
        // поверх конкретного репозитория (явная сборка, чтобы не было цикла резолвинга).
        $this->app->bind(
            CategoryRepositoryContract::class,
            static fn (Application $app): CachedCategoryRepository => new CachedCategoryRepository(
                $app->make(CategoryRepository::class),
                $app->make(CacheRepository::class),
            )
        );
    }

    public function boot(): void
    {
        PermissionRegistry::register(CategoryPermission::class);

        $this->loadRoutesFrom(dirname(__DIR__).'/routes/api.php');
    }
}
