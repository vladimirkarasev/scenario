<?php

declare(strict_types=1);

namespace Module\Directories\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Module\Directories\Services\DictionaryApiSyncService;

final class SyncDictionaryFromApiJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $directoryImportId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(DictionaryApiSyncService $syncService): void
    {
        $syncService->runImport($this->directoryImportId);
    }
}
