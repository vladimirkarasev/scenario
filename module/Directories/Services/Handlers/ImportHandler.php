<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

use Module\Directories\DTO\DirectoryImportCommand;
use Module\Directories\Models\DirectoryImport;

interface ImportHandler
{
    public function import(DirectoryImportCommand $command): DirectoryImport;
}
