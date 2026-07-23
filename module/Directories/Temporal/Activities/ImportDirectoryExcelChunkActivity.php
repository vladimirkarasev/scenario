<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Maatwebsite\Excel\Facades\Excel;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Imports\DirectoryExcelChunkImport;
use Module\Directories\Models\DirectoryImport;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;
use Throwable;

final readonly class ImportDirectoryExcelChunkActivity implements ImportDirectoryExcelChunkActivityInterface
{
    public function importChunk(int $directoryImportId, int $startRow, int $chunkSize): void
    {
        $import = DirectoryImport::query()->findOrFail($directoryImportId);

        if ($import->status !== DirectoryImportStatus::Processing->value) {
            return;
        }

        try {
            Excel::import(
                new DirectoryExcelChunkImport($directoryImportId, $startRow, $chunkSize),
                $import->file_path,
                $import->file_disk,
            );
        } catch (Throwable $exception) {
            throw new ApplicationFailure(
                $exception->getMessage(),
                'DirectoryImportChunkFailed',
                false,
                EncodedValues::fromValues([$exception->getMessage()]),
            );
        }
    }
}
