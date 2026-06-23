<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Module\Actions\Services\ActionScheduleService;

final class RunScheduledActionsCommand extends Command
{
    protected $signature = 'actions:run-scheduled';

    protected $description = 'Run due scheduled actions';

    public function handle(ActionScheduleService $scheduleService): int
    {
        $count = $scheduleService->runDue();

        $this->info("Scheduled actions queued: {$count}");

        return self::SUCCESS;
    }
}
