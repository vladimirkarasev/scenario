<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Illuminate\Support\Collection;

final readonly class DirectoryImportPage
{
    /** @param Collection<int, array<string, mixed>> $rows */
    public function __construct(
        public Collection $rows,
        public bool $hasMore,
        public ?string $endpointName = null,
        public ?string $requestId = null,
        public int $receivedCount = 0,
    ) {
    }
}
