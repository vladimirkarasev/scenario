<?php

declare(strict_types=1);

namespace Module\Schedule\Services;

use Generator;
use Module\Schedule\Support\TemporalTaskQueue;
use Module\Schedule\Temporal\Workflows\RunScheduledArtisanCommandWorkflowInterface;

final readonly class SystemScheduleSyncer
{
    private const string SCOPE = 'system';

    public function __construct(
        private ScheduleService $schedules,
        private TemporalTaskQueue $taskQueue,
    ) {
    }

    public function syncAll(): void
    {
        foreach ($this->definitions() as $definition) {
            $this->schedules->upsert($definition->withTaskQueue($this->taskQueue->value()));
        }
    }

    /**
     * @return Generator<int, ScheduleDefinition>
     */
    private function definitions(): Generator
    {
        yield ScheduleDefinition::new(
            scope: self::SCOPE,
            subjectId: 'directories-run-scheduled-imports',
            workflowType: RunScheduledArtisanCommandWorkflowInterface::WORKFLOW_TYPE
        )
            ->everyMinute()
            ->withWorkflowInput(['directories:run-scheduled-imports']);

        yield ScheduleDefinition::new(
            scope: self::SCOPE,
            subjectId: 'dictionaries-sync',
            workflowType: RunScheduledArtisanCommandWorkflowInterface::WORKFLOW_TYPE
        )
            ->everyMinute()
            ->withWorkflowInput(['dictionaries:sync']);

        yield ScheduleDefinition::new(
            scope: self::SCOPE,
            subjectId: 'dictionaries-warmup-cache',
            workflowType: RunScheduledArtisanCommandWorkflowInterface::WORKFLOW_TYPE
        )
            ->hourly()
            ->withWorkflowInput(['dictionaries:warmup-cache']);

        yield ScheduleDefinition::new(
            scope: self::SCOPE,
            subjectId: 'embed-auth-prune-tokens',
            workflowType: RunScheduledArtisanCommandWorkflowInterface::WORKFLOW_TYPE
        )
            ->hourly()
            ->withWorkflowInput(['embed-auth:prune-tokens']);
    }
}
