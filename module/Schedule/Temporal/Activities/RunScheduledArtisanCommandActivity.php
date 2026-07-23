<?php

declare(strict_types=1);

namespace Module\Schedule\Temporal\Activities;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Artisan;
use Module\Schedule\Events\ScheduledArtisanCommandFinished;
use Symfony\Component\Console\Output\BufferedOutput;

final readonly class RunScheduledArtisanCommandActivity implements RunScheduledArtisanCommandActivityInterface
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function run(string $command): void
    {
        $output = new BufferedOutput();
        $exitCode = Artisan::call($command, [], $output);

        $this->events->dispatch(new ScheduledArtisanCommandFinished($command, $exitCode, $output->fetch()));
    }
}
