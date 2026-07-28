<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

use Module\Directories\DTO\DirectoryPage;
use Module\Directories\DTO\DirectoryQuery;
use Module\Directories\Models\Directory;
use Module\Directories\Repositories\DirectoryCacheRepository;

abstract readonly class AbstractStoredDirectoryHandler implements DirectoryHandler
{
    public function __construct(
        private DirectoryCacheRepository $data,
    ) {
    }

    public function paginate(Directory $directory, DirectoryQuery $query): DirectoryPage
    {
        return $this->data->activeData($directory, $query);
    }
}
