<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DirectoryItemService;

final readonly class RebuildDirectorySearchTextActivity implements RebuildDirectorySearchTextActivityInterface
{
    public function __construct(
        private DirectoryItemService $itemService,
    ) {
    }

    public function run(int $directoryVersionId): void
    {
        $version = DirectoryVersion::query()->find($directoryVersionId);

        if ($version === null) {
            return;
        }

        $this->itemService->rebuildSearchTextForVersion($version);
    }
}
