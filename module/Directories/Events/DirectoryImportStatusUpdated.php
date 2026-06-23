<?php

declare(strict_types=1);

namespace Module\Directories\Events;

use Illuminate\Support\Str;
use Module\Directories\Models\DirectoryImport;

final readonly class DirectoryImportStatusUpdated
{
    public function __construct(
        public int $importId,
        public string $directoryId,
        public ?int $directoryVersionId,
        public string $status,
        public int $processedRows,
        public int $importedRows,
        public int $failedRows,
        public ?string $errorMessage,
    ) {}

    public static function fromImport(DirectoryImport $import): self
    {
        return new self(
            importId: $import->id,
            directoryId: (string) $import->directory_id,
            directoryVersionId: $import->directory_version_id === null ? null : (int) $import->directory_version_id,
            status: (string) $import->status,
            processedRows: (int) $import->processed_rows,
            importedRows: (int) $import->imported_rows,
            failedRows: (int) $import->failed_rows,
            errorMessage: $import->error_message,
        );
    }

    public function channel(): string
    {
        return "directory-import:{$this->importId}";
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'type' => 'status_update',
            'import_id' => $this->importId,
            'status' => $this->status,
            'processed_rows' => $this->processedRows,
            'imported_rows' => $this->importedRows,
            'failed_rows' => $this->failedRows,
            'error_message' => $this->errorMessage === null ? null : Str::limit($this->errorMessage, 500),
        ];
    }

    /** @return array<string, mixed> */
    public function logContext(): array
    {
        return [
            'import_id' => $this->importId,
            'directory_id' => $this->directoryId,
            'directory_version_id' => $this->directoryVersionId,
            'status' => $this->status,
            'processed_rows' => $this->processedRows,
            'imported_rows' => $this->importedRows,
            'failed_rows' => $this->failedRows,
        ];
    }
}
