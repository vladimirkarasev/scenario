<?php

declare(strict_types=1);

namespace Module\Directories\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Module\Directories\Services\ImportService;

final readonly class DirectoryExcelChunkImport implements SkipsEmptyRows, ToCollection, WithChunkReading, WithHeadingRow, WithLimit,
                                                  WithStartRow
{
    public function __construct(
        private int $directoryImportId,
        private int $startRow,
        private int $chunkSize,
    ) {
    }

    /** @param  Collection<int, mixed>  $collection */
    public function collection(Collection $collection): void
    {
        /** @var Collection<int, array<string, mixed>> $typedRows */
        $typedRows = $collection;

        app(ImportService::class)->importChunk(
            directoryImportId: $this->directoryImportId,
            rows: $typedRows,
            baseRowNumber: $this->startRow,
        );
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function startRow(): int
    {
        return $this->startRow;
    }

    public function limit(): int
    {
        return $this->startRow + $this->chunkSize - 1;
    }
}
