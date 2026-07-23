<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Module\Schedule\Services\SystemScheduleSyncer;

final class SyncSystemSchedulesCommand extends Command
{
    protected $signature = 'schedules:sync';

    protected $description = 'Create/update the Temporal Schedules for system maintenance commands';

    public function handle(SystemScheduleSyncer $syncer): int
    {
        $syncer->syncAll();

        $this->info('System schedules synced.');

        return self::SUCCESS;
    }
}
