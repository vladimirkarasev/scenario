<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Module\Directories\DTO\DirectoryImportPage;
use Module\Directories\DTO\DirectoryImportSourcePayload;
use Module\Directories\DTO\ExcelImportData;
use Module\Directories\DTO\ExcelFilePlan;
use Module\Directories\DTO\ExcelImportSourceConfig;
use Module\Directories\DTO\StoredExcelFile;
use Module\Directories\DTO\UploadedExcelFiles;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Services\ImportSources\Excel\ExcelFileStorage;
use Module\Directories\Services\ImportSources\Excel\ExcelImportPlanBuilder;
use Module\Directories\Services\ImportSources\Excel\ExcelPageReader;
use RuntimeException;
use Module\Directories\Imports\DirectoryExcelChunkImport;

final readonly class FileDirectoryImportSource implements PagedDirectoryImportSource
{
    public function __construct(
        private ExcelFileStorage $storage,
        private ExcelImportPlanBuilder $plans,
        private ExcelPageReader $reader,
    ) {
    }

    public function type(): DirectoryImportSourceType
    {
        return DirectoryImportSourceType::File;
    }

    public function buildPayload(ExcelImportData $data): DirectoryImportSourcePayload
    {
        $plans = $this->plansFor($data);
        $this->ensureSameHeaders($plans);

        $first = $plans[0] ?? throw new RuntimeException('Import file is required.');
        $config = new ExcelImportSourceConfig(
            files: $plans,
            totalChunks: array_sum(
                array_map(
                    static fn(ExcelFilePlan $plan): int => $plan->chunks($data->chunkSize),
                    $plans,
                ),
            ),
        );

        return new DirectoryImportSourcePayload($first->disk, $first->path, $config);
    }

    public function fetchPage(DirectoryImport $import, int $page): DirectoryImportPage
    {
        $config = ExcelImportSourceConfig::fromArray($import->source_config_json);
        $location = $this->locate($config->files, $page, $import->chunk_size);

        if ($location === null) {
            return new DirectoryImportPage($this->emptyRows(), false);
        }

        [$plan, $pageInFile] = $location;
        $sheet = $this->reader->read($plan, $pageInFile, $import->chunk_size);
        $rows = $this->rows($sheet);

        return new DirectoryImportPage(
            rows: $rows,
            hasMore: $page < $config->totalChunks,
            receivedCount: $rows->count(),
        );
    }

    /** @return list<ExcelFilePlan> */
    private function plansFor(ExcelImportData $data): array
    {
        if ($data->files instanceof UploadedExcelFiles) {
            return array_map(
                fn(UploadedFile $file): ExcelFilePlan => $this->plans->build(
                    'local',
                    $this->storage->store($file),
                ),
                $data->files->files,
            );
        }

        if ($data->files instanceof StoredExcelFile) {
            return [$this->plans->build($data->files->disk, $data->files->path)];
        }

        throw new RuntimeException('Import file is required.');
    }

    /**
     * @param list<ExcelFilePlan> $plans
     * @return array{ExcelFilePlan, int}|null
     */
    private function locate(array $plans, int $page, int $chunkSize): ?array
    {
        $remaining = max(1, $page);

        foreach ($plans as $plan) {
            $chunks = $plan->chunks($chunkSize);

            if ($remaining <= $chunks) {
                return [$plan, $remaining];
            }

            $remaining -= $chunks;
        }

        return null;
    }

    /** @param list<ExcelFilePlan> $plans */
    private function ensureSameHeaders(array $plans): void
    {
        $expected = $plans[0]->headers ?? [];

        foreach ($plans as $plan) {
            if ($plan->headers !== $expected) {
                throw new RuntimeException('All Excel files must have the same columns.');
            }
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function rows(DirectoryExcelChunkImport $sheet): Collection
    {
        return $sheet->rows->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function emptyRows(): Collection
    {
        return new Collection();
    }
}
