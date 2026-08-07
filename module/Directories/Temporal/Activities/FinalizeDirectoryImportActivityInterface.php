<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'DirectoryImport.')]
interface FinalizeDirectoryImportActivityInterface
{
    /** @return array{deleted: int} */
    #[ActivityMethod(name: 'CompleteDirectoryImport')]
    public function complete(int $directoryImportId): array;

    /** @return mixed */
    #[ActivityMethod(name: 'FailDirectoryImport')]
    public function fail(int $directoryImportId, string $errorMessage);
}
