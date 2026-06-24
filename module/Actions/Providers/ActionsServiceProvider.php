<?php

declare(strict_types=1);

namespace Module\Actions\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Module\Actions\Events\EmailSendFailed;
use Module\Actions\Events\EmailSent;
use Module\Actions\Listeners\LogEmailActivity;

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

        Event::listen(EmailSent::class, [LogEmailActivity::class, 'handleSent']);
        Event::listen(EmailSendFailed::class, [LogEmailActivity::class, 'handleFailed']);
    }
}
