<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Module\Directories\Services\DirectoryImportScheduleService;

final class RunScheduledDirectoryImportsCommand extends Command
{
    protected $signature = 'directories:run-scheduled-imports';

    protected $description = 'Run due scheduled remote directory imports';

    public function handle(DirectoryImportScheduleService $scheduleService): int
    {
        $count = $scheduleService->runDue();

        $this->info("Scheduled directory imports queued: {$count}");

        return self::SUCCESS;
    }
}
