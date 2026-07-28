<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Module\Directories\Models\Directory;

interface DirectoryImportCommand
{
    public function directory(): Directory;
}
