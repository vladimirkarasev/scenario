<?php

declare(strict_types=1);

namespace Module\Groups\Providers;

use App\Http\Middleware\AddApiMeta;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Groups\Enums\GroupPermission;
use Module\Groups\Http\Middleware\ScopeGroupToCurrentProject;
use Module\Projects\Http\Middleware\RequireCurrentProject;

final class GroupsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PermissionRegistry::register(GroupPermission::class);

        $this->registerApiRoutes();
    }

    private function registerApiRoutes(): void
    {
        Route::middleware([
            'api',
            'auth:sanctum',
            RequireCurrentProject::class,
            ScopeGroupToCurrentProject::class,
            AddApiMeta::class,
        ])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');
    }
}
