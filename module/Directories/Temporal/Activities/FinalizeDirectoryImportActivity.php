<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Module\Directories\Services\ImportService;
use RuntimeException;

final readonly class FinalizeDirectoryImportActivity implements FinalizeDirectoryImportActivityInterface
{
    public function __construct(
        private ImportService $importService,
    ) {}

    public function complete(int $directoryImportId): void
    {
        $this->importService->complete($directoryImportId);
    }

    public function fail(int $directoryImportId, string $errorMessage): void
    {
        $this->importService->fail($directoryImportId, new RuntimeException($errorMessage));
    }
}
