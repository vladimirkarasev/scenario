<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Validation\ValidationException;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Events\DirectoryImportStatusUpdated;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryImportRepository;
use Throwable;

final readonly class DirectoryImportState
{
    public function __construct(
        private DirectoryImport $model,
        private DirectoryImportRepository $imports,
        private Dispatcher $events,
    ) {
    }

    public function publish(DirectoryImport $import): void
    {
        $this->events->dispatch(DirectoryImportStatusUpdated::fromImport($import));
    }

    public function markProcessing(int $importId): ?DirectoryImport
    {
        $changed = $this->model->getConnection()->transaction(function () use ($importId): bool {
            $import = $this->imports->lockForUpdateOrFail($importId);

            if ($import->status !== DirectoryImportStatus::Pending->value) {
                return false;
            }

            if ($import->directory_version_id === null) {
                throw new DirectoryImportException('Import has no target version.');
            }

            $this->imports->update($import, [
                'status' => DirectoryImportStatus::Processing->value,
                'started_at' => now(),
                'error_message' => null,
            ]);

            return true;
        });

        if (!$changed) {
            return null;
        }

        $import = $this->imports->findOrFail($importId);
        $this->publish($import);

        return $import;
    }

    public function markCompleted(DirectoryImport $import): void
    {
        $import = $this->imports->update($import, [
            'status' => DirectoryImportStatus::Completed->value,
            'finished_at' => now(),
        ]);
        $this->publish($import);
    }

    public function markFailed(DirectoryImport $import, Throwable $exception): void
    {
        $import = $this->imports->update($import, [
            'status' => DirectoryImportStatus::Failed->value,
            'error_message' => str($exception->getMessage())->limit(65_535, '')->toString(),
            'failed_rows' => $import->failed_rows + $this->failedRows($exception),
            'finished_at' => now(),
        ]);
        $this->publish($import);
    }

    private function failedRows(Throwable $exception): int
    {
        return $exception instanceof ValidationException
            ? max(1, count($exception->errors()))
            : 1;
    }
}
