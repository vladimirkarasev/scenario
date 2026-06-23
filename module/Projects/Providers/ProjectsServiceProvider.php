<?php

declare(strict_types=1);

namespace Module\Projects\Providers;

use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Projects\CurrentProject;
use Module\Projects\Repositories\ProjectRepository;

final class ProjectsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentProject::class, function (): CurrentProject {
            $user = $this->app->make(AuthFactory::class)->user();

            if (! $user instanceof User || ! $user->sitekey || ! $user->host) {
                return new CurrentProject(null);
            }

            $project = $this->app->make(ProjectRepository::class)
                ->activeBySitekeyAndHost($user->sitekey, $user->host);

            return new CurrentProject($project);
        });
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(dirname(__DIR__).'/routes/web.php');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');
    }
}
