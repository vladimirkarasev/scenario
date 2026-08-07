<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use InvalidArgumentException;

final readonly class StoredExcelFile implements ExcelImportFiles
{
    public function __construct(
        public string $disk,
        public string $path,
    ) {
        if ($disk === '' || $path === '') {
            throw new InvalidArgumentException('Stored Excel file requires disk and path.');
        }
    }
}
