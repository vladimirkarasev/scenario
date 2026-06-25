<?php

declare(strict_types=1);

namespace Module\Actions\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Module\Actions\Events\ActionSaved;
use Module\Actions\Events\EmailSendFailed;
use Module\Actions\Events\EmailSent;
use Module\Actions\Listeners\LogEmailActivity;
use Module\Actions\Listeners\SyncActionToApiListener;
use Module\Actions\Models\Action;
use Module\Actions\Observers\ActionObserver;

final class ActionsServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__).'/routes/api.php');

        Action::observe(ActionObserver::class);

        // Подписчики на сохранение экшена — добавляй сюда новые строки.
        Event::listen(ActionSaved::class, [SyncActionToApiListener::class, 'handle']);

        Event::listen(EmailSent::class, [LogEmailActivity::class, 'handleSent']);
        Event::listen(EmailSendFailed::class, [LogEmailActivity::class, 'handleFailed']);
    }
}
