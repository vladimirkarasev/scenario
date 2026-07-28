<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

use Module\Directories\Enums\DirectorySourceType;

final readonly class ManualDirectoryHandler extends AbstractStoredDirectoryHandler
{
    public function type(): DirectorySourceType
    {
        return DirectorySourceType::Manual;
    }
}
