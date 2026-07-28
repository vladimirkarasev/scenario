<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

final readonly class DirectoryImportChunkResult
{
    /**
     * @param list<string> $rowErrors
     * @param list<string> $processedKeys
     */
    public function __construct(
        public int $failedRows,
        public array $rowErrors,
        public array $processedKeys,
        public int $addedCount,
        public int $updatedCount,
    ) {
    }
}
