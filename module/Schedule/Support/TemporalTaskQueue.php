<?php

declare(strict_types=1);

namespace Module\Schedule\Support;

/**
 * The task queue this app's Temporal worker listens on (`TEMPORAL_TASK_QUEUE` env,
 * `config/roadrunner.php`'s `temporal.defaultWorker`). Bound as a singleton in
 * {@see \Module\Schedule\Providers\ScheduleServiceProvider} and injected wherever code
 * starts a workflow or creates a Temporal Schedule, so a second project sharing the same
 * Temporal cluster only needs to change one env var.
 */
final readonly class TemporalTaskQueue
{
    public function __construct(
        private string $value,
    ) {}

    public function value(): string
    {
        return $this->value;
    }
}
