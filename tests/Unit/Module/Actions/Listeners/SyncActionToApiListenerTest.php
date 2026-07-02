<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Listeners;

use Illuminate\Support\Facades\Queue;
use Module\Actions\Events\ActionSaved;
use Module\Actions\Jobs\SyncActionToApiJob;
use Module\Actions\Listeners\SyncActionToApiListener;
use Tests\TestCase;

/**
 * SyncActionToApiListener на событие ActionSaved диспатчит SyncActionToApiJob.
 */
final class SyncActionToApiListenerTest extends TestCase
{
    public function test_handle_dispatches_sync_job(): void
    {
        Queue::fake();

        (new SyncActionToApiListener)->handle(new ActionSaved('action-123'));

        Queue::assertPushed(
            SyncActionToApiJob::class,
            fn (SyncActionToApiJob $job): bool => $job->actionId === 'action-123',
        );
    }
}
