<?php

declare(strict_types=1);

namespace Module\Scenario\Providers;

use App\Http\Middleware\AddApiMeta;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Actions\Events\ScenarioActionStageFinished;
use Module\Scenario\Enums\ScenarioPermission;
use Module\Scenario\Events\ScenarioConditionEvaluated;
use Module\Scenario\Events\ScenarioLinkFollowed;
use Module\Scenario\Events\ScenarioNodeEntered;
use Module\Scenario\Events\ScenarioNodeExited;
use Module\Scenario\Events\ScenarioRunCompleted;
use Module\Scenario\Events\ScenarioRunFailed;
use Module\Scenario\Events\ScenarioRunRewound;
use Module\Scenario\Events\ScenarioRunStarted;
use Module\Scenario\Listeners\RecordScenarioRunHistoryEvent;

final class ScenarioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PermissionRegistry::register(ScenarioPermission::class);

        $this->registerWebRoutes();
        $this->registerApiRoutes();
        $this->registerHistoryListeners();
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

    private function registerHistoryListeners(): void
    {
        Event::listen(ScenarioRunStarted::class, [RecordScenarioRunHistoryEvent::class, 'handleRunStarted']);
        Event::listen(ScenarioRunCompleted::class, [RecordScenarioRunHistoryEvent::class, 'handleRunCompleted']);
        Event::listen(ScenarioRunFailed::class, [RecordScenarioRunHistoryEvent::class, 'handleRunFailed']);
        Event::listen(ScenarioNodeEntered::class, [RecordScenarioRunHistoryEvent::class, 'handleNodeEntered']);
        Event::listen(ScenarioNodeExited::class, [RecordScenarioRunHistoryEvent::class, 'handleNodeExited']);
        Event::listen(ScenarioConditionEvaluated::class, [RecordScenarioRunHistoryEvent::class, 'handleConditionEvaluated']);
        Event::listen(ScenarioLinkFollowed::class, [RecordScenarioRunHistoryEvent::class, 'handleLinkFollowed']);
        Event::listen(ScenarioActionStageFinished::class, [RecordScenarioRunHistoryEvent::class, 'handleActionStageFinished']);
        Event::listen(ScenarioRunRewound::class, [RecordScenarioRunHistoryEvent::class, 'handleRunRewound']);
    }
}
