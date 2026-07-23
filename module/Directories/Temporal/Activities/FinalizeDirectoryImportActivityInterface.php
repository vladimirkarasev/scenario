<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'DirectoryImport.')]
interface FinalizeDirectoryImportActivityInterface
{
    /** @return mixed */
    #[ActivityMethod(name: 'CompleteDirectoryImport')]
    public function complete(int $directoryImportId);

    /** @return mixed */
    #[ActivityMethod(name: 'FailDirectoryImport')]
    public function fail(int $directoryImportId, string $errorMessage);
}
