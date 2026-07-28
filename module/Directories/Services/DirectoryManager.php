<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Module\Directories\DTO\DirectoryImportCommand;
use Module\Directories\DTO\DirectoryPage;
use Module\Directories\DTO\DirectoryQuery;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Services\Handlers\DirectoryHandlerResolver;

final readonly class DirectoryManager
{
    public function __construct(
        private DirectoryHandlerResolver $handlers,
    ) {
    }

    public function paginate(Directory $directory, DirectoryQuery $query): DirectoryPage
    {
        return $this->handlers
            ->resolve($directory->sourceType())
            ->paginate($directory, $query);
    }

    public function import(DirectoryImportCommand $command): DirectoryImport
    {
        return $this->handlers
            ->resolveImporter($command->directory()->sourceType())
            ->import($command);
    }
}
