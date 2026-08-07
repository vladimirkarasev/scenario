<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Module\Directories\Models\Directory;

final readonly class ApiDirectoryImportCommand implements DirectoryImportCommand
{
    public function __construct(
        public Directory $directoryModel,
        public ?int $userId = null,
        public ?DirectoryImportOptions $options = null,
    ) {
    }

    public function directory(): Directory
    {
        return $this->directoryModel;
    }
}
