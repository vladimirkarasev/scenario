<?php

declare(strict_types=1);

namespace Module\Actions\Listeners;

use Module\Actions\Events\ActionSaved;
use Module\Actions\Jobs\SyncActionToApiJob;

final readonly class SyncActionToApiListener
{
    public function handle(ActionSaved $event): void
    {
        SyncActionToApiJob::dispatch($event->actionId);
    }
}
