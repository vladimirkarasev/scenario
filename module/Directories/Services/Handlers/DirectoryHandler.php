<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

use Module\Directories\DTO\DirectoryPage;
use Module\Directories\DTO\DirectoryQuery;
use Module\Directories\Enums\DirectorySourceType;
use Module\Directories\Models\Directory;

interface DirectoryHandler
{
    public function type(): DirectorySourceType;

    public function paginate(Directory $directory, DirectoryQuery $query): DirectoryPage;
}
