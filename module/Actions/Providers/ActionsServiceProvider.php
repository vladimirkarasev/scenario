<?php

declare(strict_types=1);

namespace Module\Actions\Providers;

use App\Http\Middleware\AddApiMeta;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Actions\Enums\ActionPermission;
use Module\Actions\Events\ActionSaved;
use Module\Actions\Events\EmailSendFailed;
use Module\Actions\Events\EmailSent;
use Module\Actions\Listeners\LogEmailActivity;
use Module\Actions\Listeners\SyncActionToApiListener;
use Module\Actions\Models\Action;
use Module\Actions\Observers\ActionObserver;
use Module\Actions\Temporal\ActionScheduleSyncer;
use Module\Actions\Temporal\ActionScheduleSyncerInterface;
use Module\Actions\Temporal\RunActionsWorkflowStarter;
use Module\Actions\Temporal\RunActionsWorkflowStarterInterface;

final class ActionsServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(RunActionsWorkflowStarterInterface::class, RunActionsWorkflowStarter::class);
        $this->app->bind(ActionScheduleSyncerInterface::class, ActionScheduleSyncer::class);
    }

    public function boot(): void
    {
        PermissionRegistry::register(ActionPermission::class);

        Route::middleware(AddApiMeta::class)
            ->group(dirname(__DIR__).'/routes/api.php');

        Action::observe(ActionObserver::class);

        Event::listen(ActionSaved::class, [SyncActionToApiListener::class, 'handle']);

        Event::listen(EmailSent::class, [LogEmailActivity::class, 'handleSent']);
        Event::listen(EmailSendFailed::class, [LogEmailActivity::class, 'handleFailed']);
    }
}
