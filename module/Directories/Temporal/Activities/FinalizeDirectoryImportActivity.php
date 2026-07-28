<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Module\Directories\Services\Importing\DirectoryImportCoordinator;
use RuntimeException;

final readonly class FinalizeDirectoryImportActivity implements FinalizeDirectoryImportActivityInterface
{
    public function __construct(
        private DirectoryImportCoordinator $importService,
    ) {}

    /** @return array{deleted: int} */
    public function complete(int $directoryImportId): array
    {
        return ['deleted' => $this->importService->complete($directoryImportId)];
    }

    public function fail(int $directoryImportId, string $errorMessage): void
    {
        $this->importService->fail($directoryImportId, new RuntimeException($errorMessage));
    }
}
