<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Module\Directories\Services\ImportService;

final readonly class PrepareDirectoryImportActivity implements PrepareDirectoryImportActivityInterface
{
    public function __construct(
        private ImportService $importService,
    ) {}

    public function prepare(int $directoryImportId): array
    {
        return $this->importService->prepareExcelImport($directoryImportId);
    }
}
