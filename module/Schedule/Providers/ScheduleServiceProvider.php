<?php

declare(strict_types=1);

namespace Module\Schedule\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Module\Schedule\Events\ScheduledArtisanCommandFinished;
use Module\Schedule\Listeners\LogScheduledArtisanCommandFinished;
use Module\Schedule\Services\TemporalScheduleSyncer;
use Module\Schedule\Services\TemporalScheduleSyncerInterface;
use Module\Schedule\Support\TemporalTaskQueue;

final class ScheduleServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(TemporalScheduleSyncerInterface::class, TemporalScheduleSyncer::class);

        $this->app->singleton(TemporalTaskQueue::class, static function (): TemporalTaskQueue {
            $queue = config('roadrunner.temporal.defaultWorker');

            return new TemporalTaskQueue(is_string($queue) && $queue !== '' ? $queue : 'default');
        });
    }

    public function boot(): void
    {
        Event::listen(ScheduledArtisanCommandFinished::class, [LogScheduledArtisanCommandFinished::class, 'handle']);
    }
}
