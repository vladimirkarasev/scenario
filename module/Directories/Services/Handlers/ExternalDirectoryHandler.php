<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

use Module\Directories\DTO\DirectoryPage;
use Module\Directories\DTO\DirectoryQuery;
use Module\Directories\Enums\DirectorySourceType;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryExternalDataService;

final readonly class ExternalDirectoryHandler implements DirectoryHandler
{
    public function __construct(
        private DirectoryExternalDataService $data,
    ) {
    }

    public function type(): DirectorySourceType
    {
        return DirectorySourceType::External;
    }

    public function paginate(Directory $directory, DirectoryQuery $query): DirectoryPage
    {
        return $this->data->activeData($directory, $query);
    }
}
