<?php

declare(strict_types=1);

namespace Module\Schedule\Events;

final readonly class ScheduledArtisanCommandFinished
{
    public function __construct(
        public string $command,
        public int $exitCode,
        public string $output,
    ) {}
}
