<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Module\Directories\Services\DictionaryApiSyncService;

final class SyncDictionariesCommand extends Command
{
    protected $signature = 'dictionaries:sync';

    protected $description = 'Queue due API-backed dictionaries for synchronization';

    public function handle(DictionaryApiSyncService $syncService): int
    {
        $count = $syncService->queueDue();

        $this->info("API dictionaries queued: {$count}");

        return self::SUCCESS;
    }
}
