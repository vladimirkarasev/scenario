<?php

declare(strict_types=1);

namespace Module\Directories\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Module\Directories\Services\ImportService;

final class ImportDirectoryJob implements ShouldQueue
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

    public function handle(ImportService $importService): void
    {
        $importService->start($this->directoryImportId);
    }
}
