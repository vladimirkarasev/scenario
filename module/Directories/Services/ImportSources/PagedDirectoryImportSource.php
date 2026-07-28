<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Module\Directories\DTO\DirectoryImportPage;
use Module\Directories\Models\DirectoryImport;

interface PagedDirectoryImportSource extends DirectoryImportSource
{
    /**
     * Fetches and extracts one page of rows. Does not persist anything itself —
     * the caller (Temporal activity) is responsible for running the rows through
     * {@see \Module\Directories\Services\Importing\DirectoryImportCoordinator::importChunk()}.
     *
     */
    public function fetchPage(DirectoryImport $import, int $page): DirectoryImportPage;
}
