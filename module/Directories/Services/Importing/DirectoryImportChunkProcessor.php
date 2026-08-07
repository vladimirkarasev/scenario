<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Illuminate\Support\Collection;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryImportRepository;

final readonly class DirectoryImportChunkProcessor
{
    public function __construct(
        private DirectoryImport $model,
        private DirectoryImportRepository $imports,
        private DirectoryImportRowProcessor $rows,
        private DirectoryImportState $state,
    ) {
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array{added: int, updated: int, failed: int}
     */
    public function process(int $importId, Collection $rows, int $baseRowNumber): array
    {
        $this->applyConfiguredDelay();
        $import = $this->imports->findWithVersionOrFail($importId);

        if ($import->status !== DirectoryImportStatus::Processing->value) {
            return ['added' => 0, 'updated' => 0, 'failed' => 0];
        }

        $result = $this->rows->importRows($import, $rows, $baseRowNumber);
        $import->increment('processed_rows', $rows->count());
        $this->rememberKeys($import, $result->processedKeys);

        if ($result->failedRows > 0) {
            $import->increment('failed_rows', $result->failedRows);
            $message = collect($result->rowErrors)->filter()->unique()->take(10)->implode(' ');

            if ($message !== '') {
                $this->imports->update($import, [
                    'error_message' => str($message)->limit(65_535, '')->toString(),
                ]);
            }
        }

        $this->state->publish($import->refresh());

        return ['added' => $result->addedCount, 'updated' => $result->updatedCount, 'failed' => $result->failedRows];
    }

    /** @param list<string> $keys */
    private function rememberKeys(DirectoryImport $import, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $this->model->getConnection()->transaction(function () use ($import, $keys): void {
            $locked = $this->imports->lockForUpdateOrFail($import->id);
            $known = array_filter($locked->processed_keys_json ?? [], static fn(string $key): bool => $key !== '');
            $this->imports->update($locked, [
                'processed_keys_json' => array_values(array_unique([...$known, ...$keys])),
            ]);
        });
    }

    private function applyConfiguredDelay(): void
    {
        $delay = config('import.chunk_delay_seconds', 0);

        if (is_int($delay) && $delay > 0) {
            sleep($delay);
        }
    }
}
