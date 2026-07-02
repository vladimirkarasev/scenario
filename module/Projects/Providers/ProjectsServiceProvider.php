<?php

declare(strict_types=1);

namespace Module\Projects\Providers;

use App\Models\PersonalRefreshToken;
use App\Support\PermissionRegistry;
use Module\Projects\Enums\ProjectPermission;
use Module\Users\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Module\Projects\CurrentProject;
use Module\Projects\Models\Project;
use Module\Projects\Repositories\ProjectRepository;

final class ProjectsServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->scoped(CurrentProject::class, function (): CurrentProject {
            $user = $this->app->make(AuthFactory::class)->user();

            if (!$user instanceof User) {
                return new CurrentProject(null);
            }

            $projects = $this->app->make(ProjectRepository::class);

            // 1. Embed-доступ: проект привязан к access-токену, а не к юзеру.
            $bearer = $this->app->make(Request::class)->bearerToken();
            if (is_string($bearer)) {
                $token = PersonalAccessToken::findToken($bearer);

                if ($token !== null) {
                    $projectId = PersonalRefreshToken::query()
                        ->where('personal_access_token_id', $token->getKey())
                        ->value('project_id');

                    if (is_string($projectId)) {
                        $project = $projects->activeById($projectId);
                        if ($project instanceof Project) {
                            return new CurrentProject($project);
                        }
                    }
                }
            }

            // 2. Системный пользователь проекта: проект задан прямо на юзере.
            //    Так системник, ходящий по своему API-токену, скоупится на свой проект.
            if (is_string($user->project_id) && $user->project_id !== '') {
                $project = $projects->activeById($user->project_id);
                if ($project instanceof Project) {
                    return new CurrentProject($project);
                }
            }

            // 3. Фолбэк: стандартный web-доступ по sitekey/host пользователя.
            if ($user->sitekey && $user->host) {
                return new CurrentProject($projects->activeBySitekeyAndHost($user->sitekey, $user->host));
            }

            return new CurrentProject(null);
        });
    }

    public function boot(): void
    {
        PermissionRegistry::register(ProjectPermission::class);

        Route::middleware('web')
            ->group(dirname(__DIR__).'/routes/web.php');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');
    }
}
