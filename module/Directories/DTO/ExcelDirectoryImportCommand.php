<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Module\Directories\Models\Directory;

final readonly class ExcelDirectoryImportCommand implements DirectoryImportCommand
{
    public function __construct(public ExcelImportData $data)
    {
    }

    public function directory(): Directory
    {
        return $this->data->directory;
    }
}
