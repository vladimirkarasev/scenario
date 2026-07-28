<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Module\Directories\Enums\DirectoryImportSourceType;

interface DirectoryImportSource
{
    public function type(): DirectoryImportSourceType;
}
