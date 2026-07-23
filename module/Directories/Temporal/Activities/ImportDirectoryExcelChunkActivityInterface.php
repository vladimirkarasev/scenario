<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'DirectoryImport.')]
interface ImportDirectoryExcelChunkActivityInterface
{
    /** @return mixed */
    #[ActivityMethod(name: 'ImportDirectoryExcelChunk')]
    public function importChunk(int $directoryImportId, int $startRow, int $chunkSize);
}
