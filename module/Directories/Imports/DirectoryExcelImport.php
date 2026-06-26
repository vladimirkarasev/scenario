<?php

declare(strict_types=1);

namespace Module\Directories\Imports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Module\Directories\Services\ImportService;
use Throwable;

final class DirectoryExcelImport implements ShouldQueue, SkipsEmptyRows, ToCollection, WithChunkReading, WithEvents,
                                            WithHeadingRow
{
    use Importable;
    use RemembersChunkOffset;

    public function __construct(
        private readonly int $directoryImportId,
        private readonly int $chunkSize,
    ) {
    }

    /** @param  Collection<int, mixed>  $collection */
    public function collection(Collection $collection): void
    {
        $baseRowNumber = $this->headingRow() + 1 + $this->getChunkOffset();

        /** @var Collection<int, array<string, mixed>> $typedRows */
        $typedRows = $collection;

        $this->importService()->importChunk(
            directoryImportId: $this->directoryImportId,
            rows: $typedRows,
            baseRowNumber: $baseRowNumber,
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

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterImport::class => fn(): bool => $this->importService()->complete($this->directoryImportId),
            ImportFailed::class => function (ImportFailed $event): void {
                $this->importService()->fail(
                    directoryImportId: $this->directoryImportId,
                    exception: $event->getException(),
                );
            },
        ];
    }

    public function failed(Throwable $exception): void
    {
        $this->importService()->fail(
            directoryImportId: $this->directoryImportId,
            exception: $exception,
        );
    }

    private function importService(): ImportService
    {
        // Резолвим в момент выполнения: объект импорта сериализуется при постановке
        // chunk-job в очередь, поэтому хранить контейнер/сервис в свойстве нельзя.
        return app(ImportService::class);
    }
}
