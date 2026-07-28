<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources\Excel;

use Maatwebsite\Excel\Facades\Excel;
use Module\Directories\DTO\ExcelFilePlan;
use Module\Directories\Imports\DirectoryExcelChunkImport;

final class ExcelPageReader
{
    public function read(ExcelFilePlan $plan, int $page, int $chunkSize): DirectoryExcelChunkImport
    {
        $startRow = $plan->firstDataRow + ($page - 1) * $chunkSize;
        $import = new DirectoryExcelChunkImport($startRow, $chunkSize);
        Excel::import($import, $plan->path, $plan->disk);

        return $import;
    }
}
