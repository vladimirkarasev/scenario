<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'DirectoryImport.')]
interface PrepareDirectoryImportActivityInterface
{
    /**
     * @return array{headingRow: int, firstDataRow: int, chunkSize: int, totalRows: int}
     */
    #[ActivityMethod(name: 'PrepareDirectoryImport')]
    public function prepare(int $directoryImportId): array;
}
