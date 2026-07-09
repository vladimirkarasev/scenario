<?php

declare(strict_types=1);

namespace Module\Users\Providers;

use App\Http\Middleware\AddApiMeta;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Projects\Http\Middleware\RequireCurrentProject;
use Module\Projects\Http\Middleware\ScopeUserToCurrentProject;
use Module\Users\Enums\RolePermission;
use Module\Users\Enums\UserPermission;
use Module\Users\Events\UserCreated;
use Module\Users\Events\UserDeleted;
use Module\Users\Events\UserUpdated;
use Module\Users\Events\SecurityEvent;
use Module\Users\Listeners\LogUserAudit;
use Module\Users\Listeners\LogSecurityAudit;
use Module\Projects\Models\Project;
use Module\Users\Services\ProjectUserCleanupService;

final class UsersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PermissionRegistry::register(UserPermission::class, RolePermission::class);

        Event::listen(UserCreated::class, [LogUserAudit::class, 'created']);
        Event::listen(UserUpdated::class, [LogUserAudit::class, 'updated']);
        Event::listen(UserDeleted::class, [LogUserAudit::class, 'deleted']);
        Event::listen(SecurityEvent::class, LogSecurityAudit::class);

        Project::deleting(static function (Project $project): void {
            app(ProjectUserCleanupService::class)->handle($project);
        });

        Route::middleware('web')
            ->group(dirname(__DIR__).'/routes/web.php');

        Route::middleware([
            'api',
            'auth:sanctum',
            RequireCurrentProject::class,
            ScopeUserToCurrentProject::class,
            AddApiMeta::class,
        ])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');

        Route::middleware(['api', 'auth:sanctum', AddApiMeta::class])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/account.php');
    }
}
