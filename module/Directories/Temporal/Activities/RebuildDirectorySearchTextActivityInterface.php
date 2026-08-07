<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'DirectorySearchText.')]
interface RebuildDirectorySearchTextActivityInterface
{
    /** @return mixed */
    #[ActivityMethod(name: 'RebuildDirectorySearchText')]
    public function run(int $directoryVersionId);
}
