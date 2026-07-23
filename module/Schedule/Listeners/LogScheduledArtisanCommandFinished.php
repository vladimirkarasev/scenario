<?php

declare(strict_types=1);

namespace Module\Schedule\Listeners;

use Module\Schedule\Events\ScheduledArtisanCommandFinished;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class LogScheduledArtisanCommandFinished
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(ScheduledArtisanCommandFinished $event): void
    {
        try {
            $this->logger->info('Scheduled artisan command finished', [
                'command' => $event->command,
                'exit_code' => $event->exitCode,
                'output' => $event->output,
            ]);
        } catch (Throwable) {
        }
    }
}
