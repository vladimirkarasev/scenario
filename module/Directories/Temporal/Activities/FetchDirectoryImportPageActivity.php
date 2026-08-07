<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Services\Importing\DirectoryImportCoordinator;
use Module\Directories\Services\ImportSources\DirectoryImportSourceResolver;
use Module\Directories\Services\ImportSources\PagedDirectoryImportSource;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;
use Throwable;

final readonly class FetchDirectoryImportPageActivity implements FetchDirectoryImportPageActivityInterface
{
    public function __construct(
        private DirectoryImportSourceResolver $sourceResolver,
        private DirectoryImportCoordinator $importService,
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

        $chunkResult = ['added' => 0, 'updated' => 0, 'failed' => 0];

        if ($result->rows->isNotEmpty()) {
            $baseRowNumber = 2 + $import->processed_rows;
            $chunkResult = $this->importService->importChunk($directoryImportId, $result->rows, $baseRowNumber);
        }

        return [
            'hasMore' => $result->hasMore,
            'endpoint' => $result->endpointName,
            'requestId' => $result->requestId,
            'received' => $result->receivedCount,
            'added' => $chunkResult['added'],
            'updated' => $chunkResult['updated'],
            'failed' => $chunkResult['failed'],
        ];
    }
}
