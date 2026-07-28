<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

final readonly class DirectoryImportSourcePayload
{
    public function __construct(
        public string $disk,
        public string $path,
        public ImportSourceConfig $config,
    ) {
    }
}
