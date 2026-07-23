<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Schedule\Temporal;

use Illuminate\Support\Facades\Event;
use Module\Schedule\Events\ScheduledArtisanCommandFinished;
use Module\Schedule\Temporal\Activities\RunScheduledArtisanCommandActivity;
use Tests\TestCase;

final class RunScheduledArtisanCommandActivityTest extends TestCase
{
    public function test_run_dispatches_finished_event_with_command_output(): void
    {
        Event::fake(ScheduledArtisanCommandFinished::class);

        app(RunScheduledArtisanCommandActivity::class)->run('inspire');

        Event::assertDispatched(
            ScheduledArtisanCommandFinished::class,
            static fn(ScheduledArtisanCommandFinished $event): bool => $event->command === 'inspire'
                && $event->exitCode === 0
                && trim($event->output) !== '',
        );
    }
}
