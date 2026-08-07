<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final readonly class UploadedExcelFiles implements ExcelImportFiles
{
    /** @param list<UploadedFile> $files */
    public function __construct(public array $files)
    {
        if ($files === []) {
            throw new InvalidArgumentException('At least one Excel file is required.');
        }
    }
}
