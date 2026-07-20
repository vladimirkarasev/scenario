<?php

declare(strict_types=1);

namespace Module\Directories\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DirectoryItemService;

final class RebuildDirectorySearchTextJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly int $directoryVersionId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(DirectoryItemService $itemService): void
    {
        $version = DirectoryVersion::query()->find($this->directoryVersionId);

        if ($version === null) {
            return;
        }

        $itemService->rebuildSearchTextForVersion($version);
    }
}
