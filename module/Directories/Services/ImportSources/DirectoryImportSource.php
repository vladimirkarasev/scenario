<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Illuminate\Support\Collection;
use Module\Directories\DTO\DirectoryImportData;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Models\DirectoryImport;

interface DirectoryImportSource
{
    public function type(): DirectoryImportSourceType;

    public function runsInline(): bool;

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(DirectoryImportData $data): array;

    /**
     * @param  callable(int, Collection<int, array<string, mixed>>, int): void  $importChunk
     */
    public function start(DirectoryImport $import, callable $importChunk): void;
}
