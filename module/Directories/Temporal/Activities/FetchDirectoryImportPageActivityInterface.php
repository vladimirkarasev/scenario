<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'DirectoryImport.')]
interface FetchDirectoryImportPageActivityInterface
{
    /**
     * @return array{hasMore: bool}
     */
    #[ActivityMethod(name: 'FetchDirectoryImportPage')]
    public function fetchPage(int $directoryImportId, int $page): array;
}
