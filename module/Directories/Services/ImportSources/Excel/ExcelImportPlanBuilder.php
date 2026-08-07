<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources\Excel;

use Illuminate\Support\Facades\Storage;
use Module\Directories\DTO\ExcelFilePlan;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class ExcelImportPlanBuilder
{
    public function build(string $disk, string $path): ExcelFilePlan
    {
        $reader = IOFactory::createReaderForFile(Storage::disk($disk)->path($path));
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load(Storage::disk($disk)->path($path));
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [];
        $rows = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1');
        $firstRow = is_array($rows[0] ?? null) ? $rows[0] : [];

        foreach ($firstRow as $value) {
            if (is_scalar($value) || $value === null) {
                $headers[] = trim((string)$value);
            }
        }

        return new ExcelFilePlan(
            disk: $disk,
            path: $path,
            firstDataRow: 2,
            totalRows: $sheet->getHighestDataRow(),
            headers: $headers,
        );
    }
}
