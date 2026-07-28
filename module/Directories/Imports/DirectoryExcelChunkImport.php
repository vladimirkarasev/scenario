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

final class DirectoryExcelChunkImport implements SkipsEmptyRows, ToCollection, WithChunkReading, WithHeadingRow, WithLimit,
                                                  WithStartRow
{
    /** @var Collection<int, array<string, mixed>> */
    public Collection $rows;

    public function __construct(
        private readonly int $startRow,
        private readonly int $chunkSize,
    ) {
        /** @var Collection<int, array<string, mixed>> $empty */
        $empty = collect();
        $this->rows = $empty;
    }

    /** @param  Collection<int, mixed>  $collection */
    public function collection(Collection $collection): void
    {
        /** @var Collection<int, array<string, mixed>> $typedRows */
        $typedRows = $collection;

        $this->rows = $this->rows->concat($typedRows);
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
