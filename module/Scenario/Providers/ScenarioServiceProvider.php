<?php

declare(strict_types=1);

namespace Module\Scenario\Providers;

use App\Http\Middleware\AddApiMeta;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Scenario\Enums\ScenarioPermission;

final class ScenarioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PermissionRegistry::register(ScenarioPermission::class);

        $this->registerWebRoutes();
        $this->registerApiRoutes();
    }

    private function registerWebRoutes(): void
    {
        Route::middleware('web')
            ->group(dirname(__DIR__).'/routes/web.php');
    }

    private function registerApiRoutes(): void
    {
        Route::middleware(['api', 'auth:sanctum', AddApiMeta::class])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');
    }
}
