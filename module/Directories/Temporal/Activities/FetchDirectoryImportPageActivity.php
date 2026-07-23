<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Services\ImportService;
use Module\Directories\Services\ImportSources\DirectoryImportSourceResolver;
use Module\Directories\Services\ImportSources\PagedDirectoryImportSource;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;
use Throwable;

final readonly class FetchDirectoryImportPageActivity implements FetchDirectoryImportPageActivityInterface
{
    public function __construct(
        private DirectoryImportSourceResolver $sourceResolver,
        private ImportService $importService,
    ) {}

    public function fetchPage(int $directoryImportId, int $page): array
    {
        $import = DirectoryImport::query()->findOrFail($directoryImportId);

        if ($import->status !== DirectoryImportStatus::Processing->value) {
            return ['hasMore' => false];
        }

        $source = $this->sourceResolver->forImport($import);

        if (!$source instanceof PagedDirectoryImportSource) {
            throw new ApplicationFailure(
                "Source type [{$import->source_type}] does not support paged fetching.",
                'DirectoryImportUnsupportedSource',
                true,
            );
        }

        try {
            $result = $source->fetchPage($import, $page);
        } catch (Throwable $exception) {
            throw new ApplicationFailure(
                $exception->getMessage(),
                'DirectoryImportPageFailed',
                false,
                EncodedValues::fromValues([$exception->getMessage()]),
            );
        }

        if ($result['rows']->isNotEmpty()) {
            $baseRowNumber = 2 + $import->processed_rows;
            $this->importService->importChunk($directoryImportId, $result['rows'], $baseRowNumber);
        }

        return ['hasMore' => $result['hasMore']];
    }
}
