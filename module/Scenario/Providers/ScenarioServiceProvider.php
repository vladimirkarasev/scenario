<?php

declare(strict_types=1);

namespace Module\Scenario\Providers;

use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Scenario\Enums\ScenarioPermission;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Observers\ScenarioVersionObserver;

final class ScenarioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PermissionRegistry::register(ScenarioPermission::class);

        ScenarioVersion::observe(ScenarioVersionObserver::class);

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
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');
    }
}
