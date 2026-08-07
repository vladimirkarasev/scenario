<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

use Module\Directories\DTO\DirectoryImportCommand;
use Module\Directories\DTO\ApiDirectoryImportCommand;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Enums\DirectorySourceType;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryCacheRepository;
use Module\Directories\Services\DictionaryApiSyncService;

final readonly class ApiDirectoryHandler extends AbstractStoredDirectoryHandler implements ImportHandler
{
    public function __construct(
        DirectoryCacheRepository $data,
        private DictionaryApiSyncService $sync,
    ) {
        parent::__construct($data);
    }

    public function type(): DirectorySourceType
    {
        return DirectorySourceType::Api;
    }

    public function import(DirectoryImportCommand $command): DirectoryImport
    {
        if (!$command instanceof ApiDirectoryImportCommand) {
            throw new DirectoryImportException('API directory import requires API sync command.');
        }

        return $this->sync->queue(
            $command->directoryModel,
            $command->userId,
            $command->options,
        );
    }
}
