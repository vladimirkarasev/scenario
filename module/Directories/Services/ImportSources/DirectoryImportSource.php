<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Module\Directories\DTO\DirectoryImportData;
use Module\Directories\Enums\DirectoryImportSourceType;

interface DirectoryImportSource
{
    public function type(): DirectoryImportSourceType;

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(DirectoryImportData $data): array;
}
