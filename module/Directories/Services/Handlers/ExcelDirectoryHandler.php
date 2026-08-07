<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

use Module\Directories\DTO\DirectoryImportCommand;
use Module\Directories\DTO\ExcelDirectoryImportCommand;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Enums\DirectorySourceType;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryCacheRepository;
use Module\Directories\Services\Importing\DirectoryImportCoordinator;

final readonly class ExcelDirectoryHandler extends AbstractStoredDirectoryHandler implements ImportHandler
{
    public function __construct(
        DirectoryCacheRepository $data,
        private DirectoryImportCoordinator $imports,
    ) {
        parent::__construct($data);
    }

    public function type(): DirectorySourceType
    {
        return DirectorySourceType::Excel;
    }

    public function import(DirectoryImportCommand $command): DirectoryImport
    {
        if (!$command instanceof ExcelDirectoryImportCommand) {
            throw new DirectoryImportException('Excel directory import requires one or more files.');
        }

        return $this->imports->queue($command->data);
    }
}
