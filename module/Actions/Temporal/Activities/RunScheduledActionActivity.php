<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Activities;

use Module\Actions\Models\Action;
use Module\Actions\Models\ActionSchedule;
use Module\Actions\Services\ActionOrchestratorService;
use Module\Actions\Services\ActionScheduleService;

final readonly class RunScheduledActionActivity implements RunScheduledActionActivityInterface
{
    public function __construct(
        private ActionOrchestratorService $orchestrator,
        private ActionScheduleService $scheduleService,
    ) {}

    public function run(int $scheduleId): void
    {
        $schedule = ActionSchedule::query()->with('action')->find($scheduleId);

        if ($schedule === null || !$schedule->enabled) {
            return;
        }

        $action = $schedule->action;

        if ($action instanceof Action && $action->is_active) {
            $this->orchestrator->runFromData($this->scheduleService->runDataForSchedule($schedule, $action));
        }

        $now = now();

        $schedule->forceFill([
            'last_run_at' => $now,
            'next_run_at' => $this->scheduleService->nextRunAt($schedule, $now),
        ])->save();
    }
}
